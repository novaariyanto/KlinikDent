<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Starter Kit Klinik Gigi — {{ $appName }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #102033; line-height: 1.45; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 18px 0 6px; }
        p, li { color: #3a4b5c; }
        ul { margin: 0 0 8px; padding-left: 18px; }
        .muted { color: #5b6b7a; font-size: 11px; }
    </style>
</head>
<body>
    <h1>Starter Kit Persiapan Klinik Gigi</h1>
    <p class="muted">Checklist edukatif dari {{ $appName }}. Bukan pengganti izin, regulasi, atau nasihat hukum.</p>

    <h2>1. Checklist persiapan klinik</h2>
    <ul>
        <li>Nama klinik, alamat, dan nomor telepon yang akan dipakai operasional</li>
        <li>Identitas penanggung jawab klinik dan dokter gigi</li>
        <li>Jam praktik dan alur pasien dari datang sampai pulang</li>
        <li>Daftar peran: owner, pendaftaran, dokter, kasir, farmasi (bisa dirangkap)</li>
    </ul>

    <h2>2. Checklist peralatan (tingkat tinggi)</h2>
    <ul>
        <li>Unit dental, sterilisasi, dan bahan habis pakai dasar</li>
        <li>Ruang tunggu, ruang periksa, dan area kasir/farmasi</li>
        <li>Komputer atau tablet untuk rekam medis di ruang periksa</li>
        <li>Koneksi internet yang stabil untuk sistem berbasis web</li>
    </ul>

    <h2>3. Struktur data pasien</h2>
    <ul>
        <li>Nama lengkap, tanggal lahir, jenis kelamin, alamat</li>
        <li>Nomor telepon/WhatsApp untuk pengingat jadwal</li>
        <li>Penjamin: umum, BPJS, asuransi, atau perusahaan</li>
        <li>Riwayat medis yang relevan sebelum pemeriksaan gigi</li>
    </ul>

    <h2>4. Contoh workflow pasien</h2>
    <ul>
        <li>Pendaftaran → antrean → pemeriksaan</li>
        <li>Odontogram → diagnosis → tindakan</li>
        <li>Resep atau rujukan → billing → rekam medis tersimpan</li>
    </ul>

    <h2>5. Checklist rekam medis</h2>
    <ul>
        <li>Keluhan dan anamnesis (SOAP)</li>
        <li>Pemeriksaan dan diagnosis</li>
        <li>Odontogram per kunjungan</li>
        <li>Tindakan, resep, rujukan, dan instruksi pasien</li>
    </ul>

    <h2>6. Panduan dasar odontogram</h2>
    <ul>
        <li>Gunakan penomoran FDI agar catatan seragam antar dokter</li>
        <li>Catat kondisi per gigi dan permukaan, bukan hanya gambar lepas</li>
        <li>Simpan perubahan sebagai riwayat, jangan menimpa tanpa jejak</li>
        <li>Hubungkan odontogram dengan kunjungan yang sedang berjalan</li>
    </ul>

    <h2>7. Checklist administrasi klinik</h2>
    <ul>
        <li>Daftar layanan dan tarif</li>
        <li>Alur kasir, bukti pembayaran, dan rekap harian</li>
        <li>Stok obat dasar jika farmasi dijalankan di klinik</li>
        <li>Hak akses staf agar data pasien tidak terbuka untuk semua orang</li>
    </ul>
</body>
</html>
