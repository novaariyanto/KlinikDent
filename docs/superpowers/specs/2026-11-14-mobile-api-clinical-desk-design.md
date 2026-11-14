# API Meja Admin dan Dokter

API JSON untuk kerja harian admin operasional dan dokter. Fondasi login, profil, dan onboarding sudah ada di `docs/superpowers/specs/2026-11-14-mobile-api-foundation-design.md`. Kasir dan farmasi (perluasan operator) ada di `docs/superpowers/specs/2026-11-14-mobile-api-ops-pharmacy-billing-design.md`. Dashboard ringkasan ada di `docs/superpowers/specs/2026-11-14-mobile-api-dashboard-design.md`. Prompt Flutter ada di `docs/superpowers/specs/2026-11-14-flutter-mobile-prompt.md`.

## Keputusan

- Controller API baru di `App\Http\Controllers\Api`. FormRequest dan service web yang sudah ada yang mengerjakan aturan bisnis.
- Izin tiap endpoint sama dengan izin yang membuka route web padanannya (`EnforceRoutePermission` dan middleware `permission:` pada route itu).
- Cabang dari `X-Branch-Id`. Grup route ini memakai `mobile.branch` wajib, bukan `optional`.
- Penulisan rekam medis yang terkunci memakai `record.mutable`. Klien mengirim `Accept: application/json`.
- PDF adalah badan `application/pdf`, sama seperti unduhan web. Bukan JSON dan bukan URL sementara.
- Layar monitor ruang tunggu tetap di web. Antrean lab farmasi dan penyerahan obat masuk modul farmasi.
- Verifikasi manual lewat HTTP. Tidak menambah file Pest atau PHPUnit.

## Rute

Prefix `/api/v1`. Middleware grup: `auth:sanctum`, `mobile.access`, `mobile.branch`. Penulisan menambah `mobile.writes`. Penulisan isi rekam medis yang bisa dikunci menambah `record.mutable`, pada aksi yang sama dengan grup itu di web.

### Pasien dan wilayah

| Metode | Path | Padanan web |
| --- | --- | --- |
| GET | `/patients` | `registration.patients` |
| GET | `/patients/search` | `registration.patients.search` |
| POST | `/patients` | `registration.patients.store` |
| GET | `/patients/{patient}` | `registration.patients.show` |
| PUT | `/patients/{patient}` | `registration.patients.update` |
| DELETE | `/patients/{patient}` | `registration.patients.destroy` |
| POST | `/patients/{patient}/satusehat` | `registration.patients.satusehat` |
| GET | `/wilayah/cities` | `registration.wilayah.cities` |
| GET | `/wilayah/districts` | `registration.wilayah.districts` |
| GET | `/wilayah/villages` | `registration.wilayah.villages` |
| GET | `/wilayah/from-nik` | `registration.wilayah.from-nik` |

### Kunjungan

| Metode | Path | Padanan web |
| --- | --- | --- |
| GET | `/visits` | `registration.visits` |
| GET | `/visits/availability` | `registration.availability` |
| POST | `/visits` | `registration.visits.store` |
| POST | `/visits/patient-ihs` | `registration.patient-ihs` |
| GET | `/visits/{visit}` | `registration.visits.show` |
| POST | `/visits/{visit}/cancel` | `registration.visits.cancel` |

`GET /visits` memakai query `scope=today` atau `scope=history`.

### Antrean

| Metode | Path | Padanan web |
| --- | --- | --- |
| GET | `/queues` | `queue.index` |
| POST | `/queues/{queue}/call` | `queue.call` |
| POST | `/queues/{queue}/complete` | `queue.complete` |
| POST | `/queues/{queue}/skip` | `queue.skip` |

`GET /queues` memakai query `scope=today|mine|waiting|called`. `scope=mine` memakai saringan dokter yang sama dengan `queue.mine`.

### Pemeriksaan

`GET /visits/{visit}/care` padanan `care.show`. Satu respons berisi pasien, kunjungan, rekam medis, pemeriksaan, tanda vital, anamnesis, gigi, diagnosis, tindakan, resep, rujukan, surat, dan order lab kunjungan itu.

Penulisan mengulang aksi web dan memakai FormRequest yang sama:

- `PUT /visits/{visit}/care/record`
- `PUT /visits/{visit}/care/examination`
- `PUT /visits/{visit}/care/vitals`
- `PUT /visits/{visit}/care/anamnesis`
- `PUT /visits/{visit}/care/follow-up`
- `PUT /visits/{visit}/care/notes`
- `PUT /visits/{visit}/care/tooth`
- `DELETE /visits/{visit}/care/tooth/{toothNumber}` dengan `toothNumber` dua digit
- `PUT /visits/{visit}/care/dental-exam`
- `PUT /visits/{visit}/care/systemic`
- `POST|PUT|DELETE /visits/{visit}/care/diagnoses` dan `.../diagnoses/{diagnosis}`
- `GET /visits/{visit}/care/diagnoses/search`
- `POST|PUT|DELETE /visits/{visit}/care/procedures` dan `.../procedures/{procedure_record}`
- `GET /visits/{visit}/care/procedures/icd9/search`
- `GET /visits/{visit}/care/procedures/tariff`
- `POST /visits/{visit}/care/procedures/{procedure_record}/waiver/approve`
- `POST|PUT|DELETE /visits/{visit}/care/prescription-items` dan `.../prescription-items/{prescriptionItem}`
- `POST /visits/{visit}/care/prescriptions/{prescription}/send`
- `POST /visits/{visit}/care/prescription-templates`
- `PUT /visits/{visit}/care/prescription-templates/{prescriptionTemplate}`
- `DELETE /visits/{visit}/care/prescription-templates/{prescriptionTemplate}`
- `POST /visits/{visit}/care/prescription-templates/{prescriptionTemplate}/apply`
- `POST /visits/{visit}/care/prescriptions/{prescription}/template`
- `POST|PUT|DELETE /visits/{visit}/care/referrals` dan `.../referrals/{referral}`
- `POST|PUT|DELETE /visits/{visit}/care/lab` dan `.../lab/{order}`
- `POST /visits/{visit}/care/finalize`
- `POST /visits/{visit}/care/assessment/complete`
- `POST /visits/{visit}/care/amendments`
- `POST /visits/{visit}/care/sick-letter`
- `POST /visits/{visit}/care/complete`

Surat medis:

- `GET /medical-letters`
- `GET /medical-letters/{letter}`
- `POST /visits/{visit}/medical-letters`
- `POST /visits/{visit}/medical-letters/preview` mengembalikan JSON draf surat, bukan HTML
- `POST /visits/{visit}/medical-letters/frame` mengembalikan JSON data cetak draf, bukan HTML
- `POST /medical-letters/{letter}/cancel`
- `POST /medical-letters/{letter}/revise`

### Riwayat

| Metode | Path | Padanan web |
| --- | --- | --- |
| GET | `/patients/{patient}/history` | `patients.history.show` |
| GET | `/patients/{patient}/history/visits/{visit}` | `patients.history.visit` |
| POST | `/patients/{patient}/history/resume` | `patients.history.resume.store` |

### PDF

Mengembalikan `application/pdf`:

- `GET /visits/{visit}/care/resume/pdf`
- `GET /visits/{visit}/care/documents/{document}/pdf`
- `GET /visits/{visit}/care/referrals/{referral}/pdf`
- `GET /visits/{visit}/care/prescriptions/pdf`
- `GET /visits/{visit}/care/prescriptions/{prescription}/pdf`
- `GET /visits/{visit}/care/instructions/pdf`
- `GET /visits/{visit}/care/discharge/pdf`
- `GET /medical-letters/{letter}/pdf`
- `GET /patients/{patient}/history/resume/{document}/pdf`
- `GET /patients/{patient}/history/prescriptions/{prescription}/pdf`

## Respons

JSON sukses membungkus isi di `data`. Daftar memakai query `page` dan `per_page` (bawaan 20, maksimum 50) dan menambah:

```json
{ "current_page": 1, "last_page": 1, "total": 0 }
```

di kunci `meta`.

Daftar memuat field yang terlihat di tabel web padanan. `queue_monitor_token` dan rahasia integrasi tidak dikirim.

`GET /patients/search` memakai query `q` dan pencocokan `PatientController::search`.

Penulisan mengembalikan objek bagian yang baru disimpan ditambah `visit_id`. Aplikasi mengambil ulang `GET /visits/{visit}/care` untuk berkas lengkap.

`scope=mine` pada antrean memakai saringan yang sama dengan `QueueController` untuk `queue.mine`. Daftar lain dibatasi cabang aktif.

## Error

Validasi field memakai 422 FormRequest yang sudah ada. Rekam medis terkunci, karena `Accept: application/json`, menjawab 422 dengan pesan web: batas koreksi 2×24 jam telah lewat, perubahan hanya melalui adendum.

| Situasi | HTTP | code |
| --- | --- | --- |
| Token kosong, salah, atau kedaluwarsa | 401 | — |
| Izin tidak dimiliki | 403 | `forbidden` |
| Cabang di luar izin atau milik tenant lain | 403 | `branch_forbidden` |
| Cabang nonaktif | 403 | `branch_inactive` |
| Tidak ada cabang aktif | 403 | `branch_required` |
| Tulisan saat langganan ditangguhkan | 403 | `tenant_suspended` |
| Pasien, kunjungan, antrean, atau surat di luar tenant | 404 | — |
| PDF tidak ditemukan | 404 | — |

## Komponen

- Tambahan route di `routes/api.php`
- `App\Http\Controllers\Api\PatientController`
- `App\Http\Controllers\Api\VisitController`
- `App\Http\Controllers\Api\QueueController`
- `App\Http\Controllers\Api\CareController`
- `App\Http\Controllers\Api\MedicalLetterController`
- `App\Http\Controllers\Api\PatientHistoryController`
- Resource JSON di `App\Http\Resources\Api` untuk daftar, detail, dan berkas care

Controller API memanggil metode service yang sudah dipakai web, misalnya `ClinicalCareService`, `MedicalLetterService`, dan registrar kunjungan. Activity log tetap dicatat. Respons menggantikan redirect. Aturan validasi tidak disalin ke controller API.

`POST /visits` mengembalikan kunjungan, nomor antrean, dan kalimat sukses yang sama dengan web. Preview surat tidak menerbitkan nomor.

## Verifikasi manual

- Admin operasional mencari pasien, membuat pasien, membuat kunjungan, dan membatalkan kunjungan. `scope=today` dan `scope=history` hanya berisi cabang aktif.
- Antrean `scope=today|mine|waiting|called` mengikuti saringan web. Panggil, selesai, dan lewati mengubah status yang sama dengan web.
- Dokter membuka care, menyimpan anamnesis, tanda vital, gigi, diagnosis, tindakan, dan item resep, lalu mengirim resep.
- Rujukan, surat sakit, surat medis, template resep, finalisasi, amendemen, dan order lab kunjungan memakai aturan web. Rekam medis terkunci menjawab 422 adendum.
- PDF resume, resep, rujukan, dan surat kembali sebagai `application/pdf`.
- Role tanpa izin mendapat 403 `forbidden`. Kunjungan tenant lain 404. Header cabang yang ditolak memakai kode cabang fondasi.
- Tenant `suspended` dapat membaca daftar dan berkas. Penulisan ditolak `tenant_suspended`.
