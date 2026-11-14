# API Dashboard Ringkasan (Mobile)

Endpoint baca-saja untuk ringkasan KPI di aplikasi mobile. Fondasi auth ada di `docs/superpowers/specs/2026-11-14-mobile-api-foundation-design.md`. Meja klinis dan farmasi/kasir ada di spek masing-masing. Prompt Flutter ada di `docs/superpowers/specs/2026-11-14-flutter-mobile-prompt.md`. Hub laporan `/reports/*` dan `/finance/reports/*` tidak ikut modul ini.

## Keputusan

- Satu endpoint `GET /api/v1/dashboard` di API operator yang sama, bukan aplikasi terpisah.
- Siapa pun dengan izin `dashboard.view` (sama dengan web). Kartu KPI mengikuti role lewat `DashboardMetricsService`.
- Cabang wajib: `auth:sanctum`, `mobile.access`, `mobile.branch`. Angka difilter cabang aktif (`X-Branch-Id`).
- Service web diperluas: `forUser` menerima `?int $branchId = null`. Web tetap memanggil tanpa branch (null). Cache key menyertakan branch.
- Workspace operasional memaksa profil metrik `clinic-ops`, sama dengan `DashboardController` web.
- Tanpa `mobile.writes` (hanya GET). Tenant suspended tetap boleh baca.
- Verifikasi manual lewat HTTP. Tidak menambah Pest/PHPUnit.

## Rute

| Metode | Path | Auth / middleware |
| --- | --- | --- |
| GET | `/api/v1/dashboard` | token, `mobile.access`, `mobile.branch`, izin `dashboard.view` |

## Respons

Sukses membungkus isi di `data`:

| Field | Isi |
| --- | --- |
| `title` | Judul dashboard (role / workspace) |
| `description` | Teks sambutan singkat |
| `workspace` | Nilai `Workspace` (`admin`, `operational`, `clinical`, `default`, dll.) |
| `cards` | `[{ "label": string, "value": string }]` — tanpa URL web |
| `links` | `[{ "label": string, "route": string }]` — `route` = nama route Laravel sebagai petunjuk deep-link; klien mengabaikan yang tidak dikenal |
| `note` | string atau `null` |
| `setup_checklist` | Owner: `{ percent, complete, items: [{ key, label, done, optional }] }` tanpa `href`. Selain owner: `null` |
| `branch` | `{ id, name }` cabang aktif |

Periode KPI mengikuti service web (hari ini / bulan berjalan). Tidak ada query rentang tanggal.

Rahasia integrasi dan `queue_monitor_token` tidak dikirim.

## Error

| Situasi | HTTP | code |
| --- | --- | --- |
| Token kosong, salah, atau kedaluwarsa | 401 | — |
| Tanpa `dashboard.view` | 403 | `forbidden` |
| Cabang di luar izin atau milik tenant lain | 403 | `branch_forbidden` |
| Cabang nonaktif | 403 | `branch_inactive` |
| Tidak ada cabang aktif | 403 | `branch_required` |

## Komponen

- `routes/api.php` — daftar `GET dashboard` di dalam `mobile.branch`
- `App\Http\Controllers\Api\DashboardController`
- Perluasan `App\Support\Reports\DashboardMetricsService::forUser` (parameter branch + cache)
- Helper/transform di controller (atau resource) untuk: buang `href` kartu, ubah link web jadi `{ label, route }`, checklist tanpa `href`
- `WorkspaceDetector`, `SetupChecklistService` (sudah ada)

Tidak mengubah perilaku tampilan web kecuali service menerima argumen branch opsional dengan default null.

## Verifikasi manual

- Owner: 200, kartu klinik, `setup_checklist` objek (bukan null).
- Manager / clinic-ops / dokter / farmasi / kasir: kartu sesuai role web untuk cabang aktif.
- Role tanpa `dashboard.view`: 403 `forbidden`.
- Tanpa cabang aktif: 403 `branch_required`.
- Tenant `suspended`: GET dashboard tetap 200.
- Dashboard web tetap menampilkan angka tanpa prefer cabang (pemanggilan service tanpa branchId).
