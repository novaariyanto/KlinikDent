# Pharmacy Medicine Label Print Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah cetak label DomPDF A6 per item obat (dan semua label) di detail resep farmasi `/pharmacy/prescriptions/{id}`.

**Architecture:** Dua route GET di modul farmasi; `PharmacyPrescriptionController` stream DomPDF dari view ringkas (bukan layout surat care). Auth sama `show`. UI: tombol per baris item + “Cetak semua label”.

**Tech Stack:** Laravel 10, DomPDF (`Barryvdh\DomPDF\Facade\Pdf`), Blade, `PrintChrome`, jQuery/Skote UI existing (`x-button`).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-11-11-pharmacy-medicine-label-print-design.md`
- Isi label minimal saja: klinik, pasien, nama obat, dosis, frekuensi, aturan pakai, qty
- Kertas: `setPaper('a6')` portrait
- Racikan: satu label via `$item->label()`; bahan tidak dijabarkan
- **Jangan** jalankan Pest/PHPUnit; verifikasi manual (route + PDF + UI)
- **Jangan** commit kecuali user meminta
- Ikuti pola modul farmasi/care yang ada; jangan browser automation kecuali diminta

## File map

| File | Tanggung jawab |
| --- | --- |
| `routes/web.php` | Daftarkan 2 GET label sebelum route `{prescription}` fulfill; letakkan **setelah** show agar tidak bentrok (path lebih spesifik boleh di atas `{prescription}` show) |
| `app/Http/Controllers/Clinical/PharmacyPrescriptionController.php` | `printLabels`, `printItemLabel`, helper data chrome |
| `resources/views/clinical/pharmacy/print/label.blade.php` | Markup satu label |
| `resources/views/clinical/pharmacy/print/labels.blade.php` | Multi-page: include/loop label + page-break |
| `resources/views/clinical/pharmacy/show.blade.php` | Tombol UI |

---

### Task 1: View Blade label A6

**Files:**
- Create: `resources/views/clinical/pharmacy/print/label.blade.php`
- Create: `resources/views/clinical/pharmacy/print/labels.blade.php`

**Interfaces:**
- Consumes: `$clinicName` (string), `$patientName` (string), `$item` (`PrescriptionItem`), optional `$pageBreak` (bool)
- Produces: HTML DomPDF-ready untuk satu/multi label

- [ ] **Step 1: Buat partial/satu-label view**

Buat `resources/views/clinical/pharmacy/print/label.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 6mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
            margin: 0;
        }
        .clinic { font-size: 8px; text-transform: uppercase; letter-spacing: 0.02em; margin-bottom: 4px; }
        .patient { font-size: 10px; font-weight: bold; margin-bottom: 6px; }
        .drug { font-size: 12px; font-weight: bold; margin-bottom: 6px; line-height: 1.25; }
        .row { margin-bottom: 3px; }
        .muted { color: #555; }
    </style>
</head>
<body>
    <div class="clinic">{{ $clinicName }}</div>
    <div class="patient">{{ $patientName }}</div>
    <div class="drug">{{ $item->label() }}</div>
    <div class="row"><span class="muted">Dosis</span> {{ $item->dosage ?: '-' }}</div>
    <div class="row"><span class="muted">Frekuensi</span> {{ $item->frequency ?: '-' }}</div>
    <div class="row"><span class="muted">Aturan pakai</span> {{ $item->duration ?: '-' }}</div>
    <div class="row"><span class="muted">Qty</span> {{ $item->quantity }}{{ $item->isCompound() ? ' bks' : '' }}</div>
</body>
</html>
```

- [ ] **Step 2: Buat view multi-label**

Buat `resources/views/clinical/pharmacy/print/labels.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 6mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
            margin: 0;
        }
        .label-page { page-break-after: always; }
        .label-page:last-child { page-break-after: auto; }
        .clinic { font-size: 8px; text-transform: uppercase; letter-spacing: 0.02em; margin-bottom: 4px; }
        .patient { font-size: 10px; font-weight: bold; margin-bottom: 6px; }
        .drug { font-size: 12px; font-weight: bold; margin-bottom: 6px; line-height: 1.25; }
        .row { margin-bottom: 3px; }
        .muted { color: #555; }
    </style>
</head>
<body>
@foreach ($items as $item)
    <div class="label-page">
        <div class="clinic">{{ $clinicName }}</div>
        <div class="patient">{{ $patientName }}</div>
        <div class="drug">{{ $item->label() }}</div>
        <div class="row"><span class="muted">Dosis</span> {{ $item->dosage ?: '-' }}</div>
        <div class="row"><span class="muted">Frekuensi</span> {{ $item->frequency ?: '-' }}</div>
        <div class="row"><span class="muted">Aturan pakai</span> {{ $item->duration ?: '-' }}</div>
        <div class="row"><span class="muted">Qty</span> {{ $item->quantity }}{{ $item->isCompound() ? ' bks' : '' }}</div>
    </div>
@endforeach
</body>
</html>
```

- [ ] **Step 3: Verifikasi file ada**

Run: `php -r "echo file_exists('resources/views/clinical/pharmacy/print/label.blade.php') && file_exists('resources/views/clinical/pharmacy/print/labels.blade.php') ? 'ok' : 'missing';"`
Expected: `ok`

---

### Task 2: Controller + routes

**Files:**
- Modify: `app/Http/Controllers/Clinical/PharmacyPrescriptionController.php`
- Modify: `routes/web.php` (blok pharmacy prescriptions ~387–392)

**Interfaces:**
- Consumes: `authorizePrescription`, `PrintChrome::variables`, `Pdf::loadView`
- Produces:
  - `printLabels(Request $request, Prescription $prescription): \Illuminate\Http\Response`
  - `printItemLabel(Request $request, Prescription $prescription, PrescriptionItem $item): \Illuminate\Http\Response`
  - Routes: `pharmacy.prescriptions.labels`, `pharmacy.prescriptions.item-label`

- [ ] **Step 1: Tambah import & method di controller**

Di `PharmacyPrescriptionController.php` tambahkan:

```php
use App\Models\PrescriptionItem;
use App\Support\Clinical\PrintChrome;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
```

Tambahkan method (setelah `show`, sebelum `fulfill`):

```php
public function printLabels(Request $request, Prescription $prescription): Response
{
    $this->authorizePrescription($request->user(), $prescription, viewOnly: true);

    $prescription->load([
        'visit.patient',
        'visit.branch',
        'items.medicine',
    ]);

    abort_unless($prescription->items->isNotEmpty(), 422, 'Resep belum memiliki item obat.');

    $visit = $prescription->visit;
    $chrome = PrintChrome::variables((int) $visit->tenant_id, $visit->branch);

    activity_log('printed', $prescription, ['scope' => 'all_labels'], 'Cetak semua label obat farmasi', 'prescriptions');

    return Pdf::loadView('clinical.pharmacy.print.labels', [
        'clinicName' => $chrome['clinicName'],
        'patientName' => (string) ($visit->patient?->name ?: '-'),
        'items' => $prescription->items,
    ])->setPaper('a6')->stream('label-resep-'.$prescription->id.'.pdf');
}

public function printItemLabel(Request $request, Prescription $prescription, PrescriptionItem $item): Response
{
    $this->authorizePrescription($request->user(), $prescription, viewOnly: true);
    abort_unless((int) $item->prescription_id === (int) $prescription->id, 404);

    $prescription->load(['visit.patient', 'visit.branch']);
    $item->loadMissing('medicine');

    $visit = $prescription->visit;
    $chrome = PrintChrome::variables((int) $visit->tenant_id, $visit->branch);

    activity_log('printed', $item, ['prescription_id' => $prescription->id], 'Cetak label obat farmasi', 'prescriptions');

    return Pdf::loadView('clinical.pharmacy.print.label', [
        'clinicName' => $chrome['clinicName'],
        'patientName' => (string) ($visit->patient?->name ?: '-'),
        'item' => $item,
    ])->setPaper('a6')->stream('label-resep-'.$prescription->id.'-item-'.$item->id.'.pdf');
}
```

- [ ] **Step 2: Daftarkan routes**

Di `routes/web.php`, **sebelum** route `pharmacy.prescriptions.show` (agar path `.../labels` tidak tertangkap sebagai id), sisipkan:

```php
Route::get('/pharmacy/prescriptions/{prescription}/labels', [PharmacyPrescriptionController::class, 'printLabels'])->name('pharmacy.prescriptions.labels');
Route::get('/pharmacy/prescriptions/{prescription}/items/{item}/label', [PharmacyPrescriptionController::class, 'printItemLabel'])->name('pharmacy.prescriptions.item-label');
```

Letakkan tepat di atas:

```php
Route::get('/pharmacy/prescriptions/{prescription}', ...)->name('pharmacy.prescriptions.show');
```

- [ ] **Step 3: Cek sintaks & route list**

Run:

```bash
php -l app/Http/Controllers/Clinical/PharmacyPrescriptionController.php
php artisan route:list --name=pharmacy.prescriptions
```

Expected: tidak ada syntax error; muncul `pharmacy.prescriptions.labels` dan `pharmacy.prescriptions.item-label`.

---

### Task 3: UI tombol di detail resep

**Files:**
- Modify: `resources/views/clinical/pharmacy/show.blade.php`

**Interfaces:**
- Consumes: `route('pharmacy.prescriptions.labels', $prescription)`, `route('pharmacy.prescriptions.item-label', [$prescription, $item])`
- Produces: tombol toolbar + kolom Label per item utama

- [ ] **Step 1: Tambah kolom Label di thead**

Setelah kolom Qty (dan sebelum stok jika ada), atau di akhir sebelum stok:

```blade
<th class="text-end">Label</th>
```

Sesuaikan `colspan` pada baris bahan racikan jika perlu (tambah 1 kolom kosong / text-end).

- [ ] **Step 2: Tombol per item di tbody**

Pada baris `$item` (bukan ingredient), di sel Label:

```blade
<td class="text-end">
    <a href="{{ route('pharmacy.prescriptions.item-label', [$prescription, $item]) }}"
       target="_blank" rel="noopener"
       class="btn btn-sm btn-light border"
       title="Cetak label"
       aria-label="Cetak label {{ $item->label() }}">
        <i class="bx bx-printer"></i>
    </a>
</td>
```

Pada baris ingredient, tambah `<td></td>` agar kolom selaras.

- [ ] **Step 3: Tombol Cetak semua label di footer aksi**

Di blok `mt-3 d-flex flex-wrap gap-2`, sebelum Kembali (jika items tidak kosong):

```blade
@if ($prescription->items->isNotEmpty())
    <x-button
        href="{{ route('pharmacy.prescriptions.labels', $prescription) }}"
        variant="secondary"
        icon="bx bx-printer"
        target="_blank"
        rel="noopener"
    >Cetak semua label</x-button>
@endif
```

Jika `x-button` tidak meneruskan `target`/`rel`, pakai `<a class="btn btn-secondary">` mengikuti pola cetak di care.

- [ ] **Step 4: Verifikasi manual**

1. Buka `/pharmacy/prescriptions/{id}` (resep Sent/Fulfilled ber-item).
2. Klik ikon printer satu obat → PDF A6 satu label, field minimal benar.
3. Klik “Cetak semua label” → PDF multi-halaman.
4. Pastikan status resep / stok tidak berubah.

---

## Spec coverage (self-review)

| Spec | Task |
| --- | --- |
| DomPDF A6 | Task 2 `setPaper('a6')` |
| Isi minimal | Task 1 views |
| Per item + semua | Task 2 routes + Task 3 UI |
| Racikan satu label | Task 1 `$item->label()` |
| Auth sama show | Task 2 `authorizePrescription` |
| Item asing → 404 | Task 2 `abort_unless` prescription_id |
| Tidak ubah stok/status | Tidak ada update di method print |

Tidak ada placeholder TBD. Commit sengaja dihilangkan (constraint proyek).
