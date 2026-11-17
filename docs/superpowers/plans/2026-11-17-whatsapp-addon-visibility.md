# WhatsApp Add-on Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sembunyikan module WhatsApp per tenant sampai Add-on WhatsApp berstatus `active`, dengan marketplace Owner, kelola Superadmin, dan pembayaran Midtrans untuk Add-on berbayar.

**Architecture:** Katalog `saas_addons` + `saas_addon_subscriptions` + `saas_addon_invoices` terpisah dari paket dasar. `AddonGate` + middleware `addon:whatsapp` mengunci route/job; filter sidebar Add-on diterapkan per-request setelah cache permission. Billing Add-on memakai pola Midtrans yang sudah ada (order id prefix `ADD-`).

**Tech Stack:** Laravel 10, Blade + jQuery, Spatie Permission, Midtrans Snap, multi-tenant `tenant_id`.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-11-17-whatsapp-addon-visibility-design.md`
- Add-on terpisah dari `saas_packages` / `Tenant.plan_id`
- Hanya status `active` yang membuka WhatsApp; permission Spatie `whatsapp.*` tetap wajib
- Self-serve aktivasi: role Owner saja
- Hybrid: gratis/trial langsung; berbayar Midtrans penuh; Superadmin bisa aktifkan/suspend/cabut
- Migrasi: tenant dengan row `whatsapp_settings` → subscription WhatsApp `active`
- `WhatsAppSetting.enabled` tetap flag teknis API (independen)
- **Jangan** jalankan Pest/PHPUnit; verifikasi manual
- **Jangan** commit kecuali user meminta
- Ikuti UI `resources/views/pages/pendaftaran/` / pola SaaS existing; jangan browser automation kecuali diminta
- Respons & kode mengikuti konvensi modul klinikdent (Blade + jQuery, connection DB yang benar)

## File map

| File | Tanggung jawab |
| --- | --- |
| `database/migrations/2026_11_17_120000_create_saas_addon_tables.php` | Tabel addons / subscriptions / invoices |
| `app/Enums/AddonCode.php` | Kode katalog (`whatsapp`) |
| `app/Enums/AddonStatus.php` | inactive/active/suspended/expired |
| `app/Enums/AddonBillingInterval.php` | monthly/yearly/one_time |
| `app/Models/SaasAddon.php` | Katalog |
| `app/Models/SaasAddonSubscription.php` | Status per tenant |
| `app/Models/SaasAddonInvoice.php` | Invoice Add-on + Midtrans |
| `app/Support/Saas/AddonGate.php` | `isActive(Tenant, AddonCode)` |
| `app/Support/Saas/AddonService.php` | activate/suspend/revoke/expire/migrasi |
| `app/Support/Saas/AddonBillingService.php` | invoice + markPaid → activate |
| `app/Support/Saas/MidtransService.php` | Snap Add-on + webhook dual lookup |
| `app/Http/Middleware/EnsureAddonActive.php` | Middleware `addon:{code}` |
| `bootstrap/app.php` | Alias middleware |
| `routes/web.php` | Group whatsapp + marketplace + saas addons |
| `app/Models/Menu.php` | Filter Add-on per-request di `sidebarFor` |
| `app/Support/Access/MenuCatalog.php` | Menu marketplace + SaaS Add-ons |
| `app/Support/Access/PermissionCatalog.php` | `addon.view`, `addon.manage` |
| `app/Http/Controllers/Owner/AddonMarketplaceController.php` | Marketplace tenant |
| `app/Http/Controllers/Saas/AddonController.php` | CRUD katalog |
| `app/Http/Controllers/Saas/AddonSubscriptionController.php` | Kelola aktivasi tenant |
| `app/Http/Controllers/MidtransNotificationController.php` | Handle invoice Add-on |
| `app/Console/Commands/ExpireAddonSubscriptions.php` | Cron expire |
| `app/Console/Commands/MigrateWhatsAppAddonActivations.php` | Migrasi data existing |
| `app/Support/WhatsApp/WhatsAppSchedulerService.php` | Skip tenant tanpa Add-on active |
| `database/seeders/*` / sync permissions | Seed katalog + permission |
| Views `resources/views/addons/*`, `resources/views/saas/addons/*` | UI |

---

### Task 1: Enums, migration, models

**Files:**
- Create: `app/Enums/AddonCode.php`
- Create: `app/Enums/AddonStatus.php`
- Create: `app/Enums/AddonBillingInterval.php`
- Create: `database/migrations/2026_11_17_120000_create_saas_addon_tables.php`
- Create: `app/Models/SaasAddon.php`
- Create: `app/Models/SaasAddonSubscription.php`
- Create: `app/Models/SaasAddonInvoice.php`
- Modify: `app/Models/Tenant.php` (relasi `addonSubscriptions`)

**Interfaces:**
- Produces: models + enums di atas; `SaasAddon::isFree(): bool`; `SaasAddonSubscription` cast `AddonStatus`

- [ ] **Step 1: Buat enums**

```php
// app/Enums/AddonCode.php
enum AddonCode: string
{
    case WhatsApp = 'whatsapp';
}

// app/Enums/AddonStatus.php
enum AddonStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';

    public function label(): string { /* … */ }
    public function badgeClass(): string { /* … */ }
}

// app/Enums/AddonBillingInterval.php
enum AddonBillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case OneTime = 'one_time';

    public function months(): ?int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
            self::OneTime => null,
        };
    }
}
```

- [ ] **Step 2: Buat migration tiga tabel**

`saas_addons`: code unique, name, description text nullable, benefits json nullable, price decimal 15,2 default 0, interval string 20 default monthly, trial_days unsigned default 0, terms text nullable, is_active bool default true, timestamps.

`saas_addon_subscriptions`: tenant_id FK cascade, addon_id FK restrict, status string 20 default inactive, starts_at/ends_at/trial_ends_at/activated_at/suspended_at nullable timestamps, activated_by nullable FK users nullOnDelete, unique(tenant_id, addon_id), index(status, ends_at).

`saas_addon_invoices`: mirror `saas_invoices` + midtrans columns; FK `addon_subscription_id` cascade; `number` unique; status default unpaid.

- [ ] **Step 3: Buat models + relasi Tenant**

Ikuti pola `SaasPackage` / `SaasInvoice` (fillable, casts, relations). `SaasAddon::isFree()` → `(float) $this->price <= 0`.

- [ ] **Step 4: Jalankan migration**

Run: `php artisan migrate --path=database/migrations/2026_11_17_120000_create_saas_addon_tables.php`  
Expected: tiga tabel terbuat tanpa error.

- [ ] **Step 5: Verifikasi manual skema**

Run (PowerShell-safe):  
`php artisan tinker --execute="var_export(Schema::hasTable('saas_addons'));"`  
Expected: `true` (ulangi untuk dua tabel lain).

---

### Task 2: AddonGate + AddonService

**Files:**
- Create: `app/Support/Saas/AddonGate.php`
- Create: `app/Support/Saas/AddonService.php`

**Interfaces:**
- Consumes: `SaasAddon`, `SaasAddonSubscription`, `AddonCode`, `AddonStatus`, `Tenant`, `User`
- Produces:
  - `AddonGate::isActive(Tenant $tenant, AddonCode $code): bool`
  - `AddonService::ensureSubscription(Tenant $tenant, SaasAddon $addon): SaasAddonSubscription`
  - `AddonService::activate(SaasAddonSubscription $sub, ?User $actor = null, bool $trial = false): SaasAddonSubscription`
  - `AddonService::suspend(...)`, `revoke(...)` → inactive, `markExpired(...)`
  - `AddonService::migrateFromWhatsAppSettings(): int`
  - `AddonService::expireOverdue(): int`

- [ ] **Step 1: Implement `AddonGate`**

```php
final class AddonGate
{
    public static function isActive(Tenant $tenant, AddonCode $code): bool
    {
        $sub = SaasAddonSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('addon', fn ($q) => $q->where('code', $code->value))
            ->first();

        if (! $sub || $sub->status !== AddonStatus::Active) {
            return false;
        }

        return $sub->ends_at === null || $sub->ends_at->isFuture();
    }
}
```

Catatan: jika `ends_at` sudah lewat tapi status masih active, anggap tidak aktif (cron akan menormalisasi).

- [ ] **Step 2: Implement `AddonService`**

Logika `activate`:
1. Hitung `ends_at`: trial → `now + trial_days`; monthly/yearly → `now + months`; one_time / ends null untuk migrasi/superadmin tanpa expiry.
2. Set status `active`, `starts_at`/`activated_at` now, `activated_by`, clear `suspended_at`.
3. Jangan sentuh permission Spatie.

`suspend`: status suspended + `suspended_at`.  
`revoke`: status inactive.  
`markExpired`: status expired.  
`expireOverdue`: query active where ends_at < now → expired.  
`migrateFromWhatsAppSettings`: pastikan addon code whatsapp ada; untuk tiap distinct `tenant_id` di `whatsapp_settings`, ensure subscription + activate tanpa invoice (`ends_at` null).

- [ ] **Step 3: Verifikasi manual via tinker**

Buat subscription dummy inactive → `AddonGate::isActive` false; activate → true; suspend → false.

---

### Task 3: Middleware + kuncikan di route WhatsApp

**Files:**
- Create: `app/Http/Middleware/EnsureAddonActive.php`
- Modify: `bootstrap/app.php` (alias `addon`)
- Modify: `routes/web.php` (group `whatsapp` → `middleware(['addon:whatsapp'])`)

**Interfaces:**
- Consumes: `AddonGate`, `AddonCode::from($parameter)`
- Produces: abort 403 jika user tenant dan Add-on tidak active; platform admin boleh lewat (opsional: hanya tenant user yang dicek)

- [ ] **Step 1: Middleware**

```php
class EnsureAddonActive
{
    public function handle(Request $request, Closure $next, string $code): Response
    {
        $user = $request->user();
        if (! $user || $user->isPlatformAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;
        if (! $tenant || ! AddonGate::isActive($tenant, AddonCode::from($code))) {
            abort(403, 'Add-on belum aktif untuk klinik ini.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Alias di `bootstrap/app.php`**

`'addon' => \App\Http\Middleware\EnsureAddonActive::class,`

- [ ] **Step 3: Wrap group WhatsApp**

Pada `Route::prefix('whatsapp')->name('whatsapp.')->group(...)`, tambahkan `->middleware('addon:whatsapp')` di level group (tetap pertahankan permission per-route).

- [ ] **Step 4: Verifikasi manual**

Login tenant tanpa Add-on active → buka `/whatsapp` → 403.  
Setelah activate di tinker → 200 (jika punya permission).

---

### Task 4: Permission, MenuCatalog, filter sidebar

**Files:**
- Modify: `app/Support/Access/PermissionCatalog.php`
- Modify: `app/Support/Access/MenuCatalog.php`
- Modify: `app/Models/Menu.php` (`sidebarFor`)
- Modify: sync/seeder permission existing (pola proyek — biasanya artisan sync dari catalog; cari command/seeder yang sync `PermissionCatalog` dan jalankan sesuai pola)

**Interfaces:**
- Consumes: `AddonGate`, `AddonCode::WhatsApp`
- Produces: permission `addon.view` (Owner), `addon.manage` (platform); menu marketplace + SaaS; sidebar hide `whatsapp.*` tanpa Add-on

- [ ] **Step 1: Permission**

Tambah ke `platform()`: `'addon.manage'`.  
Tambah ke `tenant()` atau khusus `forOwner()`: `'addon.view'`.  
Pastikan `forOwner()` punya `addon.view`; Superadmin dapat `addon.manage` via `platform()`.

- [ ] **Step 2: Sync permission ke DB**

Jalankan mekanisme sync permission yang sudah dipakai proyek (mis. seeder / `permission:sync` jika ada). Pastikan Owner & SuperAdminSaas mendapat permission baru.

- [ ] **Step 3: MenuCatalog**

Owner — di group Langganan atau group baru:

```php
self::heading('owner.group.addons', 'Add-ons', [
    self::item('owner.addons', 'Marketplace', 'bx bx-store', 'addons.index', 'addon.view', 'Aktifkan Add-on klinik.'),
]),
```

SuperAdmin — di bawah Subscription:

```php
self::item('saas.addons', 'Add-ons', 'bx bx-puzzle', null, 'addon.manage', 'Katalog dan aktivasi Add-on.', [
    self::item('saas.addons.catalog', 'Katalog', 'bx bx-list-ul', 'saas.addons.index', 'addon.manage', 'Master Add-on.'),
    self::item('saas.addons.subscriptions', 'Aktivasi Tenant', 'bx bx-check-shield', 'saas.addon-subscriptions.index', 'addon.manage', 'Status Add-on per klinik.'),
]),
```

Sync menu dari catalog sesuai pola existing (`Menu` sync command/seeder).

- [ ] **Step 4: Filter Add-on per-request di `Menu::sidebarFor`**

Setelah `$filtered` dari cache permission, sebelum return, panggil filter baru:

```php
$filtered = static::filterByAddonEntitlements($filtered, $user);
```

Implementasi: map/filter rekursif — jika `route_name` string dan `str_starts_with($route, 'whatsapp.')`, require `$user->tenant` + `AddonGate::isActive(..., WhatsApp)`. Heading tanpa children hilang (reuse logika filterVisible children). **Jangan** masukkan status Add-on ke `rememberForever` cache key.

- [ ] **Step 5: Verifikasi manual**

Tenant tanpa Add-on: sidebar tanpa group WhatsApp; menu Marketplace terlihat untuk Owner.  
Activate Add-on: refresh halaman → group WhatsApp muncul tanpa logout.

---

### Task 5: Marketplace Owner (UI + aktivasi gratis/trial)

**Files:**
- Create: `app/Http/Controllers/Owner/AddonMarketplaceController.php`
- Create: `resources/views/addons/index.blade.php`
- Create: `resources/views/addons/show.blade.php`
- Modify: `routes/web.php` (routes `addons.*` di group tenant/owner)

**Interfaces:**
- Consumes: `AddonService`, `SaasAddon`, Owner check
- Produces: `addons.index`, `addons.show`, `addons.activate` (POST)

- [ ] **Step 1: Routes**

```php
Route::get('/addons', [AddonMarketplaceController::class, 'index'])
    ->middleware('permission:addon.view')->name('addons.index');
Route::get('/addons/{saasAddon:code}', [AddonMarketplaceController::class, 'show'])
    ->middleware('permission:addon.view')->name('addons.show');
Route::post('/addons/{saasAddon:code}/activate', [AddonMarketplaceController::class, 'activate'])
    ->middleware('permission:addon.view')->name('addons.activate');
```

- [ ] **Step 2: Controller**

- `index`: list `SaasAddon` where `is_active`, with subscription current tenant.
- `show`: detail name/description/benefits/price/status/terms.
- `activate`: abort 403 unless `$user->hasRole(RoleName::Owner)`; jika addon tidak free dan trial_days=0 → redirect ke pay (Task 6) atau flash “gunakan Bayar”; jika free atau trial → `AddonService::activate` lalu redirect success.

- [ ] **Step 3: Views**

Ikuti card/breadcrumb/alert pola Owner (subscription/plan views sebagai acuan). Tombol: **Aktifkan Add-on** (gratis/trial) atau **Bayar & Aktifkan** (Task 6).

- [ ] **Step 4: Seed katalog WhatsApp**

Di seeder/migration seeder:

```php
SaasAddon::query()->updateOrCreate(
    ['code' => 'whatsapp'],
    [
        'name' => 'WhatsApp Integration',
        'description' => 'Pengingat jadwal, promosi, dan otomasi WhatsApp untuk klinik.',
        'benefits' => ['Pengingat kunjungan otomatis', 'Promosi ke pasien consent', 'Template & riwayat pesan'],
        'price' => 0, // atau harga produksi
        'interval' => AddonBillingInterval::Monthly,
        'trial_days' => 0,
        'terms' => 'Dengan mengaktifkan, klinik menyetujui ketentuan penggunaan WhatsApp Integration.',
        'is_active' => true,
    ]
);
```

- [ ] **Step 5: Verifikasi manual**

Owner buka Marketplace → detail WhatsApp → Aktifkan (harga 0) → menu WhatsApp muncul. Non-Owner POST activate → 403.

---

### Task 6: Billing Add-on + Midtrans

**Files:**
- Create: `app/Support/Saas/AddonBillingService.php`
- Modify: `app/Support/Saas/MidtransService.php`
- Modify: `app/Http/Controllers/MidtransNotificationController.php`
- Modify: `app/Http/Controllers/Owner/AddonMarketplaceController.php` (pay/checkout/finish)
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `SaasAddonInvoice`, `AddonService`, Midtrans settings
- Produces:
  - `AddonBillingService::issueInvoice(SaasAddonSubscription $sub, string|float $amount): SaasAddonInvoice`
  - `AddonBillingService::markPaid(SaasAddonInvoice $invoice, array $payment = []): SaasAddonInvoice` → activate subscription
  - `MidtransService::createAddonSnapToken(SaasAddonInvoice $invoice, User $user): string`
  - Webhook resolves `ADD-…` / addon invoice numbers

- [ ] **Step 1: `AddonBillingService`**

Mirror `SubscriptionService::issueInvoice` / `markPaid` tetapi untuk addon invoice; nomor invoice unik prefix mis. `ADDINV-YYYYMM-#####`. Setelah paid → `AddonService::activate($subscription, null, false)` dengan `ends_at` sesuai interval.

- [ ] **Step 2: Perluas Midtrans**

- Inject `AddonBillingService` (opsional) atau resolve dari container di method.
- `createAddonSnapToken`: order_id = `$invoice->number.'-'.now()->format('His')` (number sudah ber-prefix ADD); callback finish → `route('addons.invoices.finish')`.
- `handleNotification`: setelah `findInvoice` gagal, `findAddonInvoice($orderId)`; jika ketemu → `applyAddonStatus` → `AddonBillingService::markPaid`.
- Return type notification: longgarkan ke model union / base — update controller:

```php
$invoice = $midtrans->handleNotification($request->all());
// jika SaasAddonInvoice atau SaasInvoice, log sesuai
```

- [ ] **Step 3: Routes pay**

```php
Route::post('/addons/{saasAddon:code}/pay', ...)->name('addons.pay');
Route::get('/addons/invoices/midtrans/finish', ...)->name('addons.invoices.finish');
```

`pay`: Owner only; ensure subscription inactive; issue invoice; snap token; return view checkout mirip subscription (Snap.js).

- [ ] **Step 4: Verifikasi manual**

Set price > 0 di katalog; Owner Bayar & Aktifkan → Snap sandbox (jika Midtrans ready); webhook/settlement → status active. Signature invalid → 403.

---

### Task 7: Superadmin kelola Add-on

**Files:**
- Create: `app/Http/Controllers/Saas/AddonController.php`
- Create: `app/Http/Controllers/Saas/AddonSubscriptionController.php`
- Create: `app/Http/Requests/Saas/StoreSaasAddonRequest.php`, `UpdateSaasAddonRequest.php`
- Create: `resources/views/saas/addons/index.blade.php` (+ partial fields)
- Create: `resources/views/saas/addon-subscriptions/index.blade.php`
- Modify: `routes/web.php` di group `saas.*`
- Optional: Policy `SaasAddonPolicy` authorize via `addon.manage`

**Interfaces:**
- Consumes: `AddonService`
- Produces: CRUD katalog; list/filter subscriptions; aksi activate/suspend/revoke/extend `ends_at`

- [ ] **Step 1: Routes SaaS**

```php
Route::get('addons', ...)->middleware('permission:addon.manage')->name('addons.index');
Route::post('addons', ...);
Route::put('addons/{saasAddon}', ...);
Route::post('addons/{saasAddon}/toggle', ...);
Route::get('addon-subscriptions', ...)->name('addon-subscriptions.index');
Route::post('addon-subscriptions/{saasAddonSubscription}/activate', ...);
Route::post('addon-subscriptions/{saasAddonSubscription}/suspend', ...);
Route::post('addon-subscriptions/{saasAddonSubscription}/revoke', ...);
Route::put('addon-subscriptions/{saasAddonSubscription}/extend', ...);
```

- [ ] **Step 2: UI katalog** — pola `saas/packages/index` (tabel, modal/form harga, interval, trial, terms, toggle aktif).

- [ ] **Step 3: UI aktivasi tenant** — filter tenant/addon/status; kolom activated_at, ends_at; tombol aksi; tampilkan invoice terkait (relasi hasMany).

- [ ] **Step 4: Verifikasi manual**

Superadmin buat/ubah harga; aktifkan Add-on untuk tenant B; tenant A tidak terpengaruh; suspend tenant B → menu WhatsApp hilang + `/whatsapp` 403.

---

### Task 8: Migrasi data, cron expire, gate scheduler WhatsApp

**Files:**
- Create: `app/Console/Commands/MigrateWhatsAppAddonActivations.php`
- Create: `app/Console/Commands/ExpireAddonSubscriptions.php`
- Modify: `routes/console.php` (schedule expire daily)
- Modify: `app/Support/WhatsApp/WhatsAppSchedulerService.php`

**Interfaces:**
- Consumes: `AddonService`, `AddonGate`
- Produces: commands `saas:migrate-whatsapp-addons`, `saas:expire-addons`

- [ ] **Step 1: Command migrasi**

```php
protected $signature = 'saas:migrate-whatsapp-addons';
// handle → AddonService::migrateFromWhatsAppSettings()
```

Jalankan sekali di deploy: `php artisan saas:migrate-whatsapp-addons`.

- [ ] **Step 2: Command expire + schedule**

```php
Schedule::command(ExpireAddonSubscriptions::class)->dailyAt('01:20');
```

- [ ] **Step 3: Gate di scheduler**

Di `WhatsAppSchedulerService`, setelah load `$settings` / `$tenantIds`, filter:

```php
$tenantIds = collect($tenantIds)
    ->filter(function ($tenantId) {
        $tenant = Tenant::query()->find($tenantId);
        return $tenant && AddonGate::isActive($tenant, AddonCode::WhatsApp);
    })
    ->values()
    ->all();
```

Terapkan di setiap path yang mengirim (atau satu helper `eligibleTenantIds()` di awal method yang memakai settings).

- [ ] **Step 4: Verifikasi manual**

Tenant dengan settings tapi belum dimigrasi → setelah command, Add-on active.  
Tenant active dengan `ends_at` kemarin → `saas:expire-addons` → expired → scheduler tidak kirim.

---

### Task 9: Verifikasi end-to-end (manual checklist)

- [ ] **Step 1: Workspace baru** tanpa `whatsapp_settings` → tidak ada menu WhatsApp; `/whatsapp` 403.
- [ ] **Step 2: Owner aktifkan gratis** → menu muncul tanpa logout; user dengan `whatsapp.view` bisa akses; tanpa permission tetap 403 permission.
- [ ] **Step 3: Superadmin suspend** → menu hilang; URL 403; tenant lain tidak berubah.
- [ ] **Step 4: Berbayar (sandbox)** → unpaid → belum active; after settlement → active.
- [ ] **Step 5: Migrasi** tenant lama dengan settings → active tanpa invoice.
- [ ] **Step 6: Pastikan** aktivasi tidak menambah permission baru ke role selain yang sudah di-seed (`addon.view` / `addon.manage` saja).

---

## Spec coverage (self-review)

| Requirement spec | Task |
| --- | --- |
| Hide WhatsApp default | 3, 4 |
| Marketplace + aktivasi Owner | 5, 6 |
| Status inactive/active/suspended/expired | 1, 2, 7, 8 |
| Middleware + menu + permission | 3, 4 |
| Superadmin kelola | 7 |
| Midtrans penuh | 6 |
| Migrasi whatsapp_settings | 8 |
| Scheduler gate | 8 |
| Multi-tenant isolation | 2, 7 checklist |
| Sidebar tanpa logout | 4 (per-request filter) |
| Permission tidak diganti aktivasi | 2, 9 |

## Placeholder / konsistensi

- Order id Add-on memakai nomor invoice ber-prefix `ADD` / `ADDINV` agar lookup webhook jelas.
- Filter sidebar **per-request**, bukan cache forever.
- Interval enum: `AddonBillingInterval` (bukan `BillingInterval`).
