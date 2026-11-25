# Kalkulator Dosis Racikan

## 1. Informasi Umum

- **Nama:** Kalkulator Dosis Racikan
- **Jenis:** Modal / Dialog
- **Tujuan:** Menghitung jumlah obat yang dibutuhkan berdasarkan kandungan, dosis akhir, jumlah bungkus, dan pembulatan.
- **Layout:** Form vertikal dengan dua kolom pada beberapa bagian.

---

## 2. Struktur Wireframe

```text
┌──────────────────────────────────────────────────────────────┐
│                  KALKULATOR DOSIS RACIKAN                    │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  KANDUNGAN (KEKUATAN)       JUMLAH BUNGKUS (QTY)             │
│  ┌─────────────────────┐   ┌──────────────────────────────┐  │
│  │ 0                   │   │ 1                            │  │
│  └─────────────────────┘   └──────────────────────────────┘  │
│                                                              │
│  DOSIS PECAHAN (OPSIONAL)                                     │
│  ┌──────────────────┐       ┌──────────────────┐             │
│  │ Pembilang: 1     │   /   │ Penyebut: 2      │             │
│  └──────────────────┘       └──────────────────┘             │
│                                                              │
│  Hasil Pecahan: 0.5000                                       │
│  Contoh: 1/2 = 0.5, maka Dosis Akhir = Kandungan × 0.5       │
│                                                              │
│  DOSIS AKHIR (PER BUNGKUS)                                    │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ 0,00                                                   │  │
│  └────────────────────────────────────────────────────────┘  │
│  Bisa diisi langsung atau dihitung dari pecahan × kandungan │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  KALKULATOR DOSIS RACIKAN (BERDASARKAN BB & PERSEN)          │
│                                                              │
│  BERAT BADAN (KG)             PERSEN (%)                     │
│  ┌─────────────────────┐     ┌────────────────────────────┐  │
│  │ 0                   │     │ 0                          │  │
│  └─────────────────────┘     └────────────────────────────┘  │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ [CALCULATOR] Hitung Dosis dari BB & Persen             │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
│  Rumus: Dosis = (BB × Persen / 100)                          │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  HASIL PERHITUNGAN                                            │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ Rumus: Jumlah = (Dosis Akhir × Jumlah Bungkus) /        │  │
│  │        Kandungan                                         │  │
│  │                                                        │  │
│  │ Hitung: (0.00 × 1) / 0 = 0.00                          │  │
│  │                                                        │  │
│  │ Jumlah: 0.00                Pembulatan: 0               │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│                                  ┌──────────┐ ┌───────────┐   │
│                                  │    OK    │ │   BATAL   │   │
│                                  └──────────┘ └───────────┘   │
└──────────────────────────────────────────────────────────────┘
```

---

## 3. Spesifikasi Komponen

| No | Komponen | Tipe | Keterangan |
|---|---|---|---|
| 1 | Kandungan (Kekuatan) | Number Input | Kekuatan obat per satuan |
| 2 | Jumlah Bungkus (QTY) | Number Input | Jumlah bungkus racikan |
| 3 | Pembilang | Number Input | Nilai pembilang dosis pecahan |
| 4 | Penyebut | Number Input | Nilai penyebut dosis pecahan |
| 5 | Hasil Pecahan | Read-only | Hasil pembilang dibagi penyebut |
| 6 | Dosis Akhir | Number Input | Dosis akhir per bungkus |
| 7 | Berat Badan | Number Input | Berat badan dalam kilogram |
| 8 | Persen | Number Input | Persentase dosis |
| 9 | Hitung Dosis | Button | Menghitung dosis berdasarkan BB dan persen |
| 10 | Hasil Perhitungan | Display | Menampilkan rumus dan hasil jumlah |
| 11 | Pembulatan | Display | Hasil pembulatan jumlah |
| 12 | OK | Button | Konfirmasi hasil |
| 13 | Batal | Button | Membatalkan proses |

---

## 4. Logika Perhitungan

### A. Perhitungan Dosis Pecahan

```text
Hasil Pecahan = Pembilang / Penyebut

Dosis Akhir = Kandungan × Hasil Pecahan
```

Contoh:

```text
Kandungan  = 500 mg
Pembilang  = 1
Penyebut   = 2

Hasil Pecahan = 1 / 2 = 0.5
Dosis Akhir   = 500 × 0.5
              = 250 mg
```

### B. Perhitungan Dosis Berdasarkan BB dan Persen

```text
Dosis = (BB × Persen) / 100
```

### C. Perhitungan Jumlah Obat

```text
Jumlah = (Dosis Akhir × Jumlah Bungkus) / Kandungan
```

Contoh:

```text
Dosis Akhir     = 250 mg
Jumlah Bungkus  = 10
Kandungan       = 500 mg

Jumlah = (250 × 10) / 500
       = 5
```

### D. Pembulatan

Menampilkan:
- Jumlah hasil perhitungan desimal.
- Jumlah setelah pembulatan.

**Catatan:** Aturan pembulatan harus mengikuti ketentuan satuan dan bentuk sediaan obat yang digunakan.

---

## 5. Perilaku UI

| Aksi | Perilaku |
|---|---|
| Mengubah kandungan | Menghitung ulang jumlah obat |
| Mengubah jumlah bungkus | Menghitung ulang jumlah obat |
| Mengubah pembilang | Menghitung ulang hasil pecahan |
| Mengubah penyebut | Menghitung ulang hasil pecahan |
| Mengubah dosis akhir | Menghitung ulang jumlah obat |
| Klik Hitung Dosis | Menghitung dosis berdasarkan BB dan persen |
| Klik OK | Mengirim hasil ke form sebelumnya |
| Klik Batal | Menutup dialog tanpa menyimpan perubahan |

---

## 6. Validasi

- Kandungan harus lebih besar dari 0.
- Jumlah bungkus harus lebih besar dari 0.
- Penyebut pecahan tidak boleh 0.
- Dosis akhir tidak boleh bernilai negatif.
- Berat badan tidak boleh bernilai negatif.
- Persentase harus mengikuti batas yang ditentukan aplikasi.
- Perhitungan tidak boleh menghasilkan `NaN` atau `Infinity`.
- Hasil perhitungan perlu diperbarui ketika input terkait berubah.

---

## 7. Catatan Pengembangan

1. Gunakan perhitungan numerik dengan presisi yang memadai.
2. Pisahkan nilai input, hasil kalkulasi, dan nilai pembulatan.
3. Hindari pembagian dengan nol.
4. Hasil pecahan dapat ditampilkan otomatis.
5. Hasil perhitungan jumlah obat diperbarui secara reaktif.
6. Tombol OK hanya menyimpan nilai yang sudah tervalidasi.
7. Pastikan aturan pembulatan sesuai dengan bentuk sediaan dan kebutuhan farmasi.

**Output utama:** Jumlah obat yang diperlukan untuk memenuhi dosis racikan berdasarkan jumlah bungkus yang diminta.
