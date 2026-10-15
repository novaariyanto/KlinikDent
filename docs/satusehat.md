# SPESIFIKASI INTEGRASI SATUSEHAT MULTICABANG
## KlinikDent.id — Multi-Branch Dental Clinic

### 1. TUJUAN

Mendukung klinik gigi dengan banyak cabang agar seluruh cabang dapat menggunakan satu platform KlinikDent.id, dengan pengelolaan data terpusat dan integrasi SATUSEHAT sesuai identitas masing-masing fasilitas kesehatan.

Prinsip utama:
- Satu akun perusahaan atau grup klinik dapat memiliki banyak cabang.
- Setiap cabang memiliki identitas fasilitas kesehatan sendiri sesuai registrasi resminya.
- Data pelayanan dikirim menggunakan identitas fasilitas dan lokasi yang benar.
- Kantor pusat dapat memantau status integrasi seluruh cabang.
- Data pasien dapat dikelola lintas cabang sesuai kewenangan akses, kebijakan rekam medis, dan ketentuan perlindungan data.

### 2. STRUKTUR ORGANISASI KLINIKDENT

Struktur internal yang disarankan:

Perusahaan / Grup Klinik
- Cabang A
  - Dokter gigi
  - Perawat gigi
  - Admin
  - Pasien dan kunjungan
- Cabang B
  - Dokter gigi
  - Perawat gigi
  - Admin
  - Pasien dan kunjungan
- Cabang C
  - Dokter gigi
  - Perawat gigi
  - Admin
  - Pasien dan kunjungan

Struktur tersebut adalah struktur organisasi internal KlinikDent. Struktur ini tidak otomatis berarti setiap grup perusahaan memiliki satu identitas SATUSEHAT untuk seluruh cabang.

### 3. IDENTITAS SATUSEHAT PER CABANG

Untuk setiap cabang, KlinikDent harus menyediakan konfigurasi berikut:

- [ ] Nama resmi fasilitas kesehatan.
- [ ] Alamat lengkap cabang.
- [ ] Identitas dan nomor perizinan fasilitas.
- [ ] ID `Organization` SATUSEHAT yang sesuai.
- [ ] ID `Location` SATUSEHAT untuk lokasi pelayanan.
- [ ] Status registrasi dan kesiapan integrasi.
- [ ] Data penanggung jawab fasilitas sesuai kebutuhan.
- [ ] Data tenaga kesehatan yang memberikan pelayanan.
- [ ] Konfigurasi akses dan pemetaan resource sesuai fasilitas.

**Catatan:** Jangan membuat `Organization` baru secara otomatis hanya karena pengguna menambahkan cabang di KlinikDent. Identitas SATUSEHAT harus mengikuti registrasi dan struktur resmi fasilitas kesehatan. Jika satu organisasi memiliki beberapa lokasi, pemetaannya harus mengikuti data resmi SATUSEHAT.

### 4. PEMETAAN RESOURCE FHIR

KlinikDent perlu mengelola hubungan antara cabang dan resource SATUSEHAT berikut:

| Resource | Pengelolaan |
|---|---|
| `Organization` | Identitas organisasi atau fasilitas sesuai registrasi |
| `Location` | Lokasi pelayanan yang sesuai dengan cabang |
| `Practitioner` | Identitas tenaga kesehatan |
| `PractitionerRole` | Peran tenaga kesehatan pada organisasi/lokasi, jika diperlukan |
| `Patient` | Identitas pasien |
| `Encounter` | Kunjungan pasien pada cabang yang memberikan pelayanan |
| `Condition` | Diagnosis atau kondisi klinis |
| `Observation` | Pemeriksaan dan odontogram sesuai spesifikasi |
| `Procedure` | Tindakan medis |

Seluruh referensi resource harus mengikuti ID resmi yang diperoleh dari SATUSEHAT dan hubungan resource yang ditetapkan dalam profil FHIR terkait.

### 5. ATURAN PENGIRIMAN DATA PER CABANG

Setiap kali kunjungan dibuat:

1. Sistem mengambil cabang tempat pasien benar-benar menerima pelayanan.
2. Sistem menentukan identitas organisasi dan lokasi SATUSEHAT yang sesuai.
3. Sistem menentukan tenaga kesehatan yang memberikan pelayanan.
4. Sistem membuat atau memperbarui `Encounter` sesuai playbook.
5. Resource klinis dikaitkan dengan pasien dan kunjungan yang benar.
6. Sistem menyimpan ID resource SATUSEHAT beserta referensi cabang internal.
7. Sistem mencatat hasil pengiriman, waktu pengiriman, dan pesan error jika terjadi kegagalan.

Jangan menggunakan identitas cabang pusat secara otomatis untuk seluruh kunjungan apabila identitas fasilitas atau lokasi pelayanan seharusnya berbeda.

### 6. PASIEN YANG BERKUNJUNG KE CABANG BERBEDA

Contoh:

Pasien terdaftar pertama kali di Cabang A, kemudian melakukan perawatan lanjutan di Cabang B.

Sistem harus mendukung:
- [ ] Pencarian pasien yang sudah terdaftar tanpa membuat duplikasi identitas.
- [ ] Penggunaan identitas pasien SATUSEHAT yang sesuai.
- [ ] Pembuatan kunjungan baru untuk pelayanan di Cabang B.
- [ ] Pencatatan dokter dan lokasi pelayanan Cabang B.
- [ ] Akses riwayat medis lintas cabang berdasarkan hak akses.
- [ ] Audit akses terhadap rekam medis pasien.
- [ ] Pencatatan asal cabang dan cabang tempat pelayanan dilakukan.

Riwayat kunjungan lama tidak boleh dipindahkan begitu saja ke cabang baru. Kunjungan baru harus mencerminkan tempat pelayanan yang sebenarnya.

### 7. DASHBOARD MONITORING MULTICABANG

Dashboard kantor pusat perlu menampilkan:

- [ ] Daftar seluruh cabang.
- [ ] Status konfigurasi SATUSEHAT setiap cabang.
- [ ] Identitas `Organization` dan `Location` yang telah dipetakan.
- [ ] Jumlah kunjungan yang menunggu pengiriman.
- [ ] Jumlah pengiriman berhasil dan gagal.
- [ ] Pesan error yang dapat ditindaklanjuti.
- [ ] Waktu sinkronisasi terakhir.
- [ ] Riwayat percobaan ulang pengiriman.
- [ ] Filter monitoring berdasarkan cabang dan periode.

Dashboard harus membedakan antara status integrasi cabang, status pengiriman resource, dan status kelengkapan data klinis.

### 8. HAK AKSES MULTICABANG

Peran pengguna yang disarankan:

**Super Admin Grup**
- Mengelola perusahaan dan seluruh cabang.
- Mengatur konfigurasi integrasi sesuai kewenangan.
- Memantau status pengiriman semua cabang.

**Admin Cabang**
- Mengelola operasional cabang yang menjadi tanggung jawabnya.
- Melihat status integrasi cabang.
- Menindaklanjuti kesalahan data sesuai hak akses.

**Dokter**
- Mengakses pasien dan rekam medis sesuai kewenangan.
- Membuat dan memvalidasi catatan klinis.
- Melakukan pelayanan dan dokumentasi medis.

**Petugas Integrasi**
- Memantau log dan error integrasi sesuai penugasan.
- Tidak otomatis memperoleh akses penuh ke isi rekam medis.

Pembatasan akses harus diterapkan di backend, bukan hanya dengan menyembunyikan menu di frontend.

### 9. DESAIN DATABASE INTERNAL KLINIKDENT

Setiap data yang berkaitan dengan cabang sebaiknya memiliki relasi tenant dan cabang yang jelas.

Tabel atau entitas yang disarankan:
- `organizations` — perusahaan atau grup klinik internal.
- `branches` — daftar cabang.
- `branch_satusehat_mappings` — pemetaan identitas SATUSEHAT per cabang.
- `practitioners` — data tenaga kesehatan.
- `patients` — data pasien.
- `encounters` — data kunjungan.
- `clinical_records` — catatan klinis.
- `satusehat_sync_logs` — log pengiriman.
- `satusehat_resource_mappings` — pemetaan ID internal dan ID resource SATUSEHAT.

Kolom pemetaan yang perlu dipertimbangkan:
- `organization_id` internal KlinikDent.
- `branch_id` internal KlinikDent.
- `satusehat_organization_id`.
- `satusehat_location_id`.
- `resource_type`.
- `internal_resource_id`.
- `satusehat_resource_id`.
- `sync_status`.
- `last_synced_at`.
- `last_error`.

Nama tabel dan kolom tersebut adalah rekomendasi desain internal, bukan nama tabel atau field resmi SATUSEHAT.

### 10. KEAMANAN DAN PEMISAHAN DATA

- [ ] Setiap pengguna dibatasi pada tenant dan cabang yang diizinkan.
- [ ] Permintaan API memvalidasi akses tenant dan cabang di backend.
- [ ] Data pasien tidak boleh bocor antarklinik yang berbeda tenant.
- [ ] Akses lintas cabang dalam satu grup diberikan sesuai kewenangan.
- [ ] Kredensial dan token integrasi disimpan dengan aman.
- [ ] Log tidak menyimpan data pribadi berlebihan.
- [ ] Perubahan identitas cabang dan pemetaan SATUSEHAT diaudit.
- [ ] Tersedia pencadangan dan prosedur pemulihan data.

### 11. KRITERIA SELESAI IMPLEMENTASI

- [ ] Grup klinik dapat memiliki banyak cabang.
- [ ] Setiap cabang dapat dikonfigurasi dengan identitas SATUSEHAT yang benar.
- [ ] Kunjungan dikirim menggunakan identitas fasilitas dan lokasi yang sesuai.
- [ ] Pasien dapat dilayani di cabang berbeda tanpa duplikasi identitas yang tidak perlu.
- [ ] Resource klinis terhubung dengan kunjungan yang benar.
- [ ] Dashboard pusat dapat memonitor seluruh cabang.
- [ ] Admin cabang hanya dapat mengelola data sesuai kewenangannya.
- [ ] Error pengiriman dapat dilacak dan ditangani.
- [ ] Pengujian dilakukan pada sandbox dengan skenario beberapa cabang.
- [ ] Seluruh konfigurasi telah divalidasi berdasarkan ketentuan SATUSEHAT terbaru.

### 12. REFERENSI RESMI

- SATUSEHAT Platform: https://satusehat.kemkes.go.id/platform
- Playbook Rawat Jalan Gigi: https://satusehat.kemkes.go.id/platform/docs/id/interoperability/rawat-jalan-gigi/
- Dokumentasi FHIR: https://satusehat.kemkes.go.id/platform/docs/id/fhir/
- Dokumentasi Interoperabilitas: https://satusehat.kemkes.go.id/platform/docs/id/interoperability/

**Status dokumen:** Draft spesifikasi teknis multi-cabang untuk pengembangan KlinikDent.id.

**Catatan implementasi:** Model organisasi dan lokasi SATUSEHAT harus ditentukan berdasarkan identitas serta registrasi fasilitas yang sebenarnya. Validasi struktur `Organization`, `Location`, `PractitionerRole`, dan referensi resource lain terhadap playbook resmi sebelum digunakan pada production.
