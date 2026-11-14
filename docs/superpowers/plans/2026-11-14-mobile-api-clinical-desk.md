# API Meja Admin dan Dokter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah API `/api/v1` untuk pasien, kunjungan, antrean, pemeriksaan, surat, riwayat, dan PDF, dengan aturan bisnis yang sama seperti web.

**Architecture:** Controller di `App\Http\Controllers\Api` memvalidasi lewat FormRequest yang sudah ada, memanggil service atau metode yang dipindah dari controller web, lalu mengembalikan JSON. PDF mengembalikan response DomPDF yang sudah dipakai web.

**Tech Stack:** Laravel 12, Sanctum, FormRequest dan service klinik yang sudah ada, DomPDF.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-11-14-mobile-api-clinical-desk-design.md`
- Fondasi yang sudah ada tidak diubah perilakunya: login, `/me`, onboarding, `mobile.branch:optional` pada rute itu
- Izin sama dengan route web padanan
- `mobile.branch` wajib pada rute modul ini
- PDF `application/pdf`, bukan JSON
- Monitor ruang tunggu tidak dibuat
- Jangan menambah atau menjalankan Pest/PHPUnit
- Jangan commit kecuali user meminta
- Jangan menampilkan password, token, atau rahasia integrasi

## File map

| File | Tanggung jawab |
| --- | --- |
| `app/Http/Controllers/Api/Concerns/RespondsWithPage.php` | `data` + `meta` halaman |
| `bootstrap/app.php` | 403 `forbidden` untuk `AuthorizationException` pada `api/*` |
| `app/Http/Controllers/Api/PatientController.php` | Pasien |
| `app/Http/Controllers/Api/WilayahController.php` | Kota, kecamatan, desa, NIK |
| `app/Http/Controllers/Api/VisitController.php` | Kunjungan |
| `app/Http/Controllers/Api/QueueController.php` | Antrean |
| `app/Http/Controllers/Api/CareController.php` | Berkas dan penulisan pemeriksaan |
| `app/Support/Clinical/ClinicalCareService.php` | Metode yang dipindah dari controller web |
| `app/Http/Controllers/Api/MedicalLetterController.php` | Surat |
| `app/Http/Controllers/Api/PatientHistoryController.php` | Riwayat |
| `routes/api.php` | Rute spec |

---

### Task 1: Halaman dan 403 forbidden

**Files:**
- Create: `app/Http/Controllers/Api/Concerns/RespondsWithPage.php`
- Modify: `bootstrap/app.php`

**Interfaces:**
- Consumes: tidak ada
- Produces: `RespondsWithPage::paged(Request $request, Builder $query, callable $map): JsonResponse`. `AuthorizationException` pada `api/*` menjawab `{message, code: forbidden}` status 403.

- [ ] **Step 1: Trait halaman**

```php
<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RespondsWithPage
{
    protected function paged(Request $request, Builder $query, callable $map): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->integer('per_page', 20)));
        $page = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map($map)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
```

- [ ] **Step 2: Render 403**

Di `bootstrap/app.php`, dalam `withExceptions`, tambahkan render `Illuminate\Auth\Access\AuthorizationException` bila `$request->is('api/*')`:

```php
return response()->json([
    'message' => $e->getMessage() ?: 'This action is unauthorized.',
    'code' => 'forbidden',
], 403);
```

- [ ] **Step 3: Verifikasi**

`php -l` pada trait. Jangan commit.

---

### Task 2: Pasien dan wilayah

**Files:**
- Create: `app/Http/Controllers/Api/PatientController.php`, `app/Http/Controllers/Api/WilayahController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `StorePatientRequest`, `UpdatePatientRequest`, `Patient::search`, `RespondsWithPage`, controller web `Registration\PatientController` untuk `enrichWilayahFields`, `sequences`, dan `lookupPatientIhs`. `Registration\WilayahController` untuk cities, districts, villages, fromNik.
- Produces: rute pasien dan wilayah di spec. `POST /patients` mengembalikan pasien plus `message` yang sama dengan web.

- [ ] **Step 1: Pindahkan pembantu yang dipakai store/update**

`enrichWilayahFields` di `Registration\PatientController` dipakai API. Jadikan public di kelas itu, atau pindah ke metode yang sudah ada di layanan wilayah bila metode itu sudah melakukan hal yang sama. Jangan menyalin isi pemetaan wilayah ke dua tempat.

- [ ] **Step 2: Controller pasien**

`index` memakai `Patient::query()->visibleTo($request->user())` bila scope itu ada; bila tidak, salin pembatas tenant yang dipakai `data()` web. Paginate lewat `paged`. Field daftar: `id`, `medical_record_number`, `name`, `phone`, `dob`.

`search` menyalin query `Registration\PatientController::search` dan membungkus hasil di `data`.

`store` dan `update` memakai FormRequest yang sama dan transaksi yang sama dengan method web (`nextMedicalRecord`, `enrichWilayahFields`, `lookupPatientIhs`, `activity_audit`). Respons:

```php
return response()->json([
    'data' => $patient,
    'message' => $message,
]);
```

`destroy` menolak pasien yang sudah punya kunjungan dengan 422 dan pesan web: `Pasien tidak dapat dihapus karena sudah memiliki kunjungan.`

`fetchIhs` memanggil aksi web yang sama dan mengembalikan `satusehat_id`.

Setiap aksi memanggil `$this->authorize(...)` yang sama dengan method web.

- [ ] **Step 3: Wilayah**

`WilayahController` API memanggil metode yang sama dengan `Registration\WilayahController` dan mengembalikan JSON yang method itu sudah hasilkan, dibungkus `data` bila web mengembalikan array telanjang.

- [ ] **Step 4: Rute**

Di dalam grup `auth:sanctum` + `mobile.access` + `mobile.branch` (wajib):

```php
Route::get('patients/search', [PatientController::class, 'search']);
Route::get('patients', [PatientController::class, 'index']);
Route::post('patients', [PatientController::class, 'store']);
Route::get('patients/{patient}', [PatientController::class, 'show']);
Route::put('patients/{patient}', [PatientController::class, 'update']);
Route::delete('patients/{patient}', [PatientController::class, 'destroy']);
Route::post('patients/{patient}/satusehat', [PatientController::class, 'fetchIhs']);
Route::get('wilayah/cities', [WilayahController::class, 'cities']);
Route::get('wilayah/districts', [WilayahController::class, 'districts']);
Route::get('wilayah/villages', [WilayahController::class, 'villages']);
Route::get('wilayah/from-nik', [WilayahController::class, 'fromNik']);
```

`patients/search` harus terdaftar sebelum `patients/{patient}`.

- [ ] **Step 5: Verifikasi**

`php artisan route:list --path=api/v1/patients`. Cari pasien dengan token yang punya `patient.view`. Role tanpa izin mendapat 403 `forbidden`.

---

### Task 3: Kunjungan

**Files:**
- Create: `app/Http/Controllers/Api/VisitController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `StoreVisitRequest`, `VisitRegistrar::register`, `Visit::visibleTo`, `Visit::today`
- Produces: `GET/POST /visits`, availability, patient-ihs, show, cancel. `POST /visits` berisi kunjungan, `queue_number`, dan `message` kalimat web.

- [ ] **Step 1: Daftar dan detail**

`index` membaca `scope`. `today` menambah `->today()`. `history` tidak menambah `today()`. Field: `id`, `visit_date`, `queue_number`, `patient`, `doctor`, `room`, `status`.

`show` memuat relasi yang sama dengan `Registration\VisitController::show` dan mengembalikan field itu plus `id` pasien, dokter, poli, penjamin, dan cabang.

- [ ] **Step 2: Tulis**

```php
public function store(StoreVisitRequest $request, VisitRegistrar $registrar): JsonResponse
{
    $visit = $registrar->register($request->user(), $request->validated());
    $visit->load(['patient', 'queue']);

    activity_log('created', $visit, $request->validated(), 'Registered visit for '.$visit->patient?->name, 'visits');

    $queueNumber = $visit->queue?->displayNumber();
    $ihs = $visit->patient?->satusehat_id;
    $ihsNote = $ihs ? ' IHS '.$ihs.'.' : '';
    $message = $registrar->reusedExistingPatient
        ? 'NIK sudah terdaftar pada '.$visit->patient?->name.' (RM '.$visit->patient?->medical_record_number.'). Kunjungan dicatat pada rekam medis itu. Nomor antrean '.$queueNumber.'.'.$ihsNote
        : 'Pendaftaran berhasil. Nomor antrean '.$queueNumber.'.'.$ihsNote;

    return response()->json([
        'data' => [
            'visit_id' => $visit->id,
            'queue_number' => $queueNumber,
            'patient_id' => $visit->patient_id,
        ],
        'message' => $message,
    ]);
}
```

`availability` dan `patientIhs` mengembalikan JSON yang method web sudah bangun (`doctors` pada availability). `cancel` menyalin `Registration\VisitController::cancel` tanpa redirect, mengembalikan kunjungan yang dibatalkan.

- [ ] **Step 3: Rute dan verifikasi**

Daftarkan path spec. `php artisan route:list --path=api/v1/visits`. Buat satu kunjungan uji hanya bila ada pasien cabang aktif; jangan mengubah kunjungan klinik lain.

---

### Task 4: Antrean

**Files:**
- Create: `app/Http/Controllers/Api/QueueController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: query `Registration\QueueController::filteredQuery`. Ubah method itu menjadi public agar API memakai objek query yang sama.
- Produces: `GET /queues?scope=`, call, complete, skip.

- [ ] **Step 1: Jadikan filter bisa dipakai ulang**

Ubah `filteredQuery` menjadi public. Tambah argumen opsional `?string $scope = null`. Bila `$scope` terisi, pakai itu sebagai `$filter`, bukan `filter` query web. Nilai `today`, `mine`, `waiting`, `called` tetap cabang yang ada sekarang.

- [ ] **Step 2: Controller**

`index` memanggil `filteredQuery($request, $request->query('scope', 'today'))` lewat instance controller web atau, lebih bersih, pindahkan query ke `App\Support\Registration\QueueQuery` dan panggil dari kedua controller. Pilih satu tempat. Jangan menyalin syarat `visibleTo`, `today`, dan `doctor_id`.

Field: `id`, `queue_number` dari `displayNumber()`, `status`, `called_at`, `visit_id`, `patient`, `doctor`, `room`.

`call`, `complete`, dan `skip` menyalin tiga method web termasuk `authorize` dan `activity_log`, lalu mengembalikan antrean.

- [ ] **Step 3: Verifikasi**

`scope=mine` untuk dokter hanya antrean `doctor_id` user itu. Panggil satu antrean menunggu milik cabang uji, lalu pastikan statusnya `called` di web maupun API.

---

### Task 5: Berkas pemeriksaan

**Files:**
- Create: `app/Http/Controllers/Api/CareController.php`, `app/Http/Resources/Api/CareResource.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: data yang di-load `Clinical\CareController::show`
- Produces: `GET /visits/{visit}/care`

- [ ] **Step 1: Resource**

`CareResource` memuat bagian yang diisi view `care.show`: pasien, kunjungan, rekam medis, pemeriksaan, tanda vital, anamnesis, gigi, diagnosis, tindakan, resep beserta item, rujukan, surat, dan order lab kunjungan. Jangan mengirim token monitor atau rahasia integrasi.

- [ ] **Step 2: Show**

```php
public function show(Request $request, Visit $visit): CareResource
{
    $this->authorize('view', $visit);

    return new CareResource($visit);
}
```

Load relasi yang sama dengan `Clinical\CareController::show` di dalam resource atau sebelum `new CareResource`.

- [ ] **Step 3: Verifikasi**

Dokter membuka satu kunjungan miliknya. Role tanpa `examination.view` mendapat 403 `forbidden`. Kunjungan tenant lain 404.

---

### Task 6: Penulisan pemeriksaan

**Files:**
- Modify: `app/Support/Clinical/ClinicalCareService.php`, `app/Http/Controllers/Clinical/CareController.php`, `app/Http/Controllers/Api/CareController.php`, `routes/api.php`

**Interfaces:**
- Consumes: FormRequest yang sudah dipakai tiap method web
- Produces: path penulisan di spec. Tiap method API mengembalikan bagian yang disimpan plus `visit_id`.

- [ ] **Step 1: Pindahkan isi method web**

Untuk setiap baris di bawah, pindahkan isi method web ke `ClinicalCareService` dengan nama yang sama. Method web menjadi panggilan service lalu `respondCare` seperti sekarang. Method API memanggil service yang sama lalu JSON.

| Method web | Service baru |
| --- | --- |
| `updateRecord` | `updateRecord(Visit, array): MedicalRecord` |
| `updateExamination` | `updateExamination(Visit, array): MedicalRecord` |
| `updateVitals` | `updateVitals(Visit, array): MedicalRecord` |
| `updateAnamnesis` | `updateAnamnesis(Visit, array): MedicalRecord` |
| `updateFollowUp` | `updateFollowUp(Visit, array): MedicalRecord` |
| `updateNotes` | `updateNotes(Visit, array): MedicalRecord` |
| `updateTooth` | sudah ada; API memanggil yang ada |
| `destroyTooth` | `destroyTooth(Visit, string): void` |
| `updateDentalExam` | `updateDentalExam(Visit, array): MedicalRecord` |
| `updateSystemicHistory` | `updateSystemicHistory(Visit, array): MedicalRecord` |
| `storeDiagnosis` / `updateDiagnosis` / `destroyDiagnosis` | nama yang sama |
| `storeProcedure` / `updateProcedure` / `destroyProcedure` / `approveProcedureWaiver` | nama yang sama |
| `storePrescriptionItem` / `updatePrescriptionItem` / `destroyPrescriptionItem` / `sendPrescription` | nama yang sama |
| `storeReferral` / `updateReferral` / `destroyReferral` | nama yang sama |
| `finalizeRecord` / `completeAssessment` / `storeAmendment` / `storeSickLetter` / `complete` | nama yang sama |

`searchDiagnoses`, `searchIcd9`, dan `quoteProcedureTariff` mengembalikan JSON yang method web sudah bangun, dibungkus `data`.

Template resep dan order lab memanggil `PrescriptionTemplateController` dan `DentalLabController` method yang sudah ada. Bila isinya masih redirect, pindahkan penyimpanannya ke service yang controller itu sudah pakai, lalu kedua controller memanggilnya.

Grup `record.mutable` pada route API sama dengan grup web. `finalize`, `completeAssessment`, `amendments`, `sick-letter`, dan `complete` tetap di luar grup itu, sama seperti web.

- [ ] **Step 2: Contoh pembungkus API**

```php
public function updateVitals(UpdateVitalsRequest $request, Visit $visit): JsonResponse
{
    $this->authorize('update', $visit);
    $record = $this->care->updateVitals($visit, $request->validated());

    return response()->json([
        'data' => [
            'visit_id' => $visit->id,
            'vital_signs' => $record->vital_signs,
        ],
    ]);
}
```

Method lain mengikuti bentuk itu: authorize yang sama dengan web, panggil service, kembalikan model yang disimpan plus `visit_id`.

- [ ] **Step 3: Verifikasi**

Pada kunjungan uji yang belum terkunci, simpan tanda vital dan satu diagnosis. Buka halaman web care kunjungan itu dan pastikan nilainya sama. Ulangi pada rekam medis terkunci: API menjawab 422 adendum.

---

### Task 7: Surat, riwayat, dan PDF

**Files:**
- Create: `app/Http/Controllers/Api/MedicalLetterController.php`, `app/Http/Controllers/Api/PatientHistoryController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `MedicalLetterService::draft`, `issue`, dan method riwayat `PatientHistoryController`
- Produces: rute surat, riwayat, dan seluruh GET PDF di spec

- [ ] **Step 1: Surat**

`index` dan `show` mengembalikan field daftar dan detail yang terlihat di web. `store` memanggil `letters->issue`. `preview` memanggil `letters->draft` dan mengembalikan JSON draf, bukan view. `frame` mengembalikan data cetak draf sebagai JSON. `cancel` dan `revise` memanggil method service yang dipakai controller web.

- [ ] **Step 2: Riwayat**

`show` dan `visit` mengembalikan ringkasan yang dipakai `PatientHistoryController`. `storeResume` memanggil service resume yang sama.

- [ ] **Step 3: PDF**

Tiap GET PDF di spec memanggil method print web yang sama (`printResume`, `printDocument`, `printReferral`, `printPrescriptions`, `printPrescription`, `printInstructions`, `printDischarge`, `MedicalLetterController::pdf`, `PatientHistoryController::resumePdf`, `printPrescription`) dan me-return response PDF itu langsung.

- [ ] **Step 4: Verifikasi**

Terbitkan atau pratinjau surat pada kunjungan uji. `GET` PDF resume mengembalikan `Content-Type: application/pdf`. Tenant `suspended` dapat `GET` care dan gagal pada `PUT` vitals dengan `tenant_suspended`.

---

## Self-review

- Pasien, wilayah, kunjungan, antrean, care, surat, riwayat, dan PDF spec tercakup Task 2–7.
- Monitor antrean tidak ada task-nya.
- Task 6 memindahkan isi, bukan menyalin aturan ke controller API.
