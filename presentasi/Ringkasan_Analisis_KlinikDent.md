# Ringkasan analisis KlinikDent.id

Pemeriksaan 12 Oktober 2026 dari kode sumber, route, controller, service, dan tampilan Blade. Aplikasi tidak dijalankan, jadi tidak ada hasil uji klik.

## Sengaja tidak dibawa ke presentasi

SATUSEHAT dan BPJS ada di kode, tetapi **tidak ditampilkan** pada presentasi ini. Jika ditanya, jawab bahwa penyesuaian kebutuhan, termasuk integrasi, dibahas dalam kerja sama pendampingan.

## Sudah tersedia dan terhubung ke antarmuka

- Pasien, pendaftaran, kunjungan, antrean, loket, layar monitor, dan booking sesi dokter.
- Pelayanan: keluhan, screening, pemeriksaan, odontogram FDI dewasa dan sulung, diagnosis ICD-10, tindakan, resep obat jadi dan racikan, rujukan (PDF), serta instruksi pasien (PDF).
- Dokter, jadwal, ruangan, peran, multi-klinik, cabang, dashboard sesuai peran, dan onboarding.
- Tagihan, kasir, pencatatan tunai/kartu/transfer/QRIS, shift, piutang, hutang, kas dan bank, laporan pendapatan, arus kas, dan laba rugi. QRIS dicatat sebagai metode, bukan pembayaran otomatis.
- Farmasi: resep sampai penyerahan, stok, batch, kedaluwarsa, FEFO, stok opname, retur, supplier, pembelian, dan penerimaan.
- WhatsApp (pengingat, promo, template, riwayat) setelah layanan dikonfigurasi.
- Portal pasien: lihat kunjungan dan unduh resume.
- Audit log, arsip praktik, serta langganan klinik dengan pembayaran paket lewat Midtrans.

## Perlu verifikasi sebelum dijanjikan

- Surat sakit, finalisasi rekam medis, adendum, dan form pesanan lab di layar dokter: berkas dan route ada, tetapi belum masuk tab pelayanan yang tampil.
- Daftar pesanan lab di menu farmasi bisa dibuka untuk ubah status.
- Pengiriman WhatsApp dan pembayaran Midtrans tidak diuji ke layanan sungguhan.

## Belum tersedia

- Rencana perawatan yang mengikat beberapa kunjungan. Yang ada adalah instruksi pada kunjungan berjalan.
- Integrasi katalog KFA. Yang ada adalah isian kode manual.
- Tiket bantuan platform. Menunya masih halaman kosong.

## Yang perlu diucapkan

KlinikDent.id membuka kerja sama pendampingan untuk menyesuaikan kebutuhan klinik. Prioritas ditentukan bersama, belum berupa persetujuan pengembangan pada pertemuan pertama.
