# KlinikDent

Aplikasi manajemen klinik gigi berbasis SaaS. Satu platform untuk banyak klinik (tenant), dengan isolasi data per klinik dan menu yang mengikuti peran pengguna.

## Stack

- Laravel 12, PHP 8.3+
- Blade, Bootstrap 5, Skote Admin Template
- Spatie Laravel Permission
- Yajra DataTables (server-side)
- MySQL 8+, Vite

## Arsitektur

Aplikasi memisahkan dua ruang kerja:

| Ruang | Siapa | Lingkup |
| --- | --- | --- |
| Platform SaaS | Super Admin SaaS | Tenant, paket, langganan, invoice platform, log sistem |
| Klinik (tenant) | Owner sampai Auditor | Operasional klinik: pasien, pelayanan, farmasi, kasir, keuangan |

Data operasional (pasien, kunjungan, stok, tagihan, dan seterusnya) terikat `tenant_id`. Pengguna klinik hanya melihat data kliniknya sendiri. Cabang (`branch`) membatasi operasional harian di dalam tenant.

---

## Peran (Role)

Hak akses diatur lewat Spatie Permission. Menu sidebar dibangun per peran dari katalog menu aplikasi.

### Platform

| Role | Kode | Fungsi |
| --- | --- | --- |
| Super Admin SaaS | `super-admin-saas` | Operator platform. Mengelola klinik/tenant, paket langganan, invoice SaaS, user platform, integrasi tingkat sistem, tiket dukungan, dan pengaturan global. Tidak mengerjakan pelayanan pasien. |

### Klinik

| Role | Kode | Fungsi |
| --- | --- | --- |
| Owner / Super Admin Klinik | `owner` | Pemilik klinik. Akses penuh operasional, master data, user & role, cabang, keuangan, integrasi SATUSEHAT/BPJS, dan audit log. |
| Manager / Admin Klinik | `manager` | Admin operasional. Mengelola pendaftaran, dokter & jadwal, farmasi, billing, laporan, cabang, master data, user, dan pengaturan klinik. Tidak mengelola kas/bank dan hutang secara penuh seperti Keuangan. |
| Pendaftaran | `registration` | Front office. Mendaftarkan pasien, membuat kunjungan, mengelola antrean, melihat jadwal dokter hari ini, memilih penjamin, dan cek kepesertaan BPJS. |
| Dokter Gigi | `dentist` | Pelayanan klinis. Memanggil antrean, mengisi rekam medis, odontogram, diagnosis, tindakan, resep, rujukan, melihat jadwal sendiri, laporan pribadi, dan kirim kunjungan ke SATUSEHAT. |
| Asisten Dokter | `dental-assistant` | Pendamping dokter. Melihat antrean, membantu pemeriksaan awal, anamnesis, rekam medis, odontogram, dan tindakan. Tidak menulis resep/rujukan. |
| Perawat | `nurse` | Pelayanan keperawatan. Melihat antrean, pemeriksaan awal, tanda vital, anamnesis, dan catatan pelayanan. |
| Farmasi | `pharmacy` | Apotek klinik. Memproses resep, mengelola obat/stok/batch/expired, pembelian ke supplier, dan laporan farmasi. |
| Kasir | `cashier` | Penerimaan pembayaran. Membuka/menutup shift, menagih invoice, menerima pembayaran, melihat piutang, dan laporan kasir. |
| Keuangan | `finance` | Back-office keuangan. Pendapatan, pengeluaran, piutang, hutang, kas & bank, dan laporan laba rugi / arus kas. Tidak menerima pembayaran di loket. |
| Auditor / Viewer | `auditor` | Hanya lihat. Riwayat pendaftaran, pasien, rekam medis, ringkasan keuangan, laporan, dan audit log. Tidak mengubah data operasional. |

### Matriks akses modul

| Modul | SaaS | Owner | Manager | Pendaftaran | Dokter | Asisten | Perawat | Farmasi | Kasir | Keuangan | Auditor |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Dashboard platform | ✓ | | | | | | | | | | |
| Tenant & langganan | ✓ | | | | | | | | | | |
| Dashboard klinik | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Pendaftaran & pasien | | ✓ | ✓ | ✓ | lihat | lihat | lihat | | lihat | | lihat |
| Antrean | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | | |
| Pelayanan / rekam medis | | ✓ | | | ✓ | ✓ | ✓ | | | | lihat |
| Dokter & jadwal | | ✓ | ✓ | lihat | jadwal sendiri | lihat | lihat | | | | |
| Farmasi | | ✓ | ✓ | | | | | ✓ | | | |
| Billing & kasir | | ✓ | ✓ | | | | | | ✓ | | |
| Keuangan | | ✓ | | | | | | | | ✓ | lihat |
| Laporan | | ✓ | ✓ | | pribadi | | | farmasi | kasir | keuangan | ✓ |
| Master data | | ✓ | ✓ | penjamin | | | | obat | | | |
| User, role, cabang | | ✓ | ✓ | | | | | | | | |
| SATUSEHAT / BPJS | | ✓ | ✓ | cek BPJS | kirim SATUSEHAT | | | | | | |
| Audit log | ✓ | ✓ | ✓ | | | | | | | | ✓ |

---

## Modul

### Platform SaaS

- **Dashboard SaaS** — ringkasan tenant, langganan, dan status klinik.
- **Tenant / Klinik** — daftar klinik, pendaftaran tenant baru, detail, dan status aktif/langganan.
- **Users platform** — pengguna platform dan aktivitasnya.
- **Subscription / Paket** — master paket, langganan tenant, invoice SaaS.
- **System** — master global/menu, integrasi tingkat platform, system log, audit log.
- **Support** — tiket bantuan tenant.
- **Settings** — pengaturan global platform.

### Operasional klinik

**Pendaftaran**

- Pasien baru/lama, kunjungan hari ini, riwayat kunjungan.
- Penjamin: umum, BPJS, asuransi, corporate, membership.
- Cek kepesertaan BPJS (VClaim) per klinik.

**Antrean**

- Antrean hari ini, panggil / skip / selesai.
- Monitor tampilan antrean (termasuk layar publik `/antrean/{token}`).

**Pelayanan klinis**

- Pemeriksaan, anamnesis, tanda vital, catatan pelayanan.
- Rekam medis, odontogram, diagnosis, tindakan, resep, rujukan.
- Riwayat pasien lintas kunjungan.
- Penyelesaian kunjungan oleh dokter.

**Dokter & jadwal**

- Profil dokter dan tenaga medis.
- Jadwal praktik per hari, jadwal hari ini, jadwal dokter yang login.
- Ruangan pelayanan per cabang.

**Farmasi**

- Resep masuk → diproses → selesai, plus riwayat.
- Master obat, stok, batch, kedaluwarsa, penyesuaian stok.
- Supplier, purchase order, penerimaan barang.
- Laporan stok, obat masuk/keluar, expired.

**Billing & kasir**

- Generate tagihan dari pelayanan, pembayaran, void.
- Piutang pasien, riwayat transaksi.
- Shift kasir: buka, transaksi shift, tutup, laporan kasir.

**Keuangan**

- Pendapatan harian/bulanan/per dokter.
- Pengeluaran, kategori, supplier.
- Piutang, hutang, kas & bank.
- Laporan pendapatan, pengeluaran, arus kas, piutang, laba rugi.

**Laporan klinik**

- Kunjungan, pendapatan, tindakan, pasien.
- Laporan pribadi dokter, operasional, medis, keuangan, farmasi.

### Master data & manajemen

- Klinik / poli, layanan, tindakan, tarif, obat, penjamin, ruangan.
- Cabang.
- Users & roles tenant.
- Pengaturan operasional klinik.

### Integrasi

Kredensial SATUSEHAT dan BPJS diisi per klinik oleh Owner/Manager, bukan dari `.env` global.

- **SATUSEHAT** — pengaturan dan pengiriman encounter kunjungan.
- **BPJS VClaim** — pengaturan dan cek kepesertaan.

Mode default development: sandbox / fake (`SATUSEHAT_FAKE=true`, `BPJS_FAKE=true`).

---

## Requirement

- PHP 8.3+
- Composer
- Node.js 18+ dan npm
- MySQL 8+

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Buat database MySQL, sesuaikan `DB_*` di `.env`, lalu:

```bash
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

Buka `http://127.0.0.1:8000`.

Variabel penting:

```env
APP_NAME=KlinikDent
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=klinikdent
DB_USERNAME=root
DB_PASSWORD=

ADMIN_EMAIL=superadmin@klinikdent.test
ADMIN_PASSWORD=password

QUEUE_CONNECTION=database
INTEGRATION_MODE=sandbox
SATUSEHAT_FAKE=true
BPJS_FAKE=true
```

## Akun demo

Password default: `ADMIN_PASSWORD` (biasanya `password`). Super Admin memakai `ADMIN_EMAIL`.

| Email | Role | Tenant |
| --- | --- | --- |
| `superadmin@klinikdent.test` | Super Admin SaaS | — |
| `owner@klinikdent.test` | Owner | Klinik Gigi A |
| `manager@klinikdent.test` | Manager | Klinik Gigi A |
| `registration@klinikdent.test` | Pendaftaran | Klinik Gigi A |
| `dentist@klinikdent.test` | Dokter Gigi | Klinik Gigi A |
| `assistant@klinikdent.test` | Asisten Dokter | Klinik Gigi A |
| `nurse@klinikdent.test` | Perawat | Klinik Gigi A |
| `pharmacy@klinikdent.test` | Farmasi | Klinik Gigi A |
| `cashier@klinikdent.test` | Kasir | Klinik Gigi A |
| `finance@klinikdent.test` | Keuangan | Klinik Gigi A |
| `auditor@klinikdent.test` | Auditor | Klinik Gigi A |
| `owner.b@klinikdent.test` | Owner | Klinik Gigi B |

Seeder juga membuat dua tenant demo: **Klinik Gigi A** (`klinik-a`) dan **Klinik Gigi B** (`klinik-b`).

## Perintah berguna

```bash
php artisan db:seed
php artisan app:production-check
php artisan app:backup
php artisan app:restore {file} --force
php artisan tenants:expire-subscriptions
```

Queue memakai koneksi `database`. Untuk job integrasi (SATUSEHAT/BPJS) jalankan:

```bash
php artisan queue:work
```

## Struktur singkat

```text
app/
├── Enums/                 # Role, status, interval, dsb.
├── Http/Controllers/      # Billing, Clinical, Finance, Pharmacy, Registration, Saas, ...
├── Policies/
├── Support/Access/        # PermissionCatalog, MenuCatalog
├── Support/Billing/
├── Support/Finance/
├── Support/Integrations/
├── Support/Registration/
└── Support/Saas/

resources/views/           # Blade per modul
database/seeders/          # Role, tenant, master, operasional demo
```

Sidebar dan permission per peran didefinisikan di:

- `app/Enums/RoleName.php`
- `app/Support/Access/PermissionCatalog.php`
- `app/Support/Access/MenuCatalog.php`
