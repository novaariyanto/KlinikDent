# API Dashboard Ringkasan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyediakan `GET /api/v1/dashboard` yang mengembalikan kartu KPI, tautan, note, dan checklist setup owner, difilter cabang aktif.

**Architecture:** Thin `Api\DashboardController` memanggil `DashboardMetricsService` (diperluas agar menerima `branchId`) plus `WorkspaceDetector` dan `SetupChecklistService`. Respons membuang URL web dari kartu; tautan memakai nama route Laravel.

**Tech Stack:** Laravel 12, Sanctum, Spatie permissions (`dashboard.view`), service laporan yang sudah ada.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-11-14-mobile-api-dashboard-design.md`
- Endpoint di API operator yang sama; jangan buat guard/app terpisah
- Izin: `dashboard.view` (sama web)
- `mobile.branch` wajib; tidak memakai `mobile.writes`
- Jangan menambah atau menjalankan Pest/PHPUnit
- Jangan commit kecuali user meminta
- Jangan merusak rute fondasi, meja klinis, farmasi, atau billing
- Jangan menampilkan password, token, atau rahasia

## File map

| File | Tanggung jawab |
| --- | --- |
| `app/Support/Reports/DashboardMetricsService.php` | Terima `branchId`; cache key; tautan sertakan `route` |
| `app/Http/Controllers/Api/DashboardController.php` | JSON dashboard mobile |
| `routes/api.php` | Daftarkan `GET dashboard` |

---

### Task 1: Perluas DashboardMetricsService

**Files:**
- Modify: `app/Support/Reports/DashboardMetricsService.php`

**Interfaces:**
- Consumes: `User::applyBranchLimit`, `ClinicReportService`, `FinanceReportService`, `BillingService` (sudah dipakai)
- Produces: `forUser(User $user, ?RoleName $role = null, ?int $branchId = null): array` dengan bentuk cards/links/note; tiap link `{ label, route, href }`

- [x] **Step 1: Ubah signature dan cache**

Ganti awal `forUser` agar memakai parameter branch (default null) dan memasukkan branch ke cache key:

```php
public function forUser(User $user, ?RoleName $role = null, ?int $branchId = null): array
{
    $roles = $user->getRoleNames()
        ->map(fn (string $name) => RoleName::tryFrom($name))
        ->filter()
        ->unique()
        ->values();

    if ($role instanceof RoleName) {
        $roles = collect([$role]);
    }

    $fingerprint = $roles->map(fn (RoleName $assigned) => $assigned->value)->sort()->implode('|');
    $branchFingerprint = implode(',', $user->restrictedBranchIds() ?? ['all']);
    $preferred = $branchId ?? 'none';

    if (app()->environment('testing')) {
        return $this->mergeForRoles($user, $roles, $branchId);
    }

    $key = implode(':', ['dashboard', $user->id, $fingerprint ?: 'none', $branchFingerprint, $preferred]);

    return Cache::remember($key, 120, function () use ($user, $roles, $branchId) {
        return $this->mergeForRoles($user, $roles, $branchId);
    });
}
```

Hapus baris `$branchId = null;` yang menimpa argumen.

- [x] **Step 2: Sertakan nama route pada links**

Ubah `links()`:

```php
/**
 * @param  array<string, string>  $items
 * @return list<array{label: string, route: string, href: string}>
 */
protected function links(array $items): array
{
    $links = [];

    foreach ($items as $label => $route) {
        if (Route::has($route)) {
            $links[] = [
                'label' => $label,
                'route' => $route,
                'href' => route($route),
            ];
        }
    }

    return $links;
}
```

Blade web tetap memakai `$link['href']` — tidak perlu diubah.

- [x] **Step 3: Verifikasi cepat service**

Jalankan:

```bash
php artisan tinker --execute="echo method_exists(app(App\Support\Reports\DashboardMetricsService::class), 'forUser') ? 'ok' : 'fail';"
```

Expected: `ok`. Buka dashboard web sekali (manual) bila memungkinkan; kartu dan tautan masih tampil.

---

### Task 2: Controller API dan rute

**Files:**
- Create: `app/Http/Controllers/Api/DashboardController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `DashboardMetricsService::forUser`, `WorkspaceDetector::forUser`, `SetupChecklistService::forOwner`, `request->attributes->get('active_branch')`
- Produces: `GET /api/v1/dashboard` → `{ data: { title, description, workspace, cards, links, note, setup_checklist, branch } }`

- [x] **Step 1: Buat controller**

Buat `app/Http/Controllers/Api/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Enums\Workspace;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Support\Access\WorkspaceDetector;
use App\Support\Onboarding\SetupChecklistService;
use App\Support\Reports\DashboardMetricsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardMetricsService $metrics,
        protected SetupChecklistService $checklist,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->can('dashboard.view')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $active = $request->attributes->get('active_branch');
        if (! $active instanceof Branch) {
            abort(403); // middleware mobile.branch seharusnya sudah menangani branch_required
        }

        $workspace = WorkspaceDetector::forUser($user);
        $roles = $user->getRoleNames()
            ->map(fn (string $name) => RoleName::tryFrom($name))
            ->filter()
            ->unique()
            ->values();
        $primary = $roles->count() === 1 ? $roles->first() : null;

        if ($workspace === Workspace::Operational) {
            $title = RoleName::ClinicOps->dashboardTitle();
            $payload = $this->metrics->forUser($user, RoleName::ClinicOps, (int) $active->id);
            $description = 'Selamat datang. '.RoleName::ClinicOps->dashboardDescription();
        } else {
            $title = $primary?->dashboardTitle() ?? 'Dashboard';
            $payload = $this->metrics->forUser($user, null, (int) $active->id);
            $description = $primary
                ? 'Selamat datang. '.$primary->dashboardDescription()
                : 'Selamat datang. Ringkasan gabungan untuk '.$roles->map(fn (RoleName $role) => $role->label())->join(', ').'.';
        }

        $checklist = $this->checklist->forOwner($user);
        if (is_array($checklist)) {
            $checklist['items'] = array_map(function (array $item) {
                return [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'done' => (bool) $item['done'],
                    'optional' => (bool) $item['optional'],
                ];
            }, $checklist['items']);
        }

        return response()->json([
            'data' => [
                'title' => $title,
                'description' => $description,
                'workspace' => $workspace->value,
                'cards' => array_map(fn (array $card) => [
                    'label' => $card['label'],
                    'value' => $card['value'],
                ], $payload['cards']),
                'links' => array_map(fn (array $link) => [
                    'label' => $link['label'],
                    'route' => $link['route'],
                ], $payload['links']),
                'note' => $payload['note'],
                'setup_checklist' => $checklist,
                'branch' => [
                    'id' => $active->id,
                    'name' => $active->name,
                ],
            ],
        ]);
    }
}
```

- [x] **Step 2: Daftarkan rute**

Di `routes/api.php`, tambahkan import:

```php
use App\Http\Controllers\Api\DashboardController;
```

Di dalam grup `Route::middleware('mobile.branch')->group(...)`, dekat rute baca lain (mis. setelah `me` tidak — harus di dalam `mobile.branch`), tambahkan:

```php
Route::get('dashboard', [DashboardController::class, 'show']);
```

Letakkan bersama GET pasien/visits (bukan di dalam `mobile.writes`).

- [x] **Step 3: Verifikasi rute**

```bash
php artisan route:list --path=api/v1/dashboard
```

Expected: satu baris `GET|HEAD api/v1/dashboard` → `Api\DashboardController@show`.

- [x] **Step 4: Verifikasi HTTP manual**

Dengan token Sanctum + header `X-Branch-Id` cabang sah:

1. User owner dengan `dashboard.view`: 200, `data.cards` terisi, `data.setup_checklist` objek, `data.branch.id` = header, kartu/link tanpa `href`.
2. User tanpa `dashboard.view`: 403 `forbidden`.
3. Tanpa cabang aktif: 403 `branch_required` (middleware).
4. Tenant suspended: GET tetap 200.

Jangan commit kecuali diminta.

---

## Self-review

- Spec coverage: rute, respons fields, branch filter, checklist tanpa href, error codes, komponen — Task 1–2.
- Tidak ada hub `/reports/*` (sesuai cakupan A).
- Web dashboard: `forUser($user)` tanpa branch tetap null; blade tetap pakai `href`.
