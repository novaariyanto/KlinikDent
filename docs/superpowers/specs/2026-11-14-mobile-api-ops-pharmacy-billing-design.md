# API Farmasi dan Kasir (Operator)

Perluasan API mobile operator: semua aksi farmasi dan billing yang ada di web. Fondasi auth ada di `docs/superpowers/specs/2026-11-14-mobile-api-foundation-design.md`. Meja klinis ada di `docs/superpowers/specs/2026-11-14-mobile-api-clinical-desk-design.md`. Dashboard ringkasan ada di `docs/superpowers/specs/2026-11-14-mobile-api-dashboard-design.md`. Prompt Flutter ada di `docs/superpowers/specs/2026-11-14-flutter-mobile-prompt.md`.

## Keputusan

- Endpoint ditambahkan ke API operator yang sama (`/api/v1`), bukan aplikasi terpisah. Role `clinic-ops`, kasir, farmasi, dan role lain memakai endpoint lewat izin Spatie yang sama dengan web.
- Controller API baru di `App\Http\Controllers\Api`. FormRequest dan service web (`BillingService`, `PharmacyStockService`, dll.) yang mengerjakan aturan bisnis.
- Cabang wajib: `auth:sanctum`, `mobile.access`, `mobile.branch`. Penulisan menambah `mobile.writes`.
- PDF label obat, nota, dan kwitansi adalah badan `application/pdf`.
- Dashboard HTML farmasi/billing tidak dibuatkan sebagai JSON dashboard terpisah.
- Verifikasi manual lewat HTTP. Tidak menambah file Pest atau PHPUnit.

## Rute farmasi

### Resep

| Metode | Path | Padanan web |
| --- | --- | --- |
| GET | `/pharmacy/prescriptions` | `pharmacy.prescriptions.incoming` dll. lewat `scope` |
| GET | `/pharmacy/prescriptions/{prescription}` | `pharmacy.prescriptions.show` |
| POST | `/pharmacy/prescriptions/{prescription}/fulfill` | `pharmacy.prescriptions.fulfill` |
| GET | `/pharmacy/prescriptions/{prescription}/labels` | `pharmacy.prescriptions.labels` (PDF) |
| GET | `/pharmacy/prescriptions/{prescription}/items/{item}/label` | `pharmacy.prescriptions.item-label` (PDF) |

`scope=incoming|processing|completed|history`.

### Stok

| Metode | Path | Padanan web |
| --- | --- | --- |
| GET | `/pharmacy/stock` | `pharmacy.stock.index` |
| GET | `/pharmacy/stock/batches` | `pharmacy.stock.batches` |
| GET | `/pharmacy/stock/expired` | `pharmacy.stock.expired` |
| GET | `/pharmacy/stock/adjustments` | `pharmacy.stock.adjustments` |
| POST | `/pharmacy/stock/adjustments` | `pharmacy.stock.adjustments.store` |
| GET | `/pharmacy/stock/card` | `pharmacy.stock.card` |
| GET | `/pharmacy/stock/card/{medicine}` | `pharmacy.stock.card.show` |
| GET | `/pharmacy/stock/opname` | `pharmacy.stock.opname` |
| POST | `/pharmacy/stock/opname` | `pharmacy.stock.opname.store` |
| GET | `/pharmacy/stock/returns` | `pharmacy.stock.returns` |
| POST | `/pharmacy/stock/returns` | `pharmacy.stock.returns.store` |

### Lab, supplier, PO, laporan

| Metode | Path | Padanan web |
| --- | --- | --- |
| GET | `/pharmacy/lab` | `pharmacy.lab.index` |
| PUT | `/pharmacy/lab/{order}` | `pharmacy.lab.update` |
| GET | `/pharmacy/suppliers` | `pharmacy.purchases.suppliers` |
| POST | `/pharmacy/suppliers` | `pharmacy.suppliers.store` |
| GET | `/pharmacy/suppliers/{supplier}` | show/edit data |
| PUT | `/pharmacy/suppliers/{supplier}` | `pharmacy.suppliers.update` |
| DELETE | `/pharmacy/suppliers/{supplier}` | `pharmacy.suppliers.destroy` |
| GET | `/pharmacy/orders` | `pharmacy.purchases.orders` |
| POST | `/pharmacy/orders` | `pharmacy.orders.store` |
| GET | `/pharmacy/orders/{purchaseOrder}` | `pharmacy.orders.show` |
| POST | `/pharmacy/orders/{purchaseOrder}/submit` | `pharmacy.orders.submit` |
| GET | `/pharmacy/orders/{purchaseOrder}/receive` | form data terima |
| POST | `/pharmacy/orders/{purchaseOrder}/receive` | `pharmacy.orders.receive.store` |
| GET | `/pharmacy/receipts` | `pharmacy.purchases.receipts` |
| GET | `/pharmacy/transactions` | `pharmacy.transactions` |
| GET | `/pharmacy/reports/stock` | `pharmacy.reports.stock` |
| GET | `/pharmacy/reports/outgoing` | `pharmacy.reports.outgoing` |
| GET | `/pharmacy/reports/incoming` | `pharmacy.reports.incoming` |
| GET | `/pharmacy/reports/expired` | `pharmacy.reports.expired` |
| GET | `/pharmacy/medicines` | `pharmacy.medicines.index` |

## Rute kasir / billing

| Metode | Path | Padanan web |
| --- | --- | --- |
| GET | `/billing/invoices` | `billing.invoices` / today / history lewat `scope` |
| GET | `/billing/invoices/search` | `billing.invoices.search` |
| GET | `/billing/invoices/today-patients` | `billing.invoices.today-patients` |
| POST | `/billing/invoices/generate` | `billing.invoices.generate` |
| GET | `/billing/invoices/{invoice}` | `billing.invoices.show` |
| POST | `/billing/invoices/{invoice}/confirm` | `billing.invoices.confirm` |
| POST | `/billing/invoices/{invoice}/medicines/{prescriptionItem}/decline` | `billing.invoices.medicines.decline` |
| POST | `/billing/invoices/{invoice}/pay` | `billing.invoices.pay` |
| POST | `/billing/invoices/{invoice}/refund` | `billing.invoices.refund` |
| POST | `/billing/invoices/{invoice}/void` | `billing.invoices.void` |
| GET | `/billing/invoices/{invoice}/print` | `billing.invoices.print` (PDF) |
| GET | `/billing/invoices/{invoice}/receipt` | `billing.invoices.receipt` (PDF) |
| GET | `/billing/payments` | `billing.payments` |
| POST | `/billing/payments` | `billing.payments.store` |
| GET | `/billing/receivables` | `billing.receivables` |

`scope=today|history` pada daftar tagihan.

### Shift kasir

| Metode | Path | Padanan web |
| --- | --- | --- |
| GET | `/cashier/shifts/open` | state form buka / shift aktif |
| POST | `/cashier/shifts/open` | `cashier.shifts.open.store` |
| GET | `/cashier/shifts/close` | state form tutup |
| POST | `/cashier/shifts/close` | `cashier.shifts.close.store` |
| GET | `/cashier/shifts/transactions` | `cashier.shifts.transactions` |
| GET | `/cashier/reports` | `cashier.reports` |

## Respons

JSON sukses di `data`. Daftar: `page`, `per_page` (bawaan 20, maksimum 50), `meta` (`current_page`, `last_page`, `total`).

Penulisan mengembalikan objek yang diubah plus `message` yang sama dengan web. PDF adalah `application/pdf`.

GET form buka/tutup shift mengembalikan state shift aktif atau null, bukan HTML.

Daftar dibatasi cabang aktif. Rahasia integrasi dan `queue_monitor_token` tidak dikirim.

## Error

Validasi field memakai 422 FormRequest yang sudah ada. Aturan billing/farmasi (termasuk bayar tanpa shift terbuka) menjawab 422 dengan pesan service web.

| Situasi | HTTP | code |
| --- | --- | --- |
| Token kosong, salah, atau kedaluwarsa | 401 | — |
| Izin tidak dimiliki | 403 | `forbidden` |
| Cabang di luar izin atau milik tenant lain | 403 | `branch_forbidden` |
| Cabang nonaktif | 403 | `branch_inactive` |
| Tidak ada cabang aktif | 403 | `branch_required` |
| Tulisan saat langganan ditangguhkan | 403 | `tenant_suspended` |
| Resep, tagihan, PO, supplier di luar tenant | 404 | — |
| PDF tidak ditemukan | 404 | — |

## Komponen

- Tambahan route di `routes/api.php` di dalam `mobile.branch`
- `App\Http\Controllers\Api\PharmacyPrescriptionController`
- `App\Http\Controllers\Api\PharmacyStockController`
- `App\Http\Controllers\Api\PharmacyLabController`
- `App\Http\Controllers\Api\PharmacySupplierController`
- `App\Http\Controllers\Api\PharmacyOrderController`
- `App\Http\Controllers\Api\PharmacyReportController`
- `App\Http\Controllers\Api\BillingInvoiceController`
- `App\Http\Controllers\Api\BillingPaymentController`
- `App\Http\Controllers\Api\CashShiftController`
- Resource JSON di `App\Http\Resources\Api` bila daftar/detail perlu bentuk stabil

Controller API memanggil service dan FormRequest yang sudah dipakai controller web. Activity log tetap dicatat. Aturan validasi tidak disalin.

## Verifikasi manual

- Resep `scope=incoming|processing|completed|history` mengikuti web. Detail, fulfill, label PDF `application/pdf`.
- Stok, batch, kadaluarsa, penyesuaian, kartu, opname, retur, lab, supplier, PO (buat/kirim/terima), laporan stok/masuk/keluar/kadaluarsa, dan daftar obat memakai aturan web.
- Tagihan `scope=today|history`, search, today-patients, generate, confirm, decline obat, pay, refund, void, PDF nota/kwitansi.
- Shift: buka, transaksi, tutup, laporan. Bayar tanpa shift terbuka 422 seperti web.
- Role tanpa izin 403 `forbidden`. Tenant `suspended` boleh baca; tulisan `tenant_suspended`.
