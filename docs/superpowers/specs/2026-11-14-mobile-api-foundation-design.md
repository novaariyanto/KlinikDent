# Fondasi API Mobile

API JSON untuk aplikasi mobile. Potongan ini mencakup login token, profil, izin, cabang aktif, dan onboarding owner. Fitur klinik lain (antrean, rekam medis, kasir, farmasi, laporan) di luar lingkup.

## Keputusan

- Semua role tenant boleh login. Super Admin SaaS tidak menerima token.
- Token Laravel Sanctum, ability `mobile`, berlaku 30 hari.
- Login tidak membuat session web. Password dicek dengan `Hash::check`.
- Cabang aktif dari header `X-Branch-Id`. Header kosong memakai `users.branch_id` bila terisi dan sah. Bila `branch_id` juga kosong, pakai cabang aktif pertama yang boleh diakses.
- Onboarding owner tersedia di mobile dan memakai `OnboardingService` serta FormRequest yang sudah ada.
- Role selain owner tidak diarahkan ke wizard.
- Verifikasi manual lewat HTTP. Tidak menambah file Pest atau PHPUnit.

## Skema dan paket

Tambah `laravel/sanctum`. Terbitkan konfigurasi dan migration `personal_access_tokens`, lalu jalankan migration itu. Di `config/sanctum.php`, `expiration` = `43200` menit. `bootstrap/app.php` memuat `routes/api.php`.

Model `User` memakai `HasApiTokens` dan `protected string $guard_name = 'web'` supaya izin Spatie yang sudah ada tetap terbaca.

## Rute

Prefix `/api/v1`. Respons sukses membungkus isi di `data`, kecuali login yang juga menaruh token di akar.

| Metode | Path | Auth |
| --- | --- | --- |
| POST | `/api/v1/auth/login` | publik |
| POST | `/api/v1/auth/register` | publik, throttle `register` |
| POST | `/api/v1/auth/logout` | token |
| GET | `/api/v1/me` | token |
| GET | `/api/v1/onboarding` | token, owner |
| POST | `/api/v1/onboarding/steps/{step}` | token, owner |
| POST | `/api/v1/onboarding/steps/{step}/skip` | token, owner |
| POST | `/api/v1/onboarding/back` | token, owner |
| POST | `/api/v1/onboarding/complete` | token, owner |

`{step}` salah dari `OnboardingCatalog::STEPS` menghasilkan 404.

## Register

Publik. Body JSON memakai `RegisterRequest` web: `name` (nama klinik), `owner_name`, `owner_email`, `password`, `password_confirmation`; `package_id` opsional (bila kosong memakai paket trial default); `device_name` opsional. Subdomain digenerate dari nama klinik (sama web).

Throttle `register` (5/menit per IP). Memanggil `ClinicProvisioningService::provision` (tenant `trial`, mulai trial). Catat activity `registered`. Respons sama bentuk login: `token`, `token_type`, `expires_at`, `data` (`MobileUserResource`), plus `message`. Tidak ada endpoint daftar paket. Setelah sukses klien lanjut onboarding owner.

## Login

Body JSON: `email` (wajib), `password` (wajib), `device_name` (opsional, string, maks 255). Nama token default `mobile` bila field kosong.

Urutan:

1. Throttle memakai kunci yang sama dengan web: `Str::transliterate(Str::lower(email).'|'.$ip)`, batas 5.
2. Cari user by email dan `Hash::check`. Gagal mencatat `login_failed` dan menjawab 422 tanpa membedakan email terdaftar atau tidak.
3. Tolak akun `inactive` (422 `account_inactive`) dan role `super-admin-saas` (403 `platform_admin_web_only`) sebelum token terbit.
4. Hapus token lama user dengan nama perangkat yang sama, lalu buat token baru ability `mobile` dan `expires_at` 30 hari.
5. Cabang pada login memakai header `X-Branch-Id` bila diisi. Header yang diisi tetapi ditolak menjawab 403 dan token baru dihapus. Header kosong memakai `branch_id` bila sah. Bila `branch_id` tidak sah, token tetap terbit dengan `active_branch` null dan daftar `branches`.
6. Catat `login` di activity log, modul `auth`, dengan user itu sebagai pelaku. Password tidak masuk log.

Logout menghapus token permintaan saat ini dan mencatat `logout`.

## Profil

`GET /api/v1/me` dan bagian `data` pada login:

```json
{
  "id": 1,
  "name": "Nama",
  "email": "user@klinik.test",
  "status": "active",
  "role": "owner",
  "roles": ["owner"],
  "permissions": ["dashboard.view"],
  "tenant": {
    "id": 1,
    "name": "Klinik",
    "status": "trial",
    "needs_onboarding": true,
    "onboarding_step": "template",
    "onboarding_step_number": 1,
    "onboarding_progress": 10
  },
  "branches": [{ "id": 1, "name": "Utama", "is_active": true }],
  "active_branch": { "id": 1, "name": "Utama", "is_active": true }
}
```

`role` memakai `User::primaryRole()`. `permissions` adalah nama izin user, terurut. `branches` hanya cabang `is_active` milik tenant yang boleh diakses. Owner dengan `branch.manage` melihat semua cabang aktif tenant. User lain melihat irisan `assignedBranchIds()` dengan cabang aktif. `queue_monitor_token` tidak dikirim. `active_branch` boleh `null` bila cabang belum ada.

Login sukses:

```json
{
  "token": "1|plain",
  "token_type": "Bearer",
  "expires_at": "2026-12-14T09:00:00+07:00",
  "data": {}
}
```

`data` pada login adalah objek profil yang sama dengan `GET /me`.

## Cabang aktif

Middleware `ResolveActiveBranch` pada request bertoken, kecuali `GET /me` dan seluruh rute onboarding tetap jalan bila cabang belum ada.

1. Header `X-Branch-Id` terisi menjadi kandidat. Header kosong memakai `users.branch_id` bila terisi.
2. Kandidat harus milik `tenant_id` user, lolos `canAccessBranch`, dan `is_active`. Gagal karena tenant atau izin: 403 `branch_forbidden`. Gagal karena nonaktif: 403 `branch_inactive`. Tidak ada penggantian diam-diam.
3. Header dan `branch_id` keduanya kosong: pakai cabang aktif pertama yang boleh diakses, urut `id`.
4. Tidak ada cabang yang lolos pada rute yang mewajibkan cabang: 403 `branch_required`. `GET /me` dan onboarding mengizinkan `active_branch` null.
5. User tenant tanpa `tenant_id`: 403 `tenant_required`.

Hasil menempel di request sebagai `active_branch`.

## Onboarding

Hanya owner dengan `tenant_id`. Selain itu 403 `owner_only`. Mutasi setelah `needsOnboarding()` false menjawab 422 `onboarding_completed`. `GET` setelah selesai tetap mengembalikan state dengan `needs_onboarding: false`.

Langkah sama dengan web: `template`, `roles`, `profile`, `branch`, `doctors`, `staff`, `services`, `medicines`, `integrations`, `review`. Yang boleh dilewati: `doctors`, `staff`, `services`, `medicines`, `integrations`.

Controller memanggil `OnboardingService` dan FormRequest yang sudah ada. Langkah `profile` menerima `multipart/form-data` untuk field `logo`. Langkah lain JSON.

Setiap `GET` dan mutasi sukses mengembalikan state:

- `needs_onboarding`, `step`, `step_number`, `total_steps` (10), `progress`, `optional`
- `templates`: `value`, `label`, `description` dari `OnboardingTemplate`
- `selected_roles`, `recommended_roles`, `other_roles` (value dan label)
- profil klinik saat ini dan `logo_url`
- cabang pertama bila ada (`id`, `name`, `address`, `phone`, `is_active`)
- `summary` dari `OnboardingService::summary()`
- `medicine_template_count`
- integrasi hanya `satusehat_configured` dan `bpjs_configured`

Rahasia kredensial tidak dikembalikan. `complete` memakai aturan service yang ada: alamat klinik terisi dan minimal satu cabang.

## Akses lain

Middleware `EnsureMobileAccess` setelah token: user harus `active`; Super Admin SaaS yang masih memegang token ditolak 403 `platform_admin_web_only`. Middleware ini tidak memblokir onboarding.

Tenant `suspended` boleh `GET /me`, `GET /onboarding`, dan logout. `POST` onboarding menjawab 403 `tenant_suspended` dengan pesan yang sama seperti web: langganan habis atau ditangguhkan.

## Error

Validasi field memakai 422 bawaan Laravel. Penolakan domain:

```json
{ "message": "...", "code": "owner_only" }
```

| Situasi | HTTP | code |
| --- | --- | --- |
| Kredensial salah | 422 | — |
| Akun nonaktif saat login | 422 | `account_inactive` |
| Lebih dari 5 gagal login | 429 | — |
| Super Admin SaaS | 403 | `platform_admin_web_only` |
| Token kosong, salah, atau kedaluwarsa | 401 | — |
| Token masih ada, akun sudah nonaktif | 403 | `account_inactive` |
| Bukan owner memanggil onboarding | 403 | `owner_only` |
| Mutasi onboarding setelah selesai | 422 | `onboarding_completed` |
| Lewati langkah wajib, atau selesai tanpa alamat dan cabang | 422 | pesan validasi service |
| Header cabang terlarang | 403 | `branch_forbidden` |
| Header cabang nonaktif | 403 | `branch_inactive` |
| Rute wajib cabang, tidak ada cabang aktif | 403 | `branch_required` |
| Tulisan saat tenant suspended | 403 | `tenant_suspended` |
| User tenant tanpa `tenant_id` | 403 | `tenant_required` |

## Komponen

- `routes/api.php`
- `App\Http\Controllers\Api\AuthController`
- `App\Http\Controllers\Api\MeController`
- `App\Http\Controllers\Api\OnboardingController`
- `App\Services\Mobile\MobileAuthService`
- `App\Http\Middleware\EnsureMobileAccess`
- `App\Http\Middleware\ResolveActiveBranch`
- `App\Http\Resources\MobileUserResource`
- `App\Http\Resources\OnboardingStateResource`
- alias middleware di `bootstrap/app.php`

## Verifikasi manual

- Login role tenant mengembalikan token dan profil. Password salah 422. Akun nonaktif 422 `account_inactive`. Super Admin SaaS 403 `platform_admin_web_only`.
- Lima gagal login mengunci percobaan berikut (429), termasuk form web dari IP yang sama.
- `GET /me` memakai Bearer. Setelah logout, token itu 401.
- Login ulang dengan `device_name` yang sama mengganti token lama nama itu.
- `X-Branch-Id` cabang sendiri diterima. Cabang tenant lain atau di luar penugasan `branch_forbidden`. Cabang nonaktif `branch_inactive`. Tanpa header, cabang mengikuti `branch_id` bila masih aktif.
- Owner yang belum selesai onboarding bisa login dan menjalankan sepuluh langkah sampai `complete`, termasuk logo multipart. Lewati langkah wajib ditolak. Bukan owner mendapat `owner_only`. Respons tidak memuat rahasia integrasi.
- Tenant `suspended` bisa `GET /me` dan logout. `POST` onboarding ditolak `tenant_suspended`.
- Izin di `/me` sama dengan role yang sama di web.
