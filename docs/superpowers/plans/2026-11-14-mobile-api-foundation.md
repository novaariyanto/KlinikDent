# Fondasi API Mobile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyediakan API JSON `/api/v1` untuk login Sanctum, profil, cabang aktif, dan onboarding owner.

**Architecture:** Token Sanctum pada model `User` yang sudah ada. Login memakai `Hash::check` agar session web tidak terbentuk. Middleware memisahkan akun aktif, cabang, dan blok tulisan tenant yang ditangguhkan. Onboarding memanggil `OnboardingService` dan FormRequest web yang sudah ada.

**Tech Stack:** Laravel 12, Laravel Sanctum, Spatie Permission guard `web`, activity log proyek.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-11-14-mobile-api-foundation-design.md`
- Semua role tenant boleh login. Super Admin SaaS tidak menerima token.
- Token ability `mobile`, berlaku 30 hari. `config/sanctum.php` `expiration` = `43200`.
- Login tidak memakai `Auth::attempt`. Password dicek dengan `Hash::check`.
- Header `X-Branch-Id`. Header kosong memakai `users.branch_id` bila terisi dan sah. Keduanya kosong: cabang aktif pertama yang boleh diakses. Kandidat tidak sah ditolak, tidak diganti diam-diam.
- Onboarding owner memakai `OnboardingService` dan FormRequest yang sudah ada. Role lain tidak diarahkan ke wizard.
- Verifikasi manual lewat HTTP. Jangan menambah atau menjalankan Pest/PHPUnit.
- Jangan commit kecuali user meminta.
- Jangan browser automation kecuali diminta.
- Jangan menampilkan password, token penuh, atau rahasia integrasi di log chat.

## File map

| File | Tanggung jawab |
| --- | --- |
| `composer.json` / `composer.lock` | Paket `laravel/sanctum` |
| `config/sanctum.php` | Masa berlaku token 43200 menit |
| `database/migrations/*_create_personal_access_tokens_table.php` | Tabel token Sanctum |
| `app/Models/User.php` | `HasApiTokens`, `$guard_name = 'web'` |
| `app/Exceptions/MobileApiException.php` | JSON `{message, code, errors?}` |
| `bootstrap/app.php` | Route API, render JSON, alias middleware |
| `routes/api.php` | Prefix `v1` |
| `app/Support/Mobile/ActiveBranchResolver.php` | Memilih atau menolak cabang |
| `app/Http/Middleware/EnsureMobileAccess.php` | Akun aktif, bukan platform, punya tenant |
| `app/Http/Middleware/ResolveActiveBranch.php` | Menempelkan `active_branch` |
| `app/Http/Middleware/EnsureMobileWritesAllowed.php` | Menolak tulisan saat tenant `suspended` |
| `app/Services/Mobile/MobileAuthService.php` | Login, throttle, token |
| `app/Http/Controllers/Api/AuthController.php` | Login dan logout |
| `app/Http/Controllers/Api/MeController.php` | Profil |
| `app/Http/Resources/MobileUserResource.php` | Bentuk profil |
| `app/Http/Controllers/Api/OnboardingController.php` | Wizard owner |
| `app/Http/Resources/OnboardingStateResource.php` | State wizard |

---

### Task 1: Sanctum, user token, dan error JSON

**Files:**
- Modify: `composer.json`, `composer.lock`, `app/Models/User.php`, `bootstrap/app.php`
- Create: `config/sanctum.php`, migration `personal_access_tokens`, `app/Exceptions/MobileApiException.php`, `routes/api.php`

**Interfaces:**
- Consumes: tidak ada
- Produces: `App\Exceptions\MobileApiException::__construct(string $message, string $errorCode, int $status = 403, ?array $errors = null)`. Properti publik `errorCode`, `status`, `errors`. Model `User` memakai `HasApiTokens`.

- [ ] **Step 1: Pasang Sanctum dan terbitkan berkas paket**

Jalankan dari root proyek:

```powershell
composer require laravel/sanctum
php artisan vendor:publish --tag=sanctum-config --tag=sanctum-migrations --no-interaction
php artisan migrate --no-interaction
```

Expected: `personal_access_tokens` dibuat. Jika migrate ingin menjalankan migration lain yang belum ada, berhenti dan laporkan. Jangan mengubah migration lama.

- [ ] **Step 2: Set masa berlaku token**

Di `config/sanctum.php`, ganti nilai `expiration`:

```php
'expiration' => 43200,
```

Biarkan kunci lain pada bawaan paket. Jangan mengaktifkan middleware SPA stateful.

- [ ] **Step 3: Tulis exception API**

Buat `app/Exceptions/MobileApiException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class MobileApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 403,
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }
}
```

- [ ] **Step 4: Sambungkan route API dan render JSON**

Di `bootstrap/app.php`, tambah import:

```php
use App\Exceptions\MobileApiException;
use Illuminate\Http\Request;
```

Ubah `withRouting` agar memuat API. Pertahankan `web`, `commands`, dan `health`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

Di dalam `withExceptions`, sebelum `reportable` yang sudah ada, tambahkan:

```php
$exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
    return $request->is('api/*') || $request->expectsJson();
});

$exceptions->render(function (MobileApiException $e, Request $request) {
    if (! $request->is('api/*') && ! $request->expectsJson()) {
        return null;
    }

    $payload = [
        'message' => $e->getMessage(),
        'code' => $e->errorCode,
    ];

    if ($e->errors !== null) {
        $payload['errors'] = $e->errors;
    }

    return response()->json($payload, $e->status);
});
```

Buat `routes/api.php` sementara:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    //
});
```

- [ ] **Step 5: Aktifkan token pada User**

Di `app/Models/User.php`, tambah import `Laravel\Sanctum\HasApiTokens`. Pada daftar trait class, sisipkan `HasApiTokens` di samping trait yang sudah ada. Di dalam class, sebelum `$fillable`, tambahkan:

```php
protected string $guard_name = 'web';
```

Jangan mengubah `$fillable`, relasi, atau metode cabang.

- [ ] **Step 6: Verifikasi paket**

```powershell
php artisan tinker --execute="var_export(Schema::hasTable('personal_access_tokens'));"
php artisan route:list --path=api
```

Expected: tinker mencetak `true`. `route:list` memuat prefix `api` tanpa error kelas. Jangan membuat user percobaan.

---

### Task 2: Penyelesai cabang dan middleware akses

**Files:**
- Create: `app/Support/Mobile/ActiveBranchResolver.php`, `app/Http/Middleware/EnsureMobileAccess.php`, `app/Http/Middleware/ResolveActiveBranch.php`, `app/Http/Middleware/EnsureMobileWritesAllowed.php`
- Modify: `bootstrap/app.php` alias middleware

**Interfaces:**
- Consumes: `MobileApiException` dari Task 1. `User::canAccessBranch()`, `User::restrictedBranchIds()`, `User::isActive()`, `User::isPlatformAdmin()`, `TenantStatus::Suspended`.
- Produces:
  - `ActiveBranchResolver::resolve(User $user, ?int $requestedId, bool $required): ?Branch`
  - `ActiveBranchResolver::accessibleBranches(User $user): Collection<int, Branch>`
  - Alias `mobile.access`, `mobile.branch`, `mobile.writes`
  - `ResolveActiveBranch` menulis atribut request `active_branch` (`Branch` atau `null`)

- [ ] **Step 1: Tulis penyelesai cabang**

Buat `app/Support/Mobile/ActiveBranchResolver.php`:

```php
<?php

namespace App\Support\Mobile;

use App\Exceptions\MobileApiException;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ActiveBranchResolver
{
    public function resolve(User $user, ?int $requestedId, bool $required): ?Branch
    {
        if (! $user->tenant_id) {
            throw new MobileApiException('Klinik tidak ditemukan.', 'tenant_required', 403);
        }

        if ($requestedId === null && ! $user->branch_id) {
            $branch = $this->accessibleBranches($user)->first();

            if (! $branch && $required) {
                throw new MobileApiException('Pilih cabang aktif.', 'branch_required', 403);
            }

            return $branch;
        }

        $candidateId = $requestedId ?? (int) $user->branch_id;

        if ($candidateId < 1) {
            throw new MobileApiException('Cabang tidak dapat diakses.', 'branch_forbidden', 403);
        }

        $branch = Branch::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($candidateId)
            ->first();

        if (! $branch || ! $user->canAccessBranch((int) $branch->id)) {
            throw new MobileApiException('Cabang tidak dapat diakses.', 'branch_forbidden', 403);
        }

        if (! $branch->is_active) {
            throw new MobileApiException('Cabang tidak aktif.', 'branch_inactive', 403);
        }

        return $branch;
    }

    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(User $user): Collection
    {
        return $this->allowedQuery($user)->get(['id', 'name', 'is_active']);
    }

    /**
     * @return Builder<Branch>
     */
    protected function allowedQuery(User $user): Builder
    {
        $query = Branch::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('id');

        $restricted = $user->restrictedBranchIds();

        if ($restricted !== null) {
            $query->whereIn('id', $restricted);
        }

        return $query;
    }
}
```

`requestedId === null` berarti header tidak dikirim. `0` berarti header ada tetapi bukan angka: itu `branch_forbidden`.

- [ ] **Step 2: Tulis tiga middleware**

`app/Http/Middleware/EnsureMobileAccess.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Exceptions\MobileApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->isActive()) {
            throw new MobileApiException(
                'This account is inactive. Please contact the administrator.',
                'account_inactive',
                403,
            );
        }

        if ($user->isPlatformAdmin()) {
            throw new MobileApiException(
                'Akun platform hanya dapat masuk lewat web.',
                'platform_admin_web_only',
                403,
            );
        }

        if (! $user->tenant_id) {
            throw new MobileApiException('Klinik tidak ditemukan.', 'tenant_required', 403);
        }

        return $next($request);
    }
}
```

`app/Http/Middleware/ResolveActiveBranch.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Mobile\ActiveBranchResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveBranch
{
    public function __construct(protected ActiveBranchResolver $branches) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $request->attributes->set(
                'active_branch',
                $this->branches->resolve($user, $this->requestedId($request), $mode !== 'optional')
            );
        }

        return $next($request);
    }

    protected function requestedId(Request $request): ?int
    {
        $raw = $request->header('X-Branch-Id');

        if ($raw === null || $raw === '') {
            return null;
        }

        return ctype_digit((string) $raw) ? (int) $raw : 0;
    }
}
```

`app/Http/Middleware/EnsureMobileWritesAllowed.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Exceptions\MobileApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileWritesAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $tenant = $request->user()?->tenant;

        if ($tenant && $tenant->status === TenantStatus::Suspended) {
            throw new MobileApiException(
                'Langganan klinik habis atau ditangguhkan. Hubungi pengelola platform.',
                'tenant_suspended',
                403,
            );
        }

        return $next($request);
    }
}
```

- [ ] **Step 3: Daftarkan alias**

Di array alias `bootstrap/app.php`, tambahkan tanpa menghapus alias yang sudah ada:

```php
'mobile.access' => \App\Http\Middleware\EnsureMobileAccess::class,
'mobile.branch' => \App\Http\Middleware\ResolveActiveBranch::class,
'mobile.writes' => \App\Http\Middleware\EnsureMobileWritesAllowed::class,
```

- [ ] **Step 4: Verifikasi class termuat**

```powershell
php artisan tinker --execute="var_export(class_exists(App\Support\Mobile\ActiveBranchResolver::class));"
```

Expected: `true`. Belum ada request cabang sampai Task 3.

---

### Task 3: Login, logout, dan profil

**Files:**
- Create: `app/Services/Mobile/MobileAuthService.php`, `app/Http/Controllers/Api/AuthController.php`, `app/Http/Controllers/Api/MeController.php`, `app/Http/Resources/MobileUserResource.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `ActiveBranchResolver::resolve(User, ?int, bool): ?Branch`, `ActiveBranchResolver::accessibleBranches(User): Collection`, atribut request `active_branch`, `MobileApiException`, `activity_log()`, `OnboardingService::currentStepKey(Tenant): string`, `OnboardingCatalog::stepNumber(string): int`, `Tenant::onboardingProgressPercent(): int`.
- Produces: `MobileAuthService::login(string $email, string $password, ?string $deviceName, string $ip): array{token: string, expires_at: ?string, user: User, branch: ?Branch}`. Respons login `{token, token_type, expires_at, data}`. `GET /api/v1/me` membungkus profil di `data`.

- [ ] **Step 1: Tulis resource profil**

Buat `app/Http/Resources/MobileUserResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\Branch;
use App\Models\User;
use App\Support\Mobile\ActiveBranchResolver;
use App\Support\Onboarding\OnboardingCatalog;
use App\Support\Onboarding\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileUserResource extends JsonResource
{
    public function __construct($resource, protected ?Branch $activeBranch = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $tenant = $user->tenant;
        $active = $this->activeBranch ?? $request->attributes->get('active_branch');
        $step = $tenant
            ? app(OnboardingService::class)->currentStepKey($tenant)
            : null;

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'role' => $user->primaryRole()?->value,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'tenant' => $tenant ? [
                'id' => (int) $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status->value,
                'needs_onboarding' => $tenant->needsOnboarding(),
                'onboarding_step' => $step,
                'onboarding_step_number' => $step ? OnboardingCatalog::stepNumber($step) : null,
                'onboarding_progress' => $tenant->onboardingProgressPercent(),
            ] : null,
            'branches' => app(ActiveBranchResolver::class)
                ->accessibleBranches($user)
                ->map(fn (Branch $branch) => $this->branchPayload($branch))
                ->values()
                ->all(),
            'active_branch' => $active instanceof Branch ? $this->branchPayload($active) : null,
        ];
    }

    /**
     * @return array{id: int, name: string, is_active: bool}
     */
    protected function branchPayload(Branch $branch): array
    {
        return [
            'id' => (int) $branch->id,
            'name' => $branch->name,
            'is_active' => (bool) $branch->is_active,
        ];
    }
}
```

- [ ] **Step 2: Tulis layanan login**

Buat `app/Services/Mobile/MobileAuthService.php`:

```php
<?php

namespace App\Services\Mobile;

use App\Enums\UserStatus;
use App\Exceptions\MobileApiException;
use App\Models\Branch;
use App\Models\User;
use App\Support\Mobile\ActiveBranchResolver;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileAuthService
{
    public function __construct(protected ActiveBranchResolver $branches) {}

    /**
     * @return array{token: string, expires_at: ?string, user: User, branch: ?Branch}
     */
    public function login(string $email, string $password, ?string $deviceName, string $ip): array
    {
        $this->ensureIsNotRateLimited($email, $ip);

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($this->throttleKey($email, $ip));

            activity_log(
                'login_failed',
                $user,
                ['email' => $email],
                'Failed login attempt for '.$email,
                'auth'
            );

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw new MobileApiException(
                'This account is inactive. Please contact the administrator.',
                'account_inactive',
                422,
                ['email' => ['This account is inactive. Please contact the administrator.']]
            );
        }

        if ($user->isPlatformAdmin()) {
            throw new MobileApiException(
                'Akun platform hanya dapat masuk lewat web.',
                'platform_admin_web_only',
                403,
            );
        }

        $name = trim((string) $deviceName);
        $name = $name === '' ? 'mobile' : mb_substr($name, 0, 255);

        $user->tokens()->where('name', $name)->delete();
        $issued = $user->createToken($name, ['mobile'], now()->addDays(30));

        try {
            $branch = $this->branches->resolve($user, null, false);
        } catch (MobileApiException $exception) {
            $issued->accessToken->delete();
            throw $exception;
        }

        RateLimiter::clear($this->throttleKey($email, $ip));

        activity_log('login', $user, [], 'Signed in', 'auth');

        return [
            'token' => $issued->plainTextToken,
            'expires_at' => $issued->accessToken->expires_at?->toIso8601String(),
            'user' => $user,
            'branch' => $branch,
        ];
    }

    protected function ensureIsNotRateLimited(string $email, string $ip): void
    {
        $key = $this->throttleKey($email, $ip);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($key);

        abort(response()->json([
            'message' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ], 429));
    }

    protected function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}
```

Penghitung ini sama dengan `LoginRequest`, jadi gagal di API mengunci form web dari IP yang sama. Form web tetap menjawab 422 karena `LoginRequest` melempar `ValidationException`. API menjawab 429. Jangan mengubah `LoginRequest`.

- [ ] **Step 3: Tulis controller auth dan profil**

`app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileUserResource;
use App\Services\Mobile\MobileAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function store(Request $request, MobileAuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $result = $auth->login(
            $data['email'],
            $data['password'],
            $data['device_name'] ?? null,
            (string) $request->ip(),
        );

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'data' => (new MobileUserResource($result['user'], $result['branch']))->toArray($request),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()?->currentAccessToken()?->delete();

        activity_log('logout', $user, [], 'Signed out', 'auth');

        return response()->json(['message' => 'Signed out']);
    }
}
```

`app/Http/Controllers/Api/MeController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileUserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): MobileUserResource
    {
        return new MobileUserResource($request->user());
    }
}
```

- [ ] **Step 4: Daftarkan rute auth dan profil**

Ganti isi `routes/api.php` dengan:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'store']);

    Route::middleware(['auth:sanctum', 'mobile.access'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'destroy']);

        Route::middleware('mobile.branch:optional')->group(function () {
            Route::get('me', [MeController::class, 'show']);
        });
    });
});
```

Logout tidak memakai `mobile.writes` atau `mobile.branch`, supaya tenant `suspended` dan user tanpa cabang tetap bisa keluar.

- [ ] **Step 5: Verifikasi HTTP**

Pastikan aplikasi dapat dijangkau di URL lokal yang biasa dipakai. Ganti email dan password dengan akun tenant aktif. Jangan tempel token atau password ke ringkasan chat.

```powershell
php artisan route:list --path=api/v1
curl.exe -s -D - -o NUL -H "Accept: application/json" -H "Content-Type: application/json" -d "{\"email\":\"bukan-email\",\"password\":\"\"}" http://URL/api/v1/auth/login
```

Expected:

- `route:list` memuat `POST api/v1/auth/login`, `POST api/v1/auth/logout`, `GET api/v1/me`.
- Body tidak valid menjawab 422.
- Password salah menjawab 422 tanpa field `code`, pesan sama seperti web.
- Akun nonaktif menjawab 422 dengan `code` `account_inactive`.
- Super Admin SaaS menjawab 403 `platform_admin_web_only` dan tidak ada baris token baru untuk user itu.
- Login tenant menjawab `token`, `token_type` = `Bearer`, `expires_at`, dan `data` berisi `role`, `permissions`, `branches`, `active_branch`.
- Login ulang `device_name` yang sama membuat token lama bernama itu terhapus. Cek `personal_access_tokens` hanya satu baris untuk nama itu.
- `GET /api/v1/me` dengan `Authorization: Bearer` menjawab profil. Setelah logout, token yang sama menjawab 401.
- `X-Branch-Id` cabang sendiri diterima. Id tenant lain atau di luar penugasan menjawab 403 `branch_forbidden`. Cabang nonaktif menjawab 403 `branch_inactive`. Header kosong memakai `branch_id` bila sah.
- Enam permintaan gagal berturut-turut dari IP yang sama membuat permintaan API ke-6 menjawab 429. Form web dari IP yang sama juga terkunci.

---

### Task 4: Onboarding owner

**Files:**
- Create: `app/Http/Controllers/Api/OnboardingController.php`, `app/Http/Resources/OnboardingStateResource.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `OnboardingService` (`currentStepKey`, `saveTemplate`, `saveRoles`, `saveProfile`, `saveBranch`, `saveDoctors`, `saveStaff`, `saveServices`, `saveMedicines`, `saveIntegrations`, `skipStep`, `goBack`, `complete`, `summary`), FormRequest onboarding yang sudah ada, `OnboardingCatalog`, `OnboardingTemplate`, `ClinicSettings`, `mobile.branch:optional`, `mobile.writes`.
- Produces: state onboarding di dalam `data` untuk setiap GET dan mutasi sukses.

`AuthorizesOwnerOnboarding` menolak owner yang sudah selesai dengan 403. Controller harus melempar 422 `onboarding_completed` sebelum `app(FormRequest::class)` dipanggil. User tanpa `tenant_id` sudah ditolak `tenant_required` oleh `mobile.access` dan tidak mencapai controller.

- [ ] **Step 1: Tulis resource state**

Buat `app/Http/Resources/OnboardingStateResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Enums\OnboardingTemplate;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\ClinicSettings;
use App\Support\Onboarding\OnboardingCatalog;
use App\Support\Onboarding\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $tenant = $user->tenant;
        $onboarding = app(OnboardingService::class);
        $step = $onboarding->currentStepKey($tenant);
        $template = $tenant->onboarding_template ?? OnboardingTemplate::Small;
        $settings = ClinicSettings::current((int) $tenant->id);
        $branch = $tenant->branches()->orderBy('id')->first();
        $summary = $onboarding->summary($tenant);

        return [
            'needs_onboarding' => $tenant->needsOnboarding(),
            'step' => $step,
            'step_number' => OnboardingCatalog::stepNumber($step),
            'total_steps' => OnboardingCatalog::TOTAL_STEPS,
            'progress' => $tenant->onboardingProgressPercent(),
            'optional' => OnboardingCatalog::isOptional($step),
            'templates' => collect(OnboardingTemplate::cases())->map(fn (OnboardingTemplate $item) => [
                'value' => $item->value,
                'label' => $item->label(),
                'description' => $item->description(),
            ])->values()->all(),
            'selected_roles' => $this->roles($tenant->onboarding_selected_roles ?? []),
            'recommended_roles' => $this->roles(OnboardingCatalog::recommendedRoles($template)),
            'other_roles' => $this->roles(OnboardingCatalog::otherRoles($template)),
            'profile' => [
                'clinic_name' => $settings['clinic_name'] ?? '',
                'tagline' => $settings['tagline'] ?? '',
                'rm_prefix' => $settings['rm_prefix'] ?? '',
                'address' => $settings['address'] ?? '',
                'phone' => $settings['phone'] ?? '',
                'email' => $settings['email'] ?? '',
                'npwp' => $settings['npwp'] ?? '',
                'license_number' => $settings['license_number'] ?? '',
            ],
            'logo_url' => ClinicSettings::logoUrl('dark', (int) $tenant->id),
            'branch' => $branch ? [
                'id' => (int) $branch->id,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'is_active' => (bool) $branch->is_active,
            ] : null,
            'summary' => $summary,
            'medicine_template_count' => OnboardingCatalog::medicineTemplateCount(),
            'satusehat_configured' => (bool) ($summary['satusehat'] ?? false),
            'bpjs_configured' => (bool) ($summary['bpjs'] ?? false),
        ];
    }

    /**
     * @param  list<string>  $roles
     * @return list<array{value: string, label: string}>
     */
    protected function roles(array $roles): array
    {
        return collect($roles)
            ->map(function (string $role) {
                $enum = RoleName::tryFrom($role);

                return $enum ? [
                    'value' => $enum->value,
                    'label' => $enum->label(),
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
```

Jangan menambahkan field kredensial SATUSEHAT atau BPJS.

- [ ] **Step 2: Tulis controller onboarding**

Buat `app/Http/Controllers/Api/OnboardingController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Exceptions\MobileApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\ConfirmRolesRequest;
use App\Http\Requests\Onboarding\SaveBranchRequest;
use App\Http\Requests\Onboarding\SaveDoctorsRequest;
use App\Http\Requests\Onboarding\SaveIntegrationsRequest;
use App\Http\Requests\Onboarding\SaveMedicinesRequest;
use App\Http\Requests\Onboarding\SaveProfileRequest;
use App\Http\Requests\Onboarding\SaveServicesRequest;
use App\Http\Requests\Onboarding\SaveStaffRequest;
use App\Http\Requests\Onboarding\SelectTemplateRequest;
use App\Http\Resources\OnboardingStateResource;
use App\Models\User;
use App\Support\Onboarding\OnboardingCatalog;
use App\Support\Onboarding\OnboardingService;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function show(): OnboardingStateResource
    {
        return new OnboardingStateResource($this->owner());
    }

    public function step(string $step, Request $request, OnboardingService $onboarding): OnboardingStateResource
    {
        $user = $this->ownerInProgress();
        $this->knownStep($step);

        if ($step === 'review') {
            abort(404);
        }

        match ($step) {
            'template' => $onboarding->saveTemplate($user, app(SelectTemplateRequest::class)->validated()),
            'roles' => $onboarding->saveRoles($user, app(ConfirmRolesRequest::class)->validated()),
            'profile' => $onboarding->saveProfile(
                $user,
                app(SaveProfileRequest::class)->validated(),
                $request->file('logo')
            ),
            'branch' => $onboarding->saveBranch($user, app(SaveBranchRequest::class)->validated()),
            'doctors' => $onboarding->saveDoctors($user, app(SaveDoctorsRequest::class)->validated()),
            'staff' => $onboarding->saveStaff($user, app(SaveStaffRequest::class)->validated()),
            'services' => $onboarding->saveServices($user, app(SaveServicesRequest::class)->validated()),
            'medicines' => $onboarding->saveMedicines($user, app(SaveMedicinesRequest::class)->validated()),
            'integrations' => $onboarding->saveIntegrations($user, app(SaveIntegrationsRequest::class)->validated()),
            default => abort(404),
        };

        return new OnboardingStateResource($user->fresh());
    }

    public function skip(string $step, OnboardingService $onboarding): OnboardingStateResource
    {
        $user = $this->ownerInProgress();
        $this->knownStep($step);
        $onboarding->skipStep($user, $step);

        return new OnboardingStateResource($user->fresh());
    }

    public function back(OnboardingService $onboarding): OnboardingStateResource
    {
        $user = $this->ownerInProgress();
        $onboarding->goBack($user);

        return new OnboardingStateResource($user->fresh());
    }

    public function complete(OnboardingService $onboarding): OnboardingStateResource
    {
        $user = $this->ownerInProgress();
        $onboarding->complete($user);

        return new OnboardingStateResource($user->fresh());
    }

    protected function owner(): User
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->hasRole(RoleName::Owner) || ! $user->tenant_id) {
            throw new MobileApiException(
                'Hanya owner klinik yang dapat menjalankan onboarding.',
                'owner_only',
                403,
            );
        }

        return $user;
    }

    protected function ownerInProgress(): User
    {
        $user = $this->owner();

        if (! $user->tenant?->needsOnboarding()) {
            throw new MobileApiException(
                'Onboarding klinik sudah selesai.',
                'onboarding_completed',
                422,
            );
        }

        return $user;
    }

    protected function knownStep(string $step): void
    {
        abort_unless(in_array($step, OnboardingCatalog::STEPS, true), 404);
    }
}
```

- [ ] **Step 3: Daftarkan rute onboarding**

Ganti isi `routes/api.php` dengan berkas lengkap ini:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'store']);

    Route::middleware(['auth:sanctum', 'mobile.access'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'destroy']);

        Route::middleware('mobile.branch:optional')->group(function () {
            Route::get('me', [MeController::class, 'show']);
            Route::get('onboarding', [OnboardingController::class, 'show']);

            Route::middleware('mobile.writes')->group(function () {
                Route::post('onboarding/steps/{step}', [OnboardingController::class, 'step']);
                Route::post('onboarding/steps/{step}/skip', [OnboardingController::class, 'skip']);
                Route::post('onboarding/back', [OnboardingController::class, 'back']);
                Route::post('onboarding/complete', [OnboardingController::class, 'complete']);
            });
        });
    });
});
```

- [ ] **Step 4: Verifikasi HTTP onboarding**

Gunakan owner yang `needsOnboarding()` masih true, plus satu user tenant bukan owner. Jangan mencetak rahasia integrasi.

```powershell
php artisan route:list --path=api/v1/onboarding
```

Expected:

- Bukan owner: `GET /api/v1/onboarding` menjawab 403 `owner_only`.
- Owner: `GET` menjawab `needs_onboarding: true`, `total_steps: 10`, `templates`, `recommended_roles`, `profile`, `summary`, `medicine_template_count`. Tidak ada `client_secret`, `secret_key`, atau `user_key`.
- `POST /api/v1/onboarding/steps/bukan-langkah` menjawab 404.
- `POST /api/v1/onboarding/steps/template` body `{"template":"small"}` memajukan langkah.
- `POST /api/v1/onboarding/steps/profile` memakai `multipart/form-data` dengan field teks profil dan file `logo` png di bawah 2 MB. `logo_url` terisi.
- `POST .../steps/template/skip` menjawab 422 karena langkah wajib.
- `POST .../steps/doctors/skip` diterima saat langkah saat ini sudah mencapai doctors.
- `POST /api/v1/onboarding/complete` sebelum alamat dan cabang terisi menjawab 422 dari service.
- Setelah profil, cabang, dan `complete` sah, `GET /api/v1/me` menunjukkan `needs_onboarding: false` dan `active_branch` terisi. `POST` langkah setelah itu menjawab 422 `onboarding_completed`. `GET /api/v1/onboarding` tetap 200.
- Tenant `suspended`: `GET /api/v1/me`, `GET /api/v1/onboarding`, dan logout tetap berhasil. `POST` onboarding menjawab 403 `tenant_suspended`.
- Izin di `/api/v1/me` sama dengan izin role itu di web.

---

## Self-review

- Login, profil, cabang, onboarding, suspended, dan kode error spec tercakup Task 1–4.
- `branch_required` ada di resolver untuk `mode=required`. Rute fondasi ini memakai `optional`, jadi kode itu belum terpanggil request sampai fitur berikutnya mewajibkan cabang.
- User tanpa `tenant_id` menerima `tenant_required` di `mobile.access`, bukan `owner_only`.
