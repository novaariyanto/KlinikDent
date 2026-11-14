# Prompt: Aplikasi Flutter KlinikDent (Mobile)

Salin dokumen ini ke sesi AI/coding untuk membangun aplikasi Flutter di atas API mobile KlinikDent yang sudah ada. Jangan membuat endpoint baru; ikuti kontrak di repo backend.

## Tujuan

Satu aplikasi Flutter multi-role untuk klinik gigi: **Ops** (pendaftaran + antrean + farmasi + kasir), **Dokter**, dan **Owner**. Navigasi dan aksi mengikuti `permissions` dari `GET /api/v1/me`, bukan hardcode nama role saja.

Bahasa UI: Indonesia.

## Di luar lingkup

- Master data penuh, WhatsApp, SATUSEHAT/BPJS admin, panel SaaS
- Hub laporan `/reports/*` dan `/finance/reports/*` (hanya ringkasan dashboard)
- Monitor antrean ruang tunggu (tetap web)
- Mengubah API Laravel

## Stack default

- Flutter 3.x, Dart 3
- HTTP client: `dio` (atau `http`)
- Token: secure storage (mis. `flutter_secure_storage`)
- State: Riverpod (atau setara); tetap tipis
- PDF: buka stream `application/pdf` di viewer dalam app
- Base URL: konfigurasi env/`{{BASE_URL}}` — jangan hardcode produksi di kode contoh

## Kontrak API

Prefix: `{{BASE_URL}}/api/v1`

| Header | Kapan |
| --- | --- |
| `Authorization: Bearer {token}` | Semua rute kecuali login dan register |
| `X-Branch-Id: {id}` | Setelah login; wajib untuk hampir semua fitur klinik |
| `Accept: application/json` | Default; PDF tanpa memaksa JSON |
| `Content-Type: application/json` | Body JSON; onboarding logo bisa `multipart/form-data` |

Sukses: isi utama di `data` (login/register juga menaruh `token` di akar respons).

Daftar: `page`, `per_page` (default 20, max 50), `meta.current_page`, `meta.last_page`, `meta.total`.

Penulisan sukses: objek + `message` (pesan sama semangat dengan web).

PDF: body binary `application/pdf` (kecuali receipt tagihan bisa HTML seperti API).

Rujukan API lengkap (satu file):

- `docs/superpowers/specs/2026-11-14-mobile-api-reference.md`

Spek desain modul (opsional):

- `docs/superpowers/specs/2026-11-14-mobile-api-foundation-design.md`
- `docs/superpowers/specs/2026-11-14-mobile-api-clinical-desk-design.md`
- `docs/superpowers/specs/2026-11-14-mobile-api-ops-pharmacy-billing-design.md`
- `docs/superpowers/specs/2026-11-14-mobile-api-dashboard-design.md`

## Auth dan sesi

1. Layar login + tautan **Daftar klinik**
2. `POST /auth/register` — body: `name` (klinik), `owner_name`, `owner_email`, `password`, `password_confirmation`; `device_name` opsional. Tidak perlu pilih paket (`package_id` opsional; server pakai trial default). Subdomain digenerate server.
3. Atau `POST /auth/login` — body: `email`, `password`, `device_name` (opsional)
4. Simpan `token` dari respons; `data` berisi profil (`needs_onboarding` di `tenant`)
5. Simpan `active_branch` dan daftar `branches`; setiap request klinik kirim `X-Branch-Id`
6. Owner dengan onboarding belum selesai (setelah register hampir selalu) → alur `/onboarding*` sampai `complete`
7. `POST /auth/logout` — hapus token lokal

Jangan log password atau token.

## Error (tangani di klien)

| HTTP / code | Perilaku app |
| --- | --- |
| 401 | Hapus sesi → layar login |
| 422 | Tampilkan `message` / errors field |
| 429 | Throttle login/register — pesan tunggu |
| 403 `forbidden` | Sembunyikan/matikan aksi |
| 403 `branch_required` / `branch_forbidden` / `branch_inactive` | Pemilih cabang |
| 403 `tenant_suspended` | Mode baca; nonaktifkan POST/PUT/DELETE |
| 403 `owner_only` / `platform_admin_web_only` / `account_inactive` / `tenant_required` | Pesan + keluar atau blokir fitur |
| 404 | Resource tidak ada / di luar tenant |

## Tiga persona

### Ops (farmasi + kasir digabung)

Layar inti: Dashboard → Pasien & kunjungan → Antrean → Resep farmasi → Stok (ringkas) → Tagihan & bayar → Shift kasir.

Endpoint inti:

- `GET /dashboard`
- `GET|POST|PUT|DELETE /patients…`, `GET /wilayah/…`, `GET|POST /visits…`
- `GET /queues`, `POST /queues/{id}/call|complete|skip`
- `GET /pharmacy/prescriptions` (`scope=incoming|processing|completed|history`), fulfill, labels PDF
- `GET /pharmacy/stock` (+ adjustments/opname/returns bila izin ada)
- `GET /billing/invoices` (`scope=today|history`), show, pay, print/receipt
- `GET|POST /cashier/shifts/open|close`, transactions

### Dokter

Layar inti: Dashboard → Antrean saya → Pemeriksaan (care) → PDF → Riwayat pasien → Surat.

Endpoint inti:

- `GET /dashboard`
- `GET /queues?scope=mine` (dan today/waiting sesuai izin)
- `GET /visits/{visit}/care` + PUT/POST care (record, vitals, tooth, diagnoses, procedures, prescriptions, …)
- PDF: resume, resep, rujukan, dll. di bawah `/visits/{visit}/care/…/pdf`
- `GET /patients/{patient}/history…`
- `GET|POST /medical-letters…`

Hormati kunci rekam: tulisan yang ditolak API (record terkunci / suspended) tampilkan `message`.

### Owner

Layar inti: Daftar/login → Onboarding (jika `needs_onboarding`) → Dashboard (termasuk `setup_checklist`) → pantau lewat kartu dashboard (bukan hub laporan penuh).

Endpoint inti:

- `POST /auth/register`, `POST /auth/login`
- `GET /dashboard`, `GET /me`
- `GET|POST /onboarding…`

## Aturan produk singkat

- Satu shell app; tab/menu difilter permission
- Cabang aktif selalu terlihat dan bisa diganti dari daftar `me.branches`
- Jangan tampilkan rahasia integrasi atau `queue_monitor_token`
- Verifikasi manual terhadap API nyata; jangan mengarang path di luar spek
