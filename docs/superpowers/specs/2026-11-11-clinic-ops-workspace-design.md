# Workspace Operasional Klinik Kecil

## Deteksi workspace

Kelas `App\Support\Access\WorkspaceDetector` mengembalikan `App\Enums\Workspace`:

| Workspace | Syarat |
| --- | --- |
| `platform` | `super-admin-saas` |
| `operational` | role `clinic-ops` **atau** (`registration.view` + `queue.manage` + `pharmacy.view` + `billing.view`) dan bukan owner/manager |
| `admin` | owner atau manager |
| `clinical` | dentist / dental-assistant (tanpa ops capability penuh) |
| `default` | lainnya (nurse/pharmacy/cashier/finance/auditor tunggal) |

## Dashboard Operasional

Saat workspace = `operational` (atau role `clinic-ops`), `/dashboard` menampilkan kartu tindak lanjut:

- Kunjungan hari ini, antrean menunggu, sedang dilayani
- Resep masuk (Sent)
- Tagihan terbuka, status shift kasir

Tautan cepat: pendaftaran baru, antrean, resep masuk, tagihan, pemeriksaan.

## Pohon menu `clinic-ops`

1. Dashboard Operasional  
2. Pasien & Pendaftaran  
3. Antrean & Pelayanan  
4. Farmasi & Resep  
5. Kasir & Pembayaran  
6. Laporan Operasional  
7. Lainnya (jadwal, penjamin, BPJS bila permission ada)

Permission item tetap memfilter visibility. Role granular (registration/nurse/…) tidak dihapus.
