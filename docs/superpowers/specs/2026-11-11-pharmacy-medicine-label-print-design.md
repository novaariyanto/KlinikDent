# Cetak label obat farmasi

Tanggal: 2026-11-11  
Status: draft untuk review  
Halaman: `/pharmacy/prescriptions/{prescription}`

## Tujuan

Farmasi bisa mencetak **label per item obat** (dan semua label sekaligus) dari detail resep masuk/selesai, format DomPDF kecil (A6), isi minimal untuk ditempel pada wadah obat.

## Keputusan yang disepakati

| Aspek | Pilihan |
| --- | --- |
| Media | DomPDF (bukan browser print / thermal khusus) |
| Ukuran kertas | A6 portrait (`setPaper('a6')`) |
| Isi | Minimal: nama klinik, nama pasien, nama obat, dosis, frekuensi, aturan pakai, qty |
| UX | Tombol cetak **per baris** + **Cetak semua label** |
| Racikan | Satu label memakai nama racikan (`label()`); bahan tidak dijabarkan |
| Otorisasi | Sama dengan `pharmacy.prescriptions.show` (`prescription.view` + `pharmacy.view` + tenant/branch) |

## Non-tujuan

- Tidak mengganti cetak resep dokter A5 di care/riwayat.
- Tidak menambah KFA, dokter, No. RM, course, atau tipe NC/EP/SD pada label (versi minimal).
- Tidak memotong stok / mengubah status resep saat cetak.
- Tidak ada unit test otomatis (kecuali diminta kemudian).

## Desain teknis

### Routes

Di grup route farmasi yang sudah ada:

- `GET /pharmacy/prescriptions/{prescription}/labels` → `pharmacy.prescriptions.labels` (semua item)
- `GET /pharmacy/prescriptions/{prescription}/items/{item}/label` → `pharmacy.prescriptions.item-label` (satu item)

`{item}` harus milik `{prescription}` (`abort_unless` visit/prescription relation).

### Controller

`PharmacyPrescriptionController`:

- `printLabels(Prescription $prescription): Response`
- `printItemLabel(Prescription $prescription, PrescriptionItem $item): Response`

Keduanya:

1. `authorizePrescription(..., viewOnly: true)`
2. Load `visit.patient`, `visit.branch`, `items.medicine` (atau satu item)
3. Abort 422 jika tidak ada item (untuk semua) / item kosong tidak relevan
4. `Pdf::loadView(...)->setPaper('a6')->stream(...)`
5. Opsional: `activity_log('printed', ...)` mirip cetak resep care

Data view memakai `PrintChrome::variables(tenant, branch)` untuk nama klinik saja (bukan kop surat penuh).

### Views

- `resources/views/clinical/pharmacy/print/label.blade.php` — satu halaman label
- `resources/views/clinical/pharmacy/print/labels.blade.php` — loop item; tiap item = satu “page” (CSS page-break)

Layout label ringkas (bukan `clinical.care.print.layout`):

- Nama klinik (teks, tanpa logo besar)
- Nama pasien
- Nama obat
- Dosis · Frekuensi
- Aturan pakai
- Qty

### UI detail resep

Pada `clinical/pharmacy/show.blade.php`:

- Header/toolbar card: tombol **Cetak semua label** (`target="_blank"`) jika `items` tidak kosong
- Kolom aksi **Label** per baris item obat utama (bukan baris bahan racikan): ikon printer → route item-label

## Alur

```text
Detail resep → Cetak semua / Cetak per item
  → authorize + load
  → DomPDF A6
  → stream di tab baru
```

## Risiko & mitigasi

| Risiko | Mitigasi |
| --- | --- |
| DomPDF A6 terlalu sempit untuk nama panjang | Font kecil + wrap; nama obat prioritas |
| Racikan tanpa detail bahan membingungkan | Disepakati isi minimal; bisa ditambah di iterasi berikutnya |
| User tanpa izin farmasi | Auth sama show |

## Acceptance

- Dari detail resep Sent/Fulfilled, tombol per obat membuka PDF satu label A6 dengan field minimal.
- “Cetak semua label” membuka PDF berisi satu halaman per obat.
- Item milik resep lain → 404.
- User tanpa `pharmacy.view` → 403.
- Tidak mengubah status/stok resep.
