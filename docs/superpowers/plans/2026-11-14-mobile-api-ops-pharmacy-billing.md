# API Farmasi dan Kasir (Operator) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah endpoint `/api/v1` farmasi dan billing lengkap ke API operator, dengan aturan bisnis yang sama seperti web.

**Architecture:** Controller di `App\Http\Controllers\Api` memakai FormRequest dan service web (`BillingService`, `PharmacyStockService`, dll.). Rute masuk grup `mobile.branch` yang sudah ada; tulisan di `mobile.writes`. PDF mengembalikan response DomPDF/Blade print yang sama dengan web.

**Tech Stack:** Laravel 12, Sanctum, FormRequest/service billing & farmasi yang sudah ada, DomPDF.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-11-14-mobile-api-ops-pharmacy-billing-design.md`
- Endpoint ikut API operator yang sama; jangan buat guard/app terpisah
- Izin sama dengan route web padanan (`pharmacy.*`, `billing.*`, `cash_shift.*`)
- `mobile.branch` wajib; tulisan menambah `mobile.writes`
- PDF `application/pdf` (kecuali receipt web yang HTML — API harus PDF atau HTML stream yang sama dengan web; ikuti method web)
- Jangan menambah atau menjalankan Pest/PHPUnit
- Jangan commit kecuali user meminta
- Jangan merusak rute fondasi atau meja klinis yang sudah ada
- Jangan menampilkan password, token, atau rahasia

## File map

| File | Tanggung jawab |
| --- | --- |
| `app/Http/Controllers/Api/PharmacyPrescriptionController.php` | Daftar/detail/fulfill/label |
| `app/Http/Controllers/Api/PharmacyStockController.php` | Stok, batch, opname, retur |
| `app/Http/Controllers/Api/PharmacyLabController.php` | Lab farmasi |
| `app/Http/Controllers/Api/PharmacySupplierController.php` | Supplier |
| `app/Http/Controllers/Api/PharmacyOrderController.php` | PO dan penerimaan |
| `app/Http/Controllers/Api/PharmacyReportController.php` | Laporan + transactions + medicines |
| `app/Http/Controllers/Api/BillingInvoiceController.php` | Tagihan |
| `app/Http/Controllers/Api/BillingPaymentController.php` | Pembayaran & piutang |
| `app/Http/Controllers/Api/CashShiftController.php` | Shift kasir |
| `routes/api.php` | Daftarkan semua path spec |

Pola respons: pakai `RespondsWithPage` untuk daftar. Penulisan `{ data, message }`. Authorize seperti web. Prefer cabang aktif dari `request->attributes->get('active_branch')` bila method web memakai `branch_id`.

---

### Task 1: Resep farmasi

**Files:**
- Create: `app/Http/Controllers/Api/PharmacyPrescriptionController.php`
- Modify: `routes/api.php`
- Possibly extract list query from `Clinical\PharmacyPrescriptionController`

**Interfaces:**
- Consumes: `PharmacyStockService` (fulfill), print label methods web
- Produces: GET list/show, POST fulfill, GET labels PDF

- [ ] **Step 1: Daftar dan detail**

Baca `Clinical\PharmacyPrescriptionController` untuk filter incoming/processing/completed/history. Jadikan query reusable (public method atau `PharmacyPrescriptionQuery`). API `index` memakai `scope` query. Field daftar mengikuti kolom tabel web (id, nomor, pasien, dokter, status, tanggal).

`show` memuat item yang sama dengan web show.

- [ ] **Step 2: Fulfill dan label**

`fulfill` menyalin `PharmacyPrescriptionController::fulfill` (authorize, service, activity) lalu JSON.

`printLabels` / `printItemLabel` return Response PDF web apa adanya. Pastikan item milik prescription.

- [ ] **Step 3: Rute**

Dalam `mobile.branch`:

```php
Route::get('pharmacy/prescriptions', ...);
Route::get('pharmacy/prescriptions/{prescription}', ...);
Route::get('pharmacy/prescriptions/{prescription}/labels', ...);
Route::get('pharmacy/prescriptions/{prescription}/items/{item}/label', ...);
Route::middleware('mobile.writes')->group(function () {
    Route::post('pharmacy/prescriptions/{prescription}/fulfill', ...);
});
```

- [ ] **Step 4: Verifikasi**

`php artisan route:list --path=api/v1/pharmacy/prescriptions`. Satu GET list dengan token yang punya `pharmacy.view`. Role tanpa izin 403 `forbidden`.

---

### Task 2: Stok farmasi

**Files:**
- Create: `app/Http/Controllers/Api/PharmacyStockController.php`
- Modify: `routes/api.php`, mungkin `Pharmacy\StockController` bila perlu public helper

**Interfaces:**
- Consumes: `PharmacyStockService`, FormRequest adjustment/opname/return yang sudah ada
- Produces: semua path `/pharmacy/stock/*` di spec

- [ ] **Step 1: Baca**

Implementasikan index, batches, expired, adjustments list, card, cardShow, opname form state, returns list dengan data yang view web pakai, dibungkus `data` / `paged`.

- [ ] **Step 2: Tulis**

POST adjustments, opname, returns: FormRequest web + service yang sama. `mobile.writes`.

- [ ] **Step 3: Verifikasi**

GET stock dan satu POST adjustment uji (atau dry-run validasi 422) tanpa merusak stok produksi; kembalikan data uji bila mengubah.

---

### Task 3: Lab, supplier, PO, laporan

**Files:**
- Create: `PharmacyLabController`, `PharmacySupplierController`, `PharmacyOrderController`, `PharmacyReportController` di `App\Http\Controllers\Api`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `DentalLabController` update farmasi, `SupplierController`, `PurchaseOrderController`, `PharmacyReportController`, `MedicineController` index data
- Produces: path lab/suppliers/orders/receipts/transactions/reports/medicines di spec

- [x] **Step 1: Lab dan supplier CRUD**

JSON list/detail; tulis lewat FormRequest web. DELETE supplier menolak bila aturan web menolak.

- [x] **Step 2: PO**

List, store, show, submit, receive form (JSON field yang form web butuhkan), receive store, receipts list.

- [x] **Step 3: Laporan dan medicines**

GET reports dan transactions mengembalikan baris yang sama dengan filter web (bukan HTML). Medicines list paginated.

- [x] **Step 4: Verifikasi**

`route:list --path=api/v1/pharmacy`. Supplier list 200. Role tanpa `purchase.view` / `stock.view` sesuai permission web mendapat 403.

---

### Task 4: Tagihan

**Files:**
- Create: `app/Http/Controllers/Api/BillingInvoiceController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `BillingService`, `CashierDesk`, FormRequest generate/pay/confirm/decline/refund/void
- Produces: semua path `/billing/invoices*` di spec

- [x] **Step 1: Baca**

`index` dengan `scope=today|history`. `search` dan `todayPatients` membungkus JSON web di `data` bila belum. `show` field detail web.

`print` return PDF web. `receipt`: jika web mengembalikan View HTML, API stream HTML yang sama atau PDF bila method PDF ada — ikuti method `receipt` web tanpa mengubah perilaku web.

- [x] **Step 2: Tulis**

generate, confirm, declineMedicine, pay, refund, void — panggil service/method yang sama dengan `Billing\InvoiceController`, activity log, JSON `{ data, message }`.

Bayar tanpa shift terbuka harus 422 pesan web.

- [x] **Step 3: Rute dan verifikasi**

Tulis di `mobile.writes`. Uji list today + show. Jangan void tagihan nyata kecuali uji yang dibuat khusus lalu dibersihkan.

---

### Task 5: Pembayaran dan shift kasir

**Files:**
- Create: `app/Http/Controllers/Api/BillingPaymentController.php`, `app/Http/Controllers/Api/CashShiftController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `Billing\PaymentController`, `BillingService::openShift/closeShift/currentShift`, `OpenShiftRequest`, `CloseShiftRequest`
- Produces: payments, receivables, cashier shifts paths

- [x] **Step 1: Payments**

index, store, receivables — mirror web authorize + service.

- [x] **Step 2: Shift**

GET open/close: JSON state (`current` shift atau null, opening balance hints). POST open/close: FormRequest + `BillingService`. transactions dan reports: daftar paginated / ringkasan yang view web pakai.

- [x] **Step 3: Verifikasi**

GET current shift. Buka shift uji hanya jika belum ada; tutup lagi. Bayar invoice uji membutuhkan shift terbuka. Tenant suspended: GET invoices 200, POST pay 403 `tenant_suspended`.

---

## Self-review

- Semua path di spec tercakup Task 1–5.
- Dashboard HTML farmasi/billing tidak ada task-nya.
- Pola sama meja klinis: thin API, service web, `RespondsWithPage`, cabang aktif.
