# Visibility module WhatsApp berbasis status Add-on

Tanggal: 2026-11-17  
Status: draft untuk review  
Modul: SaaS Add-ons + WhatsApp Otomatis

## Tujuan

Menyembunyikan seluruh module WhatsApp secara default per tenant (workspace), dan menampilkannya hanya jika Add-on WhatsApp berstatus `active`. Pelanggan (Owner) dapat mengaktifkan Add-on dari marketplace; Superadmin mengelola katalog dan aktivasi per tenant. Aktivasi tidak mengubah Role & Permission Spatie yang sudah ada.

## Keputusan yang disepakati

| Aspek | Pilihan |
| --- | --- |
| Arsitektur | Katalog Add-on terpisah dari `saas_packages` (pendekatan 2) |
| Status | `inactive`, `active`, `suspended`, `expired` — hanya `active` yang membuka module |
| Self-serve | Hanya role Owner |
| Hybrid aktivasi | Gratis/trial → aktif langsung; berbayar → Midtrans penuh; Superadmin bisa aktifkan/suspend/cabut manual |
| Migrasi | Tenant yang sudah punya row `whatsapp_settings` → subscription Add-on WhatsApp `active` |
| Permission | Tetap Spatie `whatsapp.*`; Add-on = lisensi tenant, bukan pengganti permission |
| `WhatsAppSetting.enabled` | Tetap flag teknis kesiapan API; independen dari status Add-on |

## Non-tujuan

- Tidak mencampur Add-on ke dalam `saas_packages` / `Tenant.plan_id`.
- Tidak otomatis memberi seluruh permission WhatsApp saat aktivasi.
- Tidak mengerjakan endpoint Mobile API WhatsApp (belum ada di codebase).
- Tidak mengubah perilaku SATUSEHAT/BPJS (`TenantIntegration`).
- Tidak menambah unit test otomatis kecuali diminta kemudian.

## Konteks existing

- Menu WhatsApp: `MenuCatalog` (heading Owner `owner.group.whatsapp`) + filter `Menu::sidebarFor` berdasarkan role/permission saja.
- Route: `routes/web.php` prefix `whatsapp.*` + middleware `permission:whatsapp.*`.
- Paket dasar: `saas_packages` / `saas_subscriptions` / `saas_invoices` + `MidtransService`.
- Tidak ada sistem Add-on/marketplace saat ini.

## Desain data

### `saas_addons` (katalog platform)

| Kolom | Keterangan |
| --- | --- |
| `code` | unik, mis. `whatsapp` |
| `name` | tampilan, mis. WhatsApp Integration |
| `description` | teks |
| `benefits` | JSON list manfaat |
| `price` | decimal |
| `interval` | `monthly` / `yearly` / `one_time` |
| `trial_days` | integer, default 0 |
| `terms` | ketentuan penggunaan |
| `is_active` | tampil di marketplace |

### `saas_addon_subscriptions` (per tenant)

| Kolom | Keterangan |
| --- | --- |
| `tenant_id`, `addon_id` | unique pair |
| `status` | `inactive` \| `active` \| `suspended` \| `expired` |
| `starts_at`, `ends_at`, `trial_ends_at` | masa aktif; `ends_at` null = tanpa expiry sampai diatur |
| `activated_at`, `suspended_at` | audit |
| `activated_by` | user_id nullable |

### `saas_addon_invoices`

Mirror pola `saas_invoices` (amount, status unpaid/paid, due/paid dates) plus kolom Midtrans yang sama (`payment_channel`, `midtrans_order_id`, snap token, transaction id, status, payload). Relasi ke `saas_addon_subscriptions`.

### Enum

- `AddonCode::WhatsApp = 'whatsapp'`
- `AddonStatus`: inactive, active, suspended, expired
- `AddonBillingInterval`: `monthly` | `yearly` | `one_time` (terpisah dari `BillingInterval` paket dasar)

## Layanan inti

### `AddonGate`

`isActive(Tenant $tenant, AddonCode $code): bool` — true hanya jika subscription status `active` dan (`ends_at` null atau masih di masa depan).

### `AddonService`

- `activate` (gratis/trial atau setelah bayar / superadmin)
- `suspend`, `revoke` → inactive, `markExpired`
- Seed migrasi dari `whatsapp_settings`
- Setelah perubahan status, request berikutnya memakai `AddonGate` per-request (lihat menu/sidebar); tidak perlu mengandalkan bust cache permission untuk visibility Add-on

### `AddonBillingService`

- Buat/ensure subscription + invoice unpaid untuk Add-on berbayar
- Order id Midtrans ber-prefix `ADD-` agar webhook membedakan dari invoice paket
- `markPaid` → panggil `AddonService::activate`

### Midtrans

Perluas `MidtransService` (atau resolver bersama): lookup invoice paket **atau** invoice Add-on by `midtrans_order_id` / number; jalur `markPaid` masing-masing tidak saling menimpa.

## Gate akses

### Middleware

- Alias `addon:whatsapp` → `EnsureAddonActive`
- Dipasang pada seluruh group route `whatsapp.*`
- Abort 403 (HTML/JSON sesuai request) jika Add-on tidak active
- Job/command WhatsApp (scheduler otomasi) wajib cek `AddonGate` sebelum kirim

### Menu / sidebar

- Item dengan `route_name` berawalan `whatsapp.` hanya visible jika `AddonGate::isActive` (MVP tidak menambah kolom meta menu)
- Heading kosong tetap dihilangkan oleh filter children existing
- **Cache sidebar:** filter Add-on diterapkan **setelah** cache permission (per-request), karena fingerprint cache saat ini hanya role+permission dan dipakai lintas tenant. Jangan mengandalkan `rememberForever` untuk status Add-on. Setelah aktivasi, request berikutnya langsung melihat menu tanpa logout; bust cache permission tidak wajib untuk kasus Add-on.

### Marketplace (tenant)

- Menu Owner: Add-ons / Marketplace (`addon.view`)
- List katalog aktif; detail WhatsApp (nama, deskripsi, manfaat, harga, status, terms)
- Aksi Owner-only: Aktifkan (gratis/trial) atau Bayar & Aktifkan (Snap)
- Setelah sukses: redirect + flash; sidebar refresh via cache bust

### Permission baru

| Permission | Scope |
| --- | --- |
| `addon.view` | Tenant — lihat marketplace |
| `addon.manage` | Platform Superadmin — CRUD katalog & kelola aktivasi tenant |

Permission `whatsapp.*` tidak diubah oleh aktivasi Add-on.

## Superadmin

Menu SaaS **Add-ons**:

1. CRUD katalog `saas_addons` (harga, interval, trial, terms, aktif katalog)
2. Daftar aktivasi per tenant (filter addon/status/tenant): aktifkan, tangguhkan, cabut, perpanjang `ends_at`
3. Lihat tanggal aktivasi, masa berlaku, invoice terkait

## Migrasi & cron

1. Migration tiga tabel di atas (+ seed katalog `whatsapp`)
2. Command/seeder migrasi: tenant dengan `whatsapp_settings` → subscription `active` (tanpa invoice; `ends_at` null)
3. Tenant baru / tanpa settings: tidak ada row aktif → module tersembunyi
4. Cron: `active` + `ends_at` past → `expired` (pola `ExpireTenantSubscriptions`)
5. Scheduler WhatsApp skip tenant non-active Add-on

## Alur utama

```text
Owner → Marketplace → WhatsApp Integration
  ├─ price=0 atau trial → AddonService.activate → status active → menu muncul
  └─ price>0 → invoice + Midtrans Snap → webhook paid → activate → menu muncul

Superadmin → set suspended/inactive/expired → menu hilang + route 403
User non-Owner dengan whatsapp.* → tetap butuh Add-on active; tidak bisa self-activate
```

## Kriteria keberhasilan

- Workspace baru default tanpa akses module WhatsApp
- Menu/submenu WhatsApp hanya muncul jika Add-on `active`
- Setelah aktif/nonaktif/suspend, visibility berubah tanpa wajib login ulang
- URL/API WhatsApp web diblok jika Add-on tidak `active`
- Permission role tetap berlaku setelah Add-on aktif
- Perubahan status satu tenant tidak memengaruhi tenant lain
- Status Add-on per tenant (multi-tenant)

## File utama yang terdampak (perkiraan)

- Migration + models/enums Add-on
- `AddonGate`, `AddonService`, `AddonBillingService`
- `MidtransService` (resolve invoice Add-on)
- Middleware + `bootstrap/app.php` alias
- `routes/web.php` (group whatsapp + marketplace + saas addons)
- `MenuCatalog`, `Menu` (visibility + cache)
- Controllers/views: marketplace tenant + SaaS Add-ons
- WhatsApp jobs/commands (gate)
- Seeder permission `addon.view` / `addon.manage` + migrasi data

## Error handling

- Aktivasi non-Owner → 403
- Add-on katalog nonaktif → tidak bisa self-serve baru
- Midtrans gagal → invoice tetap unpaid; flash error; status subscription tidak `active`
- Webhook signature invalid → sama seperti jalur paket (tolak)
- Double pay / already paid → idempotent `markPaid`
