# Dokumentasi API Mobile KlinikDent

Dokumen tunggal untuk klien mobile (Flutter/dll.). Base path: `/api/v1`. Dihasilkan dari rute terdaftar + kontrak spek modul.

- Tanggal: 2026-11-14
- Jumlah rute: 149
- Branch referensi: `feature/mobile-api-foundation`

## Daftar isi

1. [Ringkasan](#ringkasan)
2. [Autentikasi & header](#autentikasi--header)
3. [Bentuk respons](#bentuk-respons)
4. [Kode error](#kode-error)
5. [Auth, profil, onboarding](#auth-profil-onboarding)
6. [Dashboard](#dashboard)
7. [Klinis](#pasien-kunjungan-antrean-care-surat-riwayat)
8. [Farmasi](#farmasi)
9. [Billing & kasir](#billing--kasir)
10. [Indeks semua endpoint](#indeks-semua-endpoint)

## Ringkasan

API JSON multi-tenant untuk aplikasi mobile. Izin Spatie sama dengan web (`guard` web). Token Sanctum ability `mobile`, 30 hari.

| Modul | Cakupan |
| --- | --- |
| Auth | login, register, logout, me, onboarding owner |
| Dashboard | KPI ringkas per role + checklist owner |
| Klinis | pasien, wilayah, kunjungan, antrean, care, PDF, surat, riwayat |
| Farmasi | resep, stok, lab, supplier, PO, laporan |
| Billing | tagihan, bayar, piutang, shift kasir |

Spek desain terpisah (opsional):
- `docs/superpowers/specs/2026-11-14-mobile-api-foundation-design.md`
- `docs/superpowers/specs/2026-11-14-mobile-api-clinical-desk-design.md`
- `docs/superpowers/specs/2026-11-14-mobile-api-ops-pharmacy-billing-design.md`
- `docs/superpowers/specs/2026-11-14-mobile-api-dashboard-design.md`
- Prompt app: `docs/superpowers/specs/2026-11-14-flutter-mobile-prompt.md`

## Autentikasi & header

| Header | Keterangan |
| --- | --- |
| `Authorization: Bearer {token}` | Wajib kecuali login & register |
| `X-Branch-Id: {id}` | Cabang aktif; wajib pada hampir semua rute klinik |
| `Accept: application/json` | Disarankan; PDF tetap binary |
| `Content-Type: application/json` | Body JSON; onboarding logo boleh multipart |

**Middleware (kolom Auth di tabel):**

- `publik` — tanpa token
- `token` — `auth:sanctum` + `mobile.access`
- `branch` — `mobile.branch` wajib
- `branch:optional` — cabang boleh null (`me`, onboarding)
- `writes` — `mobile.writes` (ditolak jika tenant suspended)
- `record.mutable` — rekam terkunci → 422 adendum
- `visit.tenant` — kunjungan harus milik tenant user
- `throttle:register` — 5/menit per IP

## Bentuk respons

**JSON sukses:** isi utama di `data`.

**Login / register:**

```json
{
  "token": "1|…",
  "token_type": "Bearer",
  "expires_at": "2026-12-14T09:00:00+07:00",
  "data": { /* MobileUserResource */ },
  "message": "…" // register saja
}
```

**Profil (`data` / `GET /me`):** `id`, `name`, `email`, `status`, `role`, `roles`, `permissions`, `tenant` (`needs_onboarding`, step, progress), `branches[]`, `active_branch`.

**Daftar:** query `page`, `per_page` (default 20, max 50) + `meta.current_page`, `meta.last_page`, `meta.total`.

**Penulisan:** objek + `message` (pesan web).

**PDF:** `Content-Type: application/pdf` (receipt tagihan bisa HTML seperti web).

Rahasia integrasi dan `queue_monitor_token` tidak dikirim.

## Kode error

| Situasi | HTTP | code |
| --- | --- | --- |
| Kredensial salah | 422 | — |
| Validasi field | 422 | — |
| Akun nonaktif (login) | 422 | `account_inactive` |
| Onboarding sudah selesai (mutasi) | 422 | `onboarding_completed` |
| Rekam terkunci / aturan bisnis farmasi-billing | 422 | pesan service |
| Throttle login/register | 429 | — |
| Token invalid | 401 | — |
| Tanpa izin | 403 | `forbidden` |
| Super Admin SaaS | 403 | `platform_admin_web_only` |
| Bukan owner (onboarding) | 403 | `owner_only` |
| Cabang terlarang | 403 | `branch_forbidden` |
| Cabang nonaktif | 403 | `branch_inactive` |
| Cabang wajib hilang | 403 | `branch_required` |
| Tulisan saat suspended | 403 | `tenant_suspended` |
| User tanpa tenant | 403 | `tenant_required` |
| Resource luar tenant / tidak ada | 404 | — |

Bentuk domain error:

```json
{ "message": "...", "code": "forbidden" }
```

## Auth, profil, onboarding

### Register

`POST /auth/register` — publik, throttle register.

Body: `name` (klinik), `owner_name`, `owner_email`, `password`, `password_confirmation`; `package_id` opsional (default trial); `device_name` opsional. Subdomain digenerate server.

Respons: sama login + `message`. Lanjut onboarding.

### Login

`POST /auth/login` — `email`, `password`, `device_name` opsional. Throttle 5/menit (email+IP).

### Logout

`POST /auth/logout` — hapus token saat ini.

### Me

`GET /me` — profil; `branch:optional`.

### Onboarding (owner)

Langkah: `template`, `roles`, `profile`, `branch`, `doctors`, `staff`, `services`, `medicines`, `integrations`, `review`. Skip opsional: doctors, staff, services, medicines, integrations. `profile` boleh multipart `logo`.

## Auth, profil, onboarding

| Metode | Path | Auth | Controller |
| --- | --- | --- | --- |
| POST | `/auth/login` | publik | `AuthController@store` |
| POST | `/auth/logout` | token | `AuthController@destroy` |
| POST | `/auth/register` | publik, throttle:register | `AuthController@register` |
| GET | `/me` | token, branch:optional | `MeController@show` |
| GET | `/onboarding` | token, branch:optional | `OnboardingController@show` |
| POST | `/onboarding/back` | token, branch:optional, writes | `OnboardingController@back` |
| POST | `/onboarding/complete` | token, branch:optional, writes | `OnboardingController@complete` |
| POST | `/onboarding/steps/{step}` | token, branch:optional, writes | `OnboardingController@step` |
| POST | `/onboarding/steps/{step}/skip` | token, branch:optional, writes | `OnboardingController@skip` |

## Dashboard

Izin: `dashboard.view`. KPI mengikuti role; cabang aktif. Owner dapat `setup_checklist`.

Field `data`: `title`, `description`, `workspace`, `cards[{label,value}]`, `links[{label,route}]`, `note`, `setup_checklist`, `branch`.

| Metode | Path | Auth | Controller |
| --- | --- | --- | --- |
| GET | `/dashboard` | token, branch | `DashboardController@show` |

## Pasien, kunjungan, antrean, care, surat, riwayat

Query penting: `visits?scope=today|history`, `queues?scope=today|mine|waiting|called`, `patients/search?q=`.

`GET /visits/{visit}/care` = berkas lengkap pemeriksaan. Penulisan care memakai FormRequest web; setelah tulis, refresh care.

| Metode | Path | Auth | Controller |
| --- | --- | --- | --- |
| GET | `/medical-letters` | token, branch | `MedicalLetterController@index` |
| GET | `/medical-letters/{letter}` | token, branch | `MedicalLetterController@show` |
| POST | `/medical-letters/{letter}/cancel` | token, branch, writes | `MedicalLetterController@cancel` |
| GET | `/medical-letters/{letter}/pdf` | token, branch | `MedicalLetterController@pdf` |
| POST | `/medical-letters/{letter}/revise` | token, branch, writes | `MedicalLetterController@revise` |
| GET | `/patients` | token, branch | `PatientController@index` |
| POST | `/patients` | token, branch, writes | `PatientController@store` |
| GET | `/patients/search` | token, branch | `PatientController@search` |
| DELETE | `/patients/{patient}` | token, branch, writes | `PatientController@destroy` |
| GET | `/patients/{patient}` | token, branch | `PatientController@show` |
| PUT | `/patients/{patient}` | token, branch, writes | `PatientController@update` |
| GET | `/patients/{patient}/history` | token, branch | `PatientHistoryController@show` |
| GET | `/patients/{patient}/history/prescriptions/{prescription}/pdf` | token, branch | `PatientHistoryController@printPrescription` |
| POST | `/patients/{patient}/history/resume` | token, branch, writes | `PatientHistoryController@storeResume` |
| GET | `/patients/{patient}/history/resume/{document}/pdf` | token, branch | `PatientHistoryController@resumePdf` |
| GET | `/patients/{patient}/history/visits/{visit}` | token, branch | `PatientHistoryController@visit` |
| POST | `/patients/{patient}/satusehat` | token, branch, writes | `PatientController@fetchIhs` |
| GET | `/queues` | token, branch | `QueueController@index` |
| POST | `/queues/{queue}/call` | token, branch, writes | `QueueController@call` |
| POST | `/queues/{queue}/complete` | token, branch, writes | `QueueController@complete` |
| POST | `/queues/{queue}/skip` | token, branch, writes | `QueueController@skip` |
| GET | `/visits` | token, branch | `VisitController@index` |
| POST | `/visits` | token, branch, writes | `VisitController@store` |
| GET | `/visits/availability` | token, branch | `VisitController@availability` |
| POST | `/visits/patient-ihs` | token, branch, writes | `VisitController@patientIhs` |
| GET | `/visits/{visit}` | token, branch | `VisitController@show` |
| POST | `/visits/{visit}/cancel` | token, branch, writes | `VisitController@cancel` |
| GET | `/visits/{visit}/care` | token, branch, visit.tenant | `CareController@show` |
| POST | `/visits/{visit}/care/amendments` | token, branch, writes, visit.tenant | `CareController@storeAmendment` |
| PUT | `/visits/{visit}/care/anamnesis` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateAnamnesis` |
| POST | `/visits/{visit}/care/assessment/complete` | token, branch, writes, visit.tenant | `CareController@completeAssessment` |
| POST | `/visits/{visit}/care/complete` | token, branch, writes, visit.tenant | `CareController@complete` |
| PUT | `/visits/{visit}/care/dental-exam` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateDentalExam` |
| POST | `/visits/{visit}/care/diagnoses` | token, branch, writes, record.mutable, visit.tenant | `CareController@storeDiagnosis` |
| GET | `/visits/{visit}/care/diagnoses/search` | token, branch, visit.tenant | `CareController@searchDiagnoses` |
| DELETE | `/visits/{visit}/care/diagnoses/{diagnosis}` | token, branch, writes, record.mutable, visit.tenant | `CareController@destroyDiagnosis` |
| PUT | `/visits/{visit}/care/diagnoses/{diagnosis}` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateDiagnosis` |
| GET | `/visits/{visit}/care/discharge/pdf` | token, branch, visit.tenant | `CareController@pdfDischarge` |
| GET | `/visits/{visit}/care/documents/{document}/pdf` | token, branch, visit.tenant | `CareController@pdfDocument` |
| PUT | `/visits/{visit}/care/examination` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateExamination` |
| POST | `/visits/{visit}/care/finalize` | token, branch, writes, visit.tenant | `CareController@finalizeRecord` |
| PUT | `/visits/{visit}/care/follow-up` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateFollowUp` |
| GET | `/visits/{visit}/care/instructions/pdf` | token, branch, visit.tenant | `CareController@pdfInstructions` |
| POST | `/visits/{visit}/care/lab` | token, branch, writes, record.mutable, visit.tenant | `CareController@storeLab` |
| DELETE | `/visits/{visit}/care/lab/{order}` | token, branch, writes, record.mutable, visit.tenant | `CareController@destroyLab` |
| PUT | `/visits/{visit}/care/lab/{order}` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateLab` |
| PUT | `/visits/{visit}/care/notes` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateNotes` |
| POST | `/visits/{visit}/care/prescription-items` | token, branch, writes, record.mutable, visit.tenant | `CareController@storePrescriptionItem` |
| DELETE | `/visits/{visit}/care/prescription-items/{prescriptionItem}` | token, branch, writes, record.mutable, visit.tenant | `CareController@destroyPrescriptionItem` |
| PUT | `/visits/{visit}/care/prescription-items/{prescriptionItem}` | token, branch, writes, record.mutable, visit.tenant | `CareController@updatePrescriptionItem` |
| POST | `/visits/{visit}/care/prescription-templates` | token, branch, writes, record.mutable, visit.tenant | `CareController@storePrescriptionTemplate` |
| DELETE | `/visits/{visit}/care/prescription-templates/{prescriptionTemplate}` | token, branch, writes, record.mutable, visit.tenant | `CareController@destroyPrescriptionTemplate` |
| PUT | `/visits/{visit}/care/prescription-templates/{prescriptionTemplate}` | token, branch, writes, record.mutable, visit.tenant | `CareController@updatePrescriptionTemplate` |
| POST | `/visits/{visit}/care/prescription-templates/{prescriptionTemplate}/apply` | token, branch, writes, record.mutable, visit.tenant | `CareController@applyPrescriptionTemplate` |
| GET | `/visits/{visit}/care/prescriptions/pdf` | token, branch, visit.tenant | `CareController@pdfPrescriptions` |
| GET | `/visits/{visit}/care/prescriptions/{prescription}/pdf` | token, branch, visit.tenant | `CareController@pdfPrescription` |
| POST | `/visits/{visit}/care/prescriptions/{prescription}/send` | token, branch, writes, record.mutable, visit.tenant | `CareController@sendPrescription` |
| POST | `/visits/{visit}/care/prescriptions/{prescription}/template` | token, branch, writes, record.mutable, visit.tenant | `CareController@storeTemplateFromPrescription` |
| POST | `/visits/{visit}/care/procedures` | token, branch, writes, record.mutable, visit.tenant | `CareController@storeProcedure` |
| GET | `/visits/{visit}/care/procedures/icd9/search` | token, branch, visit.tenant | `CareController@searchIcd9` |
| GET | `/visits/{visit}/care/procedures/tariff` | token, branch, visit.tenant | `CareController@quoteProcedureTariff` |
| DELETE | `/visits/{visit}/care/procedures/{procedure_record}` | token, branch, writes, record.mutable, visit.tenant | `CareController@destroyProcedure` |
| PUT | `/visits/{visit}/care/procedures/{procedure_record}` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateProcedure` |
| POST | `/visits/{visit}/care/procedures/{procedure_record}/waiver/approve` | token, branch, writes, record.mutable, visit.tenant | `CareController@approveProcedureWaiver` |
| PUT | `/visits/{visit}/care/record` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateRecord` |
| POST | `/visits/{visit}/care/referrals` | token, branch, writes, record.mutable, visit.tenant | `CareController@storeReferral` |
| DELETE | `/visits/{visit}/care/referrals/{referral}` | token, branch, writes, record.mutable, visit.tenant | `CareController@destroyReferral` |
| PUT | `/visits/{visit}/care/referrals/{referral}` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateReferral` |
| GET | `/visits/{visit}/care/referrals/{referral}/pdf` | token, branch, visit.tenant | `CareController@pdfReferral` |
| GET | `/visits/{visit}/care/resume/pdf` | token, branch, visit.tenant | `CareController@pdfResume` |
| POST | `/visits/{visit}/care/sick-letter` | token, branch, writes, visit.tenant | `CareController@storeSickLetter` |
| PUT | `/visits/{visit}/care/systemic` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateSystemicHistory` |
| PUT | `/visits/{visit}/care/tooth` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateTooth` |
| DELETE | `/visits/{visit}/care/tooth/{toothNumber}` | token, branch, writes, record.mutable, visit.tenant | `CareController@destroyTooth` |
| PUT | `/visits/{visit}/care/vitals` | token, branch, writes, record.mutable, visit.tenant | `CareController@updateVitals` |
| POST | `/visits/{visit}/medical-letters` | token, branch, writes, visit.tenant | `MedicalLetterController@store` |
| POST | `/visits/{visit}/medical-letters/frame` | token, branch, writes, visit.tenant | `MedicalLetterController@frame` |
| POST | `/visits/{visit}/medical-letters/preview` | token, branch, writes, visit.tenant | `MedicalLetterController@preview` |
| GET | `/wilayah/cities` | token, branch | `WilayahController@cities` |
| GET | `/wilayah/districts` | token, branch | `WilayahController@districts` |
| GET | `/wilayah/from-nik` | token, branch | `WilayahController@fromNik` |
| GET | `/wilayah/villages` | token, branch | `WilayahController@villages` |

## Farmasi

Resep: `scope=incoming|processing|completed|history`. Label = PDF.

| Metode | Path | Auth | Controller |
| --- | --- | --- | --- |
| GET | `/pharmacy/lab` | token, branch | `PharmacyLabController@index` |
| PUT | `/pharmacy/lab/{order}` | token, branch, writes | `PharmacyLabController@update` |
| GET | `/pharmacy/medicines` | token, branch | `PharmacyReportController@medicines` |
| GET | `/pharmacy/orders` | token, branch | `PharmacyOrderController@index` |
| POST | `/pharmacy/orders` | token, branch, writes | `PharmacyOrderController@store` |
| GET | `/pharmacy/orders/{purchaseOrder}` | token, branch | `PharmacyOrderController@show` |
| GET | `/pharmacy/orders/{purchaseOrder}/receive` | token, branch | `PharmacyOrderController@receiveForm` |
| POST | `/pharmacy/orders/{purchaseOrder}/receive` | token, branch, writes | `PharmacyOrderController@receive` |
| POST | `/pharmacy/orders/{purchaseOrder}/submit` | token, branch, writes | `PharmacyOrderController@submit` |
| GET | `/pharmacy/prescriptions` | token, branch | `PharmacyPrescriptionController@index` |
| GET | `/pharmacy/prescriptions/{prescription}` | token, branch | `PharmacyPrescriptionController@show` |
| POST | `/pharmacy/prescriptions/{prescription}/fulfill` | token, branch, writes | `PharmacyPrescriptionController@fulfill` |
| GET | `/pharmacy/prescriptions/{prescription}/items/{item}/label` | token, branch | `PharmacyPrescriptionController@printItemLabel` |
| GET | `/pharmacy/prescriptions/{prescription}/labels` | token, branch | `PharmacyPrescriptionController@printLabels` |
| GET | `/pharmacy/receipts` | token, branch | `PharmacyOrderController@receipts` |
| GET | `/pharmacy/reports/expired` | token, branch | `PharmacyReportController@expired` |
| GET | `/pharmacy/reports/incoming` | token, branch | `PharmacyReportController@incoming` |
| GET | `/pharmacy/reports/outgoing` | token, branch | `PharmacyReportController@outgoing` |
| GET | `/pharmacy/reports/stock` | token, branch | `PharmacyReportController@stock` |
| GET | `/pharmacy/stock` | token, branch | `PharmacyStockController@index` |
| GET | `/pharmacy/stock/adjustments` | token, branch | `PharmacyStockController@adjustments` |
| POST | `/pharmacy/stock/adjustments` | token, branch, writes | `PharmacyStockController@storeAdjustment` |
| GET | `/pharmacy/stock/batches` | token, branch | `PharmacyStockController@batches` |
| GET | `/pharmacy/stock/card` | token, branch | `PharmacyStockController@card` |
| GET | `/pharmacy/stock/card/{medicine}` | token, branch | `PharmacyStockController@cardShow` |
| GET | `/pharmacy/stock/expired` | token, branch | `PharmacyStockController@expired` |
| GET | `/pharmacy/stock/opname` | token, branch | `PharmacyStockController@opname` |
| POST | `/pharmacy/stock/opname` | token, branch, writes | `PharmacyStockController@storeOpname` |
| GET | `/pharmacy/stock/returns` | token, branch | `PharmacyStockController@returns` |
| POST | `/pharmacy/stock/returns` | token, branch, writes | `PharmacyStockController@storeReturn` |
| GET | `/pharmacy/suppliers` | token, branch | `PharmacySupplierController@index` |
| POST | `/pharmacy/suppliers` | token, branch, writes | `PharmacySupplierController@store` |
| DELETE | `/pharmacy/suppliers/{supplier}` | token, branch, writes | `PharmacySupplierController@destroy` |
| GET | `/pharmacy/suppliers/{supplier}` | token, branch | `PharmacySupplierController@show` |
| PUT | `/pharmacy/suppliers/{supplier}` | token, branch, writes | `PharmacySupplierController@update` |
| GET | `/pharmacy/transactions` | token, branch | `PharmacyReportController@transactions` |

## Billing & kasir

Tagihan: `scope=today|history`. Bayar tanpa shift terbuka → 422. Print = PDF; receipt bisa HTML.

| Metode | Path | Auth | Controller |
| --- | --- | --- | --- |
| GET | `/billing/invoices` | token, branch | `BillingInvoiceController@index` |
| POST | `/billing/invoices/generate` | token, branch, writes | `BillingInvoiceController@generate` |
| GET | `/billing/invoices/search` | token, branch | `BillingInvoiceController@search` |
| GET | `/billing/invoices/today-patients` | token, branch | `BillingInvoiceController@todayPatients` |
| GET | `/billing/invoices/{invoice}` | token, branch | `BillingInvoiceController@show` |
| POST | `/billing/invoices/{invoice}/confirm` | token, branch, writes | `BillingInvoiceController@confirm` |
| POST | `/billing/invoices/{invoice}/medicines/{prescriptionItem}/decline` | token, branch, writes | `BillingInvoiceController@declineMedicine` |
| POST | `/billing/invoices/{invoice}/pay` | token, branch, writes | `BillingInvoiceController@pay` |
| GET | `/billing/invoices/{invoice}/print` | token, branch | `BillingInvoiceController@print` |
| GET | `/billing/invoices/{invoice}/receipt` | token, branch | `BillingInvoiceController@receipt` |
| POST | `/billing/invoices/{invoice}/refund` | token, branch, writes | `BillingInvoiceController@refund` |
| POST | `/billing/invoices/{invoice}/void` | token, branch, writes | `BillingInvoiceController@void` |
| GET | `/billing/payments` | token, branch | `BillingPaymentController@index` |
| POST | `/billing/payments` | token, branch, writes | `BillingPaymentController@store` |
| GET | `/billing/receivables` | token, branch | `BillingPaymentController@receivables` |
| GET | `/cashier/reports` | token, branch | `CashShiftController@reports` |
| GET | `/cashier/shifts/close` | token, branch | `CashShiftController@closeForm` |
| POST | `/cashier/shifts/close` | token, branch, writes | `CashShiftController@close` |
| GET | `/cashier/shifts/open` | token, branch | `CashShiftController@openForm` |
| POST | `/cashier/shifts/open` | token, branch, writes | `CashShiftController@open` |
| GET | `/cashier/shifts/transactions` | token, branch | `CashShiftController@transactions` |

## Indeks semua endpoint

| Metode | Path | Auth |
| --- | --- | --- |
| POST | `/auth/login` | publik |
| POST | `/auth/logout` | token |
| POST | `/auth/register` | publik, throttle:register |
| GET | `/billing/invoices` | token, branch |
| POST | `/billing/invoices/generate` | token, branch, writes |
| GET | `/billing/invoices/search` | token, branch |
| GET | `/billing/invoices/today-patients` | token, branch |
| GET | `/billing/invoices/{invoice}` | token, branch |
| POST | `/billing/invoices/{invoice}/confirm` | token, branch, writes |
| POST | `/billing/invoices/{invoice}/medicines/{prescriptionItem}/decline` | token, branch, writes |
| POST | `/billing/invoices/{invoice}/pay` | token, branch, writes |
| GET | `/billing/invoices/{invoice}/print` | token, branch |
| GET | `/billing/invoices/{invoice}/receipt` | token, branch |
| POST | `/billing/invoices/{invoice}/refund` | token, branch, writes |
| POST | `/billing/invoices/{invoice}/void` | token, branch, writes |
| GET | `/billing/payments` | token, branch |
| POST | `/billing/payments` | token, branch, writes |
| GET | `/billing/receivables` | token, branch |
| GET | `/cashier/reports` | token, branch |
| GET | `/cashier/shifts/close` | token, branch |
| POST | `/cashier/shifts/close` | token, branch, writes |
| GET | `/cashier/shifts/open` | token, branch |
| POST | `/cashier/shifts/open` | token, branch, writes |
| GET | `/cashier/shifts/transactions` | token, branch |
| GET | `/dashboard` | token, branch |
| GET | `/me` | token, branch:optional |
| GET | `/medical-letters` | token, branch |
| GET | `/medical-letters/{letter}` | token, branch |
| POST | `/medical-letters/{letter}/cancel` | token, branch, writes |
| GET | `/medical-letters/{letter}/pdf` | token, branch |
| POST | `/medical-letters/{letter}/revise` | token, branch, writes |
| GET | `/onboarding` | token, branch:optional |
| POST | `/onboarding/back` | token, branch:optional, writes |
| POST | `/onboarding/complete` | token, branch:optional, writes |
| POST | `/onboarding/steps/{step}` | token, branch:optional, writes |
| POST | `/onboarding/steps/{step}/skip` | token, branch:optional, writes |
| GET | `/patients` | token, branch |
| POST | `/patients` | token, branch, writes |
| GET | `/patients/search` | token, branch |
| DELETE | `/patients/{patient}` | token, branch, writes |
| GET | `/patients/{patient}` | token, branch |
| PUT | `/patients/{patient}` | token, branch, writes |
| GET | `/patients/{patient}/history` | token, branch |
| GET | `/patients/{patient}/history/prescriptions/{prescription}/pdf` | token, branch |
| POST | `/patients/{patient}/history/resume` | token, branch, writes |
| GET | `/patients/{patient}/history/resume/{document}/pdf` | token, branch |
| GET | `/patients/{patient}/history/visits/{visit}` | token, branch |
| POST | `/patients/{patient}/satusehat` | token, branch, writes |
| GET | `/pharmacy/lab` | token, branch |
| PUT | `/pharmacy/lab/{order}` | token, branch, writes |
| GET | `/pharmacy/medicines` | token, branch |
| GET | `/pharmacy/orders` | token, branch |
| POST | `/pharmacy/orders` | token, branch, writes |
| GET | `/pharmacy/orders/{purchaseOrder}` | token, branch |
| GET | `/pharmacy/orders/{purchaseOrder}/receive` | token, branch |
| POST | `/pharmacy/orders/{purchaseOrder}/receive` | token, branch, writes |
| POST | `/pharmacy/orders/{purchaseOrder}/submit` | token, branch, writes |
| GET | `/pharmacy/prescriptions` | token, branch |
| GET | `/pharmacy/prescriptions/{prescription}` | token, branch |
| POST | `/pharmacy/prescriptions/{prescription}/fulfill` | token, branch, writes |
| GET | `/pharmacy/prescriptions/{prescription}/items/{item}/label` | token, branch |
| GET | `/pharmacy/prescriptions/{prescription}/labels` | token, branch |
| GET | `/pharmacy/receipts` | token, branch |
| GET | `/pharmacy/reports/expired` | token, branch |
| GET | `/pharmacy/reports/incoming` | token, branch |
| GET | `/pharmacy/reports/outgoing` | token, branch |
| GET | `/pharmacy/reports/stock` | token, branch |
| GET | `/pharmacy/stock` | token, branch |
| GET | `/pharmacy/stock/adjustments` | token, branch |
| POST | `/pharmacy/stock/adjustments` | token, branch, writes |
| GET | `/pharmacy/stock/batches` | token, branch |
| GET | `/pharmacy/stock/card` | token, branch |
| GET | `/pharmacy/stock/card/{medicine}` | token, branch |
| GET | `/pharmacy/stock/expired` | token, branch |
| GET | `/pharmacy/stock/opname` | token, branch |
| POST | `/pharmacy/stock/opname` | token, branch, writes |
| GET | `/pharmacy/stock/returns` | token, branch |
| POST | `/pharmacy/stock/returns` | token, branch, writes |
| GET | `/pharmacy/suppliers` | token, branch |
| POST | `/pharmacy/suppliers` | token, branch, writes |
| DELETE | `/pharmacy/suppliers/{supplier}` | token, branch, writes |
| GET | `/pharmacy/suppliers/{supplier}` | token, branch |
| PUT | `/pharmacy/suppliers/{supplier}` | token, branch, writes |
| GET | `/pharmacy/transactions` | token, branch |
| GET | `/queues` | token, branch |
| POST | `/queues/{queue}/call` | token, branch, writes |
| POST | `/queues/{queue}/complete` | token, branch, writes |
| POST | `/queues/{queue}/skip` | token, branch, writes |
| GET | `/visits` | token, branch |
| POST | `/visits` | token, branch, writes |
| GET | `/visits/availability` | token, branch |
| POST | `/visits/patient-ihs` | token, branch, writes |
| GET | `/visits/{visit}` | token, branch |
| POST | `/visits/{visit}/cancel` | token, branch, writes |
| GET | `/visits/{visit}/care` | token, branch, visit.tenant |
| POST | `/visits/{visit}/care/amendments` | token, branch, writes, visit.tenant |
| PUT | `/visits/{visit}/care/anamnesis` | token, branch, writes, record.mutable, visit.tenant |
| POST | `/visits/{visit}/care/assessment/complete` | token, branch, writes, visit.tenant |
| POST | `/visits/{visit}/care/complete` | token, branch, writes, visit.tenant |
| PUT | `/visits/{visit}/care/dental-exam` | token, branch, writes, record.mutable, visit.tenant |
| POST | `/visits/{visit}/care/diagnoses` | token, branch, writes, record.mutable, visit.tenant |
| GET | `/visits/{visit}/care/diagnoses/search` | token, branch, visit.tenant |
| DELETE | `/visits/{visit}/care/diagnoses/{diagnosis}` | token, branch, writes, record.mutable, visit.tenant |
| PUT | `/visits/{visit}/care/diagnoses/{diagnosis}` | token, branch, writes, record.mutable, visit.tenant |
| GET | `/visits/{visit}/care/discharge/pdf` | token, branch, visit.tenant |
| GET | `/visits/{visit}/care/documents/{document}/pdf` | token, branch, visit.tenant |
| PUT | `/visits/{visit}/care/examination` | token, branch, writes, record.mutable, visit.tenant |
| POST | `/visits/{visit}/care/finalize` | token, branch, writes, visit.tenant |
| PUT | `/visits/{visit}/care/follow-up` | token, branch, writes, record.mutable, visit.tenant |
| GET | `/visits/{visit}/care/instructions/pdf` | token, branch, visit.tenant |
| POST | `/visits/{visit}/care/lab` | token, branch, writes, record.mutable, visit.tenant |
| DELETE | `/visits/{visit}/care/lab/{order}` | token, branch, writes, record.mutable, visit.tenant |
| PUT | `/visits/{visit}/care/lab/{order}` | token, branch, writes, record.mutable, visit.tenant |
| PUT | `/visits/{visit}/care/notes` | token, branch, writes, record.mutable, visit.tenant |
| POST | `/visits/{visit}/care/prescription-items` | token, branch, writes, record.mutable, visit.tenant |
| DELETE | `/visits/{visit}/care/prescription-items/{prescriptionItem}` | token, branch, writes, record.mutable, visit.tenant |
| PUT | `/visits/{visit}/care/prescription-items/{prescriptionItem}` | token, branch, writes, record.mutable, visit.tenant |
| POST | `/visits/{visit}/care/prescription-templates` | token, branch, writes, record.mutable, visit.tenant |
| DELETE | `/visits/{visit}/care/prescription-templates/{prescriptionTemplate}` | token, branch, writes, record.mutable, visit.tenant |
| PUT | `/visits/{visit}/care/prescription-templates/{prescriptionTemplate}` | token, branch, writes, record.mutable, visit.tenant |
| POST | `/visits/{visit}/care/prescription-templates/{prescriptionTemplate}/apply` | token, branch, writes, record.mutable, visit.tenant |
| GET | `/visits/{visit}/care/prescriptions/pdf` | token, branch, visit.tenant |
| GET | `/visits/{visit}/care/prescriptions/{prescription}/pdf` | token, branch, visit.tenant |
| POST | `/visits/{visit}/care/prescriptions/{prescription}/send` | token, branch, writes, record.mutable, visit.tenant |
| POST | `/visits/{visit}/care/prescriptions/{prescription}/template` | token, branch, writes, record.mutable, visit.tenant |
| POST | `/visits/{visit}/care/procedures` | token, branch, writes, record.mutable, visit.tenant |
| GET | `/visits/{visit}/care/procedures/icd9/search` | token, branch, visit.tenant |
| GET | `/visits/{visit}/care/procedures/tariff` | token, branch, visit.tenant |
| DELETE | `/visits/{visit}/care/procedures/{procedure_record}` | token, branch, writes, record.mutable, visit.tenant |
| PUT | `/visits/{visit}/care/procedures/{procedure_record}` | token, branch, writes, record.mutable, visit.tenant |
| POST | `/visits/{visit}/care/procedures/{procedure_record}/waiver/approve` | token, branch, writes, record.mutable, visit.tenant |
| PUT | `/visits/{visit}/care/record` | token, branch, writes, record.mutable, visit.tenant |
| POST | `/visits/{visit}/care/referrals` | token, branch, writes, record.mutable, visit.tenant |
| DELETE | `/visits/{visit}/care/referrals/{referral}` | token, branch, writes, record.mutable, visit.tenant |
| PUT | `/visits/{visit}/care/referrals/{referral}` | token, branch, writes, record.mutable, visit.tenant |
| GET | `/visits/{visit}/care/referrals/{referral}/pdf` | token, branch, visit.tenant |
| GET | `/visits/{visit}/care/resume/pdf` | token, branch, visit.tenant |
| POST | `/visits/{visit}/care/sick-letter` | token, branch, writes, visit.tenant |
| PUT | `/visits/{visit}/care/systemic` | token, branch, writes, record.mutable, visit.tenant |
| PUT | `/visits/{visit}/care/tooth` | token, branch, writes, record.mutable, visit.tenant |
| DELETE | `/visits/{visit}/care/tooth/{toothNumber}` | token, branch, writes, record.mutable, visit.tenant |
| PUT | `/visits/{visit}/care/vitals` | token, branch, writes, record.mutable, visit.tenant |
| POST | `/visits/{visit}/medical-letters` | token, branch, writes, visit.tenant |
| POST | `/visits/{visit}/medical-letters/frame` | token, branch, writes, visit.tenant |
| POST | `/visits/{visit}/medical-letters/preview` | token, branch, writes, visit.tenant |
| GET | `/wilayah/cities` | token, branch |
| GET | `/wilayah/districts` | token, branch |
| GET | `/wilayah/from-nik` | token, branch |
| GET | `/wilayah/villages` | token, branch |

---

*Sumber rute: `php artisan route:list --path=api/v1 --json`.*
