## 3. Struktur tabel input

Tabel resep memiliki kolom berikut:

| Kolom | Behaviour |
|---|---|
| `R/` | Penanda awal item resep. Muncul pada baris non-racikan dan baris induk racikan. |
| Barang | Pencarian dan pemilihan obat. |
| Kekuatan | Diisi dari master obat saat obat dipilih. |
| Dosis | Pada non-racikan terisi dari kekuatan dan disabled. Pada komponen racikan dapat diubah. |
| Stok | Stok obat pada depo terpilih. |
| Satuan | Tidak memiliki input aktif pada baris obat saat ini. Pada baris induk racikan, kolom ini digunakan untuk memilih jenis racikan. |
| Signa | Empat waktu pemberian: pagi, siang, sore, malam. |
| Waktu | Pilihan sebelum makan, sesudah makan, bersama makan, atau kosong. |
| JHO | Input angka minimal `1`. |
| Qty | Jumlah obat atau jumlah racikan yang diresepkan. |
| Keterangan | Catatan non-racikan atau instruksi racikan. |
| Harga | Harga estimasi dari data obat. |
| Subtotal | Harga dikali qty. |
| Aksi | Menghapus baris. |

### Behaviour pemilihan obat

1. Pencarian obat baru berjalan setelah minimal dua karakter dimasukkan.
2. Hasil pencarian menampilkan nama obat dan nama generik jika tersedia.
3. Saat obat dipilih, sistem mengisi:
   - ID obat.
   - Kekuatan.
   - Harga estimasi.
   - Dosis awal berdasarkan kekuatan.
4. Stok kemudian diambil berdasarkan kombinasi obat dan depo farmasi.
5. Tampilan stok:
   - Stok lebih dari nol: warna hijau.
   - Stok nol atau kurang: warna merah.
   - Gagal mengambil stok: tanda `-`.
6. Harga yang ditampilkan pada hasil pencarian berasal dari `hna * 1,1` dan digunakan untuk menghitung estimasi biaya.
7. Pemilihan obat tidak otomatis memblokir penyimpanan hanya karena stok nol. Stok hanya ditampilkan sebagai informasi.

---

## 4. Resep non-racikan

### 4.1 Menambah baris

Klik **Non Racik** untuk menambahkan satu baris obat biasa.

Pada baris non-racikan tersedia:

- Obat.
- Kekuatan.
- Dosis readonly, mengikuti kekuatan obat.
- Stok.
- Signa pagi, siang, sore, malam.
- Waktu konsumsi.
- JHO.
- Qty.
- Keterangan.
- Harga dan subtotal.

### 4.2 Signa

Masing-masing waktu signa memiliki pilihan angka:

```text
0, 0.25, 0.5, 0.75, ... , 5
```

Input juga mendukung angka custom melalui fitur tag Select2.

Sistem menyimpan signa sebagai empat nilai terpisah:

- `pagi`
- `siang`
- `sore`
- `malam`

Selain itu, sistem membentuk `aturan_pakai` dengan format:

```text
pagi-siang-sore-malam
```

Contoh:

```text
1-0-1-0
```

### 4.3 Perhitungan qty

Ketika signa atau JHO diubah:

```text
qty = (pagi + siang + sore + malam) x JHO
```

Jika total signa menghasilkan nol, qty dikembalikan menjadi `1` pada sisi UI.

Contoh:

```text
Pagi 1 + Siang 0 + Sore 1 + Malam 0 = 2 kali/hari
JHO = 3
Qty = 2 x 3 = 6
```

Qty non-racikan dikirim sebagai `jumlah_diresepkan`.

### 4.4 Waktu konsumsi dan keterangan

- Field **Waktu** disimpan sebagai `keterangan_makan`.
- Pilihan yang tersedia:
  - `Sebelum Makan`
  - `Sesudah Makan`
  - `Bersama Makan`
  - Kosong
- Field **Keterangan** disimpan sebagai `catatan`.

---

## 5. Resep racikan

### 5.1 Menambah racikan

Klik **Racik** untuk membuat baris induk racikan.

Baris induk racikan memiliki:

- Kode racikan otomatis, misalnya `RACIK-01`.
- Nama racikan yang dapat diubah.
- Jenis racikan.
- Signa pagi, siang, sore, malam.
- Waktu konsumsi.
- JHO.
- Jumlah racikan/qty.
- Instruksi racikan.

Setelah baris induk dibuat, muncul tombol:

- **Komponen Racik**
- **Simpan Sebagai Template**

### 5.2 Jenis racikan

Jenis racikan dicari melalui Select2 dari master jenis racikan. ID jenis racikan dikirim sebagai `jenis_racikan`.

Contoh jenis racikan bergantung pada isi master aplikasi; form tidak membuat daftar jenis racikan secara hard-code.

### 5.3 Menambah komponen

Klik **Komponen Racik** untuk menambahkan obat sebagai komponen pada racikan tersebut.

Komponen racikan memiliki:

- Obat.
- Kekuatan.
- Dosis/pembilang yang dapat diubah.
- Stok.
- Qty komponen.
- Tombol kalkulator dosis.
- Tombol hapus.

Komponen tidak memiliki input signa, waktu makan, JHO, atau keterangan sendiri. Informasi tersebut mengikuti baris induk racikan.

### 5.4 Perhitungan qty komponen

Jika dosis, kekuatan, atau jumlah bungkus berubah, sistem menghitung:

```text
qty komponen = (dosis x jumlah racikan) / kekuatan obat
```

Hasil qty komponen ditampilkan dengan dua angka desimal.

- `jumlah racikan` diambil dari qty baris induk.
- Jika nilai jumlah racikan tidak tersedia, perhitungan menggunakan fallback `10` pada beberapa alur JavaScript.
- Jika kekuatan obat tidak lebih dari nol, qty komponen menjadi `0` pada perhitungan otomatis.

Data komponen dikirim dengan struktur utama:

- `obat_id`
- `kekuatan`
- `pembilang`
- `qty`
- `satuan` (saat ini dikirim kosong dari form racikan)

### 5.5 Perhitungan qty racikan

Pada baris induk racikan, perubahan signa atau JHO mengikuti rumus yang sama dengan non-racikan:

```text
qty racikan = (pagi + siang + sore + malam) x JHO
```

Qty baris induk menjadi `jumlah_diresepkan` racikan.

### 5.6 Keterangan racikan

- Input **Keterangan** pada baris induk racikan dikirim sebagai `instruksi_racikan`.
- `aturan_pakai` racikan tetap dibentuk dari nilai signa dengan format:

```text
pagi-siang-sore-malam
```

### 5.7 Menghapus racikan

Jika baris induk racikan dihapus:

1. Seluruh komponen racikan ikut dihapus dari tabel.
2. Baris aksi racikan ikut dihapus.
3. Total biaya dihitung ulang.

Jika hanya komponen yang dihapus, induk racikan tetap ada.

---

## 6. Kalkulator dosis racikan

Tombol kalkulator tersedia pada baris komponen racikan.

Kalkulator menyediakan:

- Kandungan/kekuatan obat.
- Jumlah bungkus racikan.
- Pembilang.
- Penyebut.
- Nilai desimal pembilang/penyebut.
- Dosis akhir.
- Berat badan.
- Persentase dosis.
- Hasil jumlah.
- Hasil pembulatan jumlah.

### Rumus

Dosis akhir dari pecahan:

```text
dosis akhir = (pembilang / penyebut) x kandungan
```

Jumlah komponen:

```text
jumlah = (dosis akhir x jumlah bungkus) / kandungan
```

Jumlah yang disimpan ke baris adalah hasil pembulatan ke atas (`ceil`).

Kalkulator juga dapat menghitung dosis dari berat badan dan persentase:

```text
dosis akhir = (berat badan x persentase) / 100
```

Ketika tombol **Simpan/OK** pada kalkulator ditekan, sistem mengisi dosis dan qty pada baris komponen yang sedang dihitung.

---
