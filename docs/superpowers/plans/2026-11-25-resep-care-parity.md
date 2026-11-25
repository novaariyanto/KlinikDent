# Resep Care — Parity dengan `docs/resep.md`

**Goal:** Samakan behaviour tab Resep (`/visits/{visit}/care?tab=resep`) dengan spesifikasi `docs/resep.md` (referensi: `~/Sites/simrs` Resep V2), tanpa merusak SATUSEHAT, billing, farmasi, dan cetak resep.

**Analisis halaman live:** `http://klinikdent.test/visits/5/care?tab=resep&from=pelayanan` (login `dentist@klinikdent.test`) merender markup yang sama dengan repo: tabel 13 kolom (Barang, Kekuatan, Dosis, Stok, Satuan, Signa, Waktu, JHO, Qty, Keterangan, Harga, Subtotal, Aksi), baris draft via `<template>`, signa 4 input teks, racikan = bahan bersarang di sel Barang. Visit 5 belum punya item resep.

**Fondasi yang sudah sesuai** (jangan diubah tanpa alasan):
- Signa 4 slot (`signa_p/s/sr/m`) + `jho` (min 1) sudah ada di DB, model, request, dan validator.
- Qty dihitung otomatis dari signa × JHO (UI + `PrescriptionQuantity`), PRN / s.d. habis / sediaan non-tablet sudah ditangani.
- Stok per obat tampil hijau/merah, stok 0 tidak memblokir simpan.
- Racikan punya bahan (`prescription_compound_items`), harga racikan = Σ(qty bahan × harga), hapus induk meng-cascade bahan.
- Tombol *Non Racik* / *Racik*, edit inline, simpan sebagai template, hitung estimasi & subtotal sudah ada.

---

## 1. Gap terhadap `docs/resep.md`

| # | Butir dokumen | Kondisi sekarang | Aksi | Ukuran |
|---|---|---|---|---|
| G1 | Kolom pertama `R/` | Tidak ada kolom; penanda "R/ RACIK-01" menempel di sel Barang | Tambah kolom `R/` (isi `R/` untuk obat & induk racikan, kosong untuk komponen) | S |
| G2 | Signa = pilihan `0, 0.25 … 5` + custom (Select2 tags) | 4 input teks bebas (max 20) + popover katalog | Ganti dengan satu partial select+tags, dipakai semua baris & template | M |
| G3 | `aturan_pakai` = `pagi-siang-sore-malam` | Hanya tampilan `signaLine()`; `frequency` sering kosong bila signa diketik manual → SATUSEHAT memblokir | Accessor `aturan_pakai`; saat simpan, isi `frequency` dari signa bila kosong | S |
| G4 | Qty = (pagi+siang+sore+malam) × JHO; total 0 → 1 | Qty = **dosis** × Σsigna × JHO (`rxAssess`, `PrescriptionQuantity::assess`) | Buang pengali dosis untuk sediaan countable; pakai Σsigna × JHO | M |
| G5 | Dosis non-racikan diisi dari kekuatan & disabled | Input teks bebas + katalog dosis | Isi otomatis dari `strengthLabel()` saat obat dipilih, `readonly` + hidden input | S |
| G6 | Satuan non-racikan tanpa input aktif | Select satuan editable | Tampilkan sebagai teks readonly (hidden input tetap dikirim) | S |
| G7 | Waktu = Sebelum/Sesudah/Bersama makan/kosong | Input teks bebas (`AC`, `PC`, …) | Select 3 opsi + kosong; map nilai lama `AC/PC` saat edit | S |
| G8 | Satuan induk racikan = pilih jenis racikan dari master | Tidak ada jenis racikan; yang ada enum `medication_type` EP/SD + select satuan | Tambah kolom `jenis_racikan` + katalog pilihan (lihat Keputusan K2) | M |
| G9 | Nama racikan dapat diubah, kode `RACIK-01` otomatis | Nama = gabungan nama bahan, kode dihitung di view | Tambah kolom `name` pada `prescription_items`, input nama di baris induk | M |
| G10 | Komponen racikan = baris sendiri; Dosis/pembilang editable; Qty = (dosis × jumlah racikan) / kekuatan; tampil 2 desimal | Bahan bersarang di sel Barang; qty bahan manual/disamakan qty induk; tidak ada dosis per bahan | Restrukturisasi baris induk + baris komponen; kolom `dose_amount`; hitung qty otomatis | L |
| G11 | Kalkulator dosis (kandungan, bungkus, pembilang/penyebut, BB, %, hasil, ceil) | Tidak ada | Modal kalkulator per komponen, isi dosis + qty | M |
| G12 | Hapus induk = hapus komponen; hapus komponen = induk tetap | Sudah (FK cascade) | Tidak ada perubahan; tambah test regresi | XS |
| G13 | Pencarian obat min 2 karakter, tampilkan nama + nama generik, harga `hna × 1,1` | Select2 client-side berisi semua obat, label name+unit+KFA+strength, tanpa nama generik; harga estimasi = `base_price`, harga racikan = `sell_price` | `minimumInputLength: 2`; kolom `medicines.generic_name`; satukan harga ke `sell_price` | M |
| G14 | Stok per obat + **depo** terpilih | Stok = total cabang (`MedicineStockQuery` by branch); `prescriptions.pharmacy_depot` hanya metadata, `medicine_stocks` tidak punya dimensi depo | Lihat Keputusan K3 (default: tetap per cabang + label depo) | — |
| G15 | Stok gagal diambil → `-` | Stok kosong dirender `0` (badge merah) | Bedakan `null` (tanpa obat / gagal) vs `0` | XS |
| G16 | Tombol *Komponen Racik* + *Simpan Sebagai Template* per racikan | Hanya template level resep (bukan per baris induk) | Lihat Keputusan K4 (default: pertahankan template level resep) | — |
| G17 | Fallback jumlah racikan `10` bila kosong | Tidak ada | Tidak diadopsi (angka ajaib) | — |
| G18 | Nama field: `jumlah_diresepkan`, `keterangan_makan`, `catatan`, `jenis_racikan`, `instruksi_racikan`, `obat_id`, `pembilang`, `aturan_pakai` | `quantity`, `waktu`, `notes`, —, `notes`, `medicine_id`, `per_dose_quantity`, — | Lihat Keputusan K1 (default: tidak rename) | — |

---

## 2. Keputusan yang dipakai plan ini

- **K1 — Jangan rename kolom/field.** `quantity` = `jumlah_diresepkan`, `waktu` = `keterangan_makan`, `notes` = `catatan`, `medicine_id` = `obat_id`. Rename menembus SATUSEHAT (`SatuSehatFhirPayload`, readiness), billing, farmasi, PDF, template, dan mobile API. Yang ditambah hanya yang belum ada: `prescription_items.name`, `prescription_items.jenis_racikan`, `prescription_compound_items.dose_amount`, `medicines.generic_name`. Semua aditif, tanpa backfill wajib.
- **K2 — Jenis racikan = katalog config** (`config/pharmacy.php → 'jenis_racikan'`), disimpan sebagai string pada item, Select2 tags agar jenis baru bisa diketik. Dibuat tabel master + CRUD hanya jika klinik minta kelola sendiri (upgrade path jelas).
- **K3 — Stok tetap per cabang.** Tidak menambah dimensi depo ke `medicine_stocks` (menyentuh penerimaan stok, opname, mutasi, farmasi). Tampilkan label "Stok depo: {pharmacy_depot}" dari resep bila ada.
- **K4 — Template tetap level resep.** Tombol "Simpan Sebagai Template" yang ada (draft → template) dipertahankan; template per satu racikan tidak ditambahkan.
- **K5 — Harga estimasi = `sell_price`.** `sell_price` sudah `base_price + margin`; bila klinik ingin persis HNA×1,1 set margin 10% di master obat — bukan logika baru di form.

---

## 3. Rencana implementasi

### Phase 0 — Jaring pengaman (XS)
- [ ] `tests/Unit/PrescriptionQuantityTest.php`: signa 1-0-1-0 + JHO 3 → 6; signa kosong → `mode` bukan calculated + qty UI 1; PRN → manual; `s/d habis` tanpa JHO → manual; ml → qty ml; racikan countable tanpa angka dosis → 1/bungkus.
- [ ] `tests/Feature/ClinicalCareTest.php`: store item hanya dengan `signa_p..m` + `jho` + `medicine_id` (tanpa `frequency`) berhasil, `frequency` terisi `1-0-1-0`, `quantity` sesuai rumus.
- [ ] Jalankan `php artisan test --filter=PrescriptionQuantityTest` dan `--filter=ClinicalCareTest` (baseline hijau sebelum refactor).

### Phase 1 — Signa slots + `aturan_pakai` (M)
- [ ] Partial baru `resources/views/clinical/care/partials/prescription-signa-slots.blade.php`: 4 `<select name="signa_x">` (opsi 0, 0.25, …, 5) + opsi kosong; init Select2 `tags: true`, `minimumResultsForSearch: Infinity`.
- [ ] Pakai partial di `plan-prescriptions-list.blade.php` (baris tersimpan, template baris siap, template racikan) — ganti 3 blok input teks.
- [ ] `PrescriptionItem::aturan_pakai()` (accessor) → `p-s-sr-m`; `signaLine()` pakai format dokumen (`1-0-1-0`), bukan ` - `.
- [ ] `PrescriptionItemWriter::create`/`update`: bila `frequency` kosong dan ada signa, isi `frequency = aturan_pakai`. Jangan timpa `frequency` yang sudah berisi teks lain (mis. `3×1`, `PRN`).
- [ ] `PrescriptionSatuSehatReadiness`: tidak berubah (SIG jadi lengkap otomatis).
- [ ] Test: accessor format, normalisasi `frequency`, nilai lama `AC`/`PC` tetap tersimpan.

### Phase 2 — Rumus Qty sesuai dokumen (M)
- [ ] `PrescriptionQuantity::schedule()`: untuk sediaan countable, `daily = Σslot` (bukan `dose × Σslot`); `dose` tetap dipakai untuk sediaan cair/ml dan untuk teks `explain`.
- [ ] `rxAssess()` di `plan-prescriptions.blade.php`: samakan logika (sumber kebenaran ganda harus tetap sinkron). Hapus `doseAmount` sebagai pengali; Σslot = daily.
- [ ] Σslot = 0 → Qty UI = 1 (sudah di-clamp; pastikan tidak tersimpan 0) dan hint "Signa kosong, Qty diisi manual".
- [ ] Hapus pengali ganda untuk racikan: daily = Σslot; `explain` tetap menampilkan pagi/siang/sore/malam.
- [ ] Test: 500 mg + signa 1-0-1-0 + JHO 3 → 6 (bukan 3000); 3×2 via signa 2-2-2-0 JHO 5 → 30; ml 5-0-5-0 JHO 3 → 30 ml.

### Phase 3 — Paritas tabel & input (S–M)
- [ ] Tambah `<th>R/</th>` + `<td>` di thead/tbody/template; isi `R/` untuk ready & induk racikan.
- [ ] Pada pilih obat (`rxFillMedicine`): `dosage` = `strengthLabel()`, set `readonly`, simpan hidden `dosage` agar tetap terkirim. Untuk sediaan cair tetap izinkan edit (placeholder `5 ml`).
- [ ] Satuan ready: render teks readonly + hidden input; hapus select pada baris obat. Baris induk racikan: kolom Satuan dipakai select **jenis racikan**.
- [ ] Waktu: select `Sebelum Makan` / `Sesudah Makan` / `Bersama Makan` / kosong; saat load, map `AC → Sebelum Makan`, `PC → Sesudah Makan`, `saat makan → Bersama Makan`.
- [ ] Harga: `rx-medicine-catalog['price']` pakai `sell_price` (bukan `base_price`); `subtotal = qty × sell_price`.
- [ ] Stok: `null` → `—`, `0` → badge merah `0`, gagal query → `—`.
- [ ] Search: `minimumInputLength: 2`; label opsi `name · generic_name · strength`; harga & stok di dropdown via `templateResult`.

### Phase 4 — Racikan induk + komponen (L)
- [ ] Migrasi `database/migrations/2026_11_25_resep_parity.php` (semua `hasColumn` guard):
  - `prescription_items.name` (string 80, nullable) → nama racikan.
  - `prescription_items.jenis_racikan` (string 60, nullable).
  - `prescription_compound_items.dose_amount` (decimal 12,4, nullable) → pembilang/dosis.
  - `medicines.generic_name` (string 160, nullable).
- [ ] `config/pharmacy.php`: `'jenis_racikan' => ['Puyer', 'Kapsul', 'Salep', 'Sirup', 'Tetes', ...]`.
- [ ] Model/casts/fillable: `PrescriptionItem.name`, `jenis_racikan`; `PrescriptionCompoundItem.dose_amount`; `Medicine.generic_name` + tampil di form master obat (`management/medicines`).
- [ ] Request `Store/UpdatePrescriptionItemRequest`: validasi `name`, `jenis_racikan` (in config), `ingredients.*.dose_amount` (numeric min 0, max 999999); normalisasi tetap simpan `per_dose_quantity` (untuk SatuSehat) = `round(dose_amount / strength_value)` bila kekuatan > 0.
- [ ] `PrescriptionItemWriter`: saat simpan racikan, hitung `ingredient.quantity = ceil(dose_amount × parent.quantity / strength_value)` untuk bahan non-additive yang punya dosis; `per_dose_quantity` seperti di atas; `syncCompoundPrice()` tetap.
- [ ] `PrescriptionQuantity::ingredientMessage()`: sesuaikan pesan/mismatch dengan rumus baru; hentikan validasi lama `per_dose × qty`.
- [ ] UI `plan-prescriptions-list.blade.php`: render induk racikan (`R/ RACIK-01`, input nama, select jenis racikan, signa, waktu, JHO, Qty, instruksi, harga/subtotal, aksi) lalu baris komponen (Barang=select obat bahan, Kekuatan readonly, Dosis input, Stok, Qty readonly 2 desimal, Aksi: hapus + kalkulator). Template baris racikan baru di `rx-draft-compound-template` + partial `rx-draft-component-template`.
- [ ] Hapus bahan lewat tombol hapus komponen → PATCH item tanpa bahan tersebut; hapus induk → DELETE item (cascade).
- [ ] JS: `rxRecalcCompound()` hitung ulang semua qty komponen saat dosis / qty induk / kekuatan berubah; `rxRecalcRow` jumlahkan `qty × harga` per komponen; sinkron `data-rx-estimate`.
- [ ] Simpan: payload racikan mengirim `ingredients[i][dose_amount]`, `[medicine_id]`, `[strength_value]`, `[unit]`, dan `quantity` hasil hitung (server tetap menghitung ulang — server menang).
- [ ] Partial template (`prescription-compound-fields`, `prescription-template-compound-row`, `prescription-template-row`) tetap kompatibel: bahan lama tanpa `dose_amount` → fallback `per_dose_quantity`/qty manual.
- [ ] Test: formula (250 mg × 10 bungkus / 500 mg = 5), kekuatan 0 → qty 0 lalu clamp 1 di UI, qty 2 desimal tampilan, hapus induk menghapus bahan, hapus komponen induk tetap.

### Phase 5 — Kalkulator dosis (M)
- [ ] Modal `rx-dose-calculator` (di `plan-prescriptions.blade.php`): kandungan, jumlah bungkus, pembilang, penyebut, desimal pembilang/penyebut, dosis akhir, berat badan, persentase, hasil, pembulatan (readonly hasil).
- [ ] Rumus: `dosis_akhir = (pembilang / penyebut) × kandungan`; `jumlah = (dosis_akhir × bungkus) / kandungan`; `dosis_akhir = (BB × persentase) / 100`; simpan `ceil(jumlah)`.
- [ ] Tombol OK mengisi `dose_amount` + `quantity` komponen target lalu memicu `rxRecalcCompound`.
- [ ] Test JS manual + unit test PHP untuk kedua rumus bila logika dipindah ke `PrescriptionQuantity` (disarankan: satu helper `PrescriptionDoseCalculator` agar bisa dites).

### Phase 6 — Cetak, SATUSEHAT, polish (M)
- [ ] `PrescriptionItem::instructionText()`/PDF (`print/partials`, `prescription pdf`, label 58mm) memakai format signa dokumen dan `name` racikan.
- [ ] `SatuSehatFhirPayload` + `PrescriptionSatuSehatReadiness`: racikan memakai `name`/`jenis_racikan` di label, cek regresi payload test (`SatuSehatPayloadTest`).
- [ ] Farmasi/billing: pastikan `compoundCharge()` dan pemotongan stok memakai `ingredient.quantity` baru (integer, hasil ceil).
- [ ] Template: terapkan template racikan menghasilkan baris induk + komponen dengan `dose_amount`; tanpa `dose_amount` pakai fallback lama.
- [ ] Manual smoke di `/visits/5/care?tab=resep`: tambah obat (ketik 1 huruf → dropdown kosong; 2 huruf → hasil), signa via select & custom tag, JHO, Qty, simpan, reload, edit, hapus; racikan dengan 2 komponen + kalkulator; cetak PDF & label.

---

## 4. File yang tersentuh (perkiraan)

- `app/Models/{PrescriptionItem,PrescriptionCompoundItem,Medicine}.php`
- `app/Support/Clinical/{PrescriptionQuantity,PrescriptionItemWriter,PrescriptionTemplateService,PrescriptionSatuSehatReadiness}.php`
- `app/Support/Clinical/PrescriptionDoseCalculator.php` (baru, Phase 5)
- `app/Http/Requests/Clinical/{Store,Update}PrescriptionItemRequest.php`, `StorePrescriptionTemplateRequest.php`
- `resources/views/clinical/care/partials/plan-prescriptions.blade.php`
- `resources/views/clinical/care/partials/plan-prescriptions-list.blade.php`
- `resources/views/clinical/care/partials/prescription-signa-slots.blade.php` (baru)
- `resources/views/clinical/care/partials/prescription-compound-fields.blade.php`, `prescription-ingredient-row.blade.php`, `prescription-template-*.blade.php`
- `config/pharmacy.php`, `database/migrations/2026_11_25_resep_parity.php`, `resources/views/management/medicines/*` (nama generik)
- Tests: `tests/Unit/PrescriptionQuantityTest.php` (baru), `tests/Unit/PrescriptionDoseCalculatorTest.php` (baru), `tests/Feature/ClinicalCareTest.php`

---

## 5. Di luar cakupan / risiko

- **Tidak diubah:** nama kolom DB, stok per depo, template per racikan, fallback jumlah 10, alur kirim farmasi & SATUSEHAT di luar penyesuaian SIG.
- **Risiko utama:** rewrite racikan menyentuh `plan-prescriptions-list` + `plan-prescriptions` (≈2.700 baris total) dan dipakai template/farmasi/cetak. Mitigasi: Phase 0 test dulu, migrasi aditif, server menghitung ulang qty (UI hanya tampilan), dan fallback data lama (`per_dose_quantity`) dipertahankan.
- **Data lama:** item racikan tanpa `dose_amount` tetap tampil dengan qty tersimpan; jangan paksa hitung ulang saat load untuk menghindari perubahan resep yang sudah dikirim.
- **Rollback:** migrasi `down()` hanya drop kolom baru; kode lama tetap bisa membaca `quantity`/`per_dose_quantity` sehingga rollback kode aman.

---

## 6. Status eksekusi (2026-11-25)

Sudah dikerjakan:

- **Phase 0** — `tests/Unit/PrescriptionQuantityTest.php` (8 test) + 2 test fitur di `ClinicalCareTest` (signa → frequency/qty, qty komponen racikan). Lulus.
- **Phase 1** — `prescription-signa-slots.blade.php` (select 0…5 + Select2 tags), `PrescriptionQuantity::signaLine()`, `PrescriptionItem::aturan_pakai()`, `frequency` otomatis dari signa saat simpan, format `1-0-1-0`.
- **Phase 2** — rumus qty jadi Σsigna × JHO (server + JS), signa 0 → Qty 1, `3×2` → 6/hari, ml tetap Σslot.
- **Phase 3** — kolom `R/`, dosis readonly dari kekuatan (fallback editable bila kekuatan kosong), satuan ready readonly, waktu 3 pilihan (+map `AC`/`PC`), harga estimasi pakai `sell_price`, search min 2 karakter + nama generik + harga/stok di dropdown.
- **Phase 4** — migrasi aditif (`name`, `jenis_racikan`, `dose_amount`, `generic_name`), katalog `config/pharmacy.jenis_racikan`, baris induk racikan dengan nama + jenis + komponen (obat, kekuatan, dosis/pembilang, stok, qty otomatis, hapus), qty komponen = ceil(dosis × bungkus ÷ kekuatan) di UI dan server, cascade delete tetap.
- **Phase 5** — modal kalkulator dosis (pecahan/berat badan, hasil + pembulatan) mengisi dosis + qty komponen.
- Form master obat mendapat field **Nama generik**.
- Qty komponen: `ceil(dosis × qty induk ÷ kekuatan)`; nilai mentah (mis. 21,6) ditampilkan di samping qty. Qty induk yang diketik manual ikut menghitung ulang komponen.
- Baris komponen tampil selalu (view) dan berubah jadi input saat baris induk diedit; tambah/hapus komponen langsung di tabel.

Deviasi yang disengaja (tetap sesuai Keputusan §2):

- Nama kolom DB tidak diubah; `aturan_pakai` berupa accessor, bukan kolom.
- Qty komponen disimpan integer (ceil), tampilan 2 desimal tidak dipakai karena kolom stok/billing integer.
- Komponen racikan dirender sebagai **baris tabel sendiri** di bawah baris induk (R/ · Barang/obat · Kekuatan · Dosis · Stok · Qty · Harga · Subtotal · Aksi). Harga/subtotal induk = jumlah subtotal komponen.
- Jenis racikan memakai katalog config (select biasa), belum tabel master + CRUD.
- Stok tetap per cabang (belum per depo); template tetap level resep; fallback qty `10` tidak diadopsi.
- Harga memakai `sell_price` (set margin 10% bila ingin persis HNA×1,1).

Verifikasi: `php artisan test --filter="PrescriptionQuantityTest|ClinicalCareTest"` → hanya `test_pharmacy_sees_sent_prescriptions_only` yang gagal, dan test itu juga gagal di HEAD (pre-existing). Smoke HTTP ke `/visits/5/care?tab=resep`: tambah obat (signa → frequency `1-0-1-0`, qty 6), tambah racikan (dose 250, bungkus 10, kekuatan 500 → qty 5), update racikan (bungkus 20 → qty 10), hapus item — semua 200.
