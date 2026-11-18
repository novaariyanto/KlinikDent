<?php

namespace Database\Seeders\Concerns;

trait CmsArticleBodies
{
    protected function bodyFor(string $slug, string $title, string $excerpt): string
    {
        return match ($slug) {
            'apa-itu-software-klinik-gigi' => $this->articleSoftwareKlinikGigi($excerpt),
            'cara-memilih-software-klinik-gigi' => $this->articleCaraMemilih($excerpt),
            'apa-itu-odontogram-digital' => $this->articleApaItuOdontogram($excerpt),
            'penomoran-gigi-fdi' => $this->articlePenomoranFdi($excerpt),
            'rekam-medis-elektronik-dokter-gigi' => $this->articleRme($excerpt),
            'mengelola-klinik-gigi-secara-digital' => $this->articleDigitalisasi($excerpt),
            'persiapan-membuka-praktik-dokter-gigi' => $this->articlePraktikMandiri($excerpt),
            'manfaat-aplikasi-klinik-multi-cabang' => $this->articleMultiCabang($excerpt),
            'persiapan-integrasi-satusehat-klinik-gigi' => $this->articleSatusehat($excerpt),
            'memilih-aplikasi-klinik-gigi-sesuai-kebutuhan' => $this->articlePilihSesuaiKebutuhan($excerpt),
            default => '<p><strong>Jawaban singkat:</strong> '.e($excerpt).'</p>'
                .'<p>Artikel “'.e($title).'” membahas topik operasional klinik gigi secara edukatif.</p>'
                .'<p>Pelajari juga <a href="/odontogram-digital">odontogram digital</a> dan <a href="/rekam-medis-gigi">rekam medis gigi</a>, atau <a href="/register">coba KlinikDent</a>.</p>',
        };
    }

    protected function articleSoftwareKlinikGigi(string $excerpt): string
    {
        return <<<HTML
<p><strong>Jawaban singkat:</strong> {$excerpt}</p>
<h2>Apa itu software klinik gigi?</h2>
<p>Software klinik gigi adalah sistem informasi yang membantu praktik mencatat data pasien, kunjungan, rekam medis, odontogram, tindakan, resep, dan billing dalam satu alur kerja. Tujuannya mengurangi catatan terpisah dan memudahkan tim klinis serta administrasi bekerja dari data yang sama.</p>
<h2>Fitur yang biasanya dibutuhkan</h2>
<ul>
<li>Pendaftaran pasien dan antrean kunjungan.</li>
<li>Rekam medis kunjungan (termasuk SOAP bila dipakai klinik).</li>
<li><a href="/odontogram-digital">Odontogram digital</a> dengan penomoran yang konsisten.</li>
<li>Tindakan, resep, dan kasir/billing.</li>
<li>Laporan operasional serta pengaturan peran pengguna.</li>
</ul>
<h2>Siapa yang cocok memakai</h2>
<p>Praktik dokter gigi mandiri, klinik gigi satu lokasi, maupun klinik yang bertumbuh ke <a href="/multi-cabang">multi-cabang</a> dapat memakai software klinik gigi. Pilih sistem yang sesuai skala tim dan kebutuhan dokumentasi klinis Anda.</p>
<h2>Bagaimana KlinikDent membantu</h2>
<p>KlinikDent adalah aplikasi manajemen klinik gigi berbasis web yang menghubungkan alur pasien hingga billing. Anda dapat melihat ringkasan fitur di <a href="/klinik-gigi">halaman klinik gigi</a> atau <a href="/register">mencoba akun</a> sesuai paket yang tersedia.</p>
<h2>Batasan artikel</h2>
<p>Artikel ini bersifat edukatif umum dan tidak menggantikan penilaian klinis atau konsultasi hukum/regulasi. Tidak ada klaim peringkat pencarian atau testimoni yang tidak terverifikasi.</p>
HTML;
    }

    protected function articleCaraMemilih(string $excerpt): string
    {
        return <<<HTML
<p><strong>Jawaban singkat:</strong> {$excerpt}</p>
<h2>Mulai dari alur kerja nyata</h2>
<p>Sebelum membandingkan harga atau daftar fitur, tulis alur harian klinik: pendaftaran, pemeriksaan, tindakan, resep, pembayaran, dan laporan. Software yang baik menyesuaikan alur itu, bukan memaksa tim mengubah semuanya sekaligus.</p>
<h2>Sepuluh hal yang perlu dicek</h2>
<ol>
<li>Kelengkapan <a href="/rekam-medis-gigi">rekam medis gigi</a> dan riwayat kunjungan.</li>
<li>Ketersediaan <a href="/odontogram-digital">odontogram digital</a> (idealnya FDI) yang terhubung ke kunjungan.</li>
<li>Keamanan akses: login, peran, dan audit aktivitas penting.</li>
<li>Dukungan multi pengguna dan, bila perlu, <a href="/multi-cabang">multi-cabang</a>.</li>
<li>Billing/kasir yang selaras dengan tindakan.</li>
<li>Kemudahan onboarding untuk asisten dan dokter.</li>
<li>Kemampuan ekspor atau pelaporan untuk operasional.</li>
<li>Opsi integrasi pendukung (misalnya SATUSEHAT) sesuai status implementasi vendor.</li>
<li>Dukungan teknis dan dokumentasi yang jelas.</li>
<li>Harga dan masa uji yang transparan — lihat <a href="/#harga">informasi harga KlinikDent</a>.</li>
</ol>
<h2>Hindari jebakan umum</h2>
<p>Jangan memilih hanya karena daftar fitur terpanjang. Uji dengan data dummy, pastikan odontogram dan rekam medis tidak terpisah, dan konfirmasi apakah trial atau paket berbayar sesuai kebutuhan.</p>
<p><a href="/register">Coba KlinikDent</a> atau baca panduan terkait di <a href="/blog">blog</a>.</p>
HTML;
    }

    protected function articleApaItuOdontogram(string $excerpt): string
    {
        return <<<HTML
<p><strong>Jawaban singkat:</strong> {$excerpt}</p>
<h2>Apa itu odontogram digital?</h2>
<p>Odontogram digital adalah sistem pencatatan kondisi gigi pasien dalam bentuk diagram elektronik. Berbeda catatan kertas yang mudah tercecer, odontogram digital menyimpan status gigi per kunjungan dan dapat ditinjau kembali pada kontrol berikutnya.</p>
<h2>Mengapa penting di klinik gigi</h2>
<p>Tim klinis membutuhkan gambaran kondisi gigi yang konsisten agar rencana perawatan, tindakan, dan komunikasi antar dokter tidak bergantung pada memori atau catatan lepas. Odontogram yang terhubung ke <a href="/rekam-medis-gigi">rekam medis gigi</a> menjaga konteks kunjungan tetap utuh.</p>
<h2>Hubungan dengan penomoran FDI</h2>
<p>Banyak praktik memakai penomoran FDI agar setiap gigi punya identitas yang sama di seluruh dokumentasi. Pelajari dasar penomoran di artikel <a href="/blog/penomoran-gigi-fdi">Panduan Memahami Penomoran Gigi FDI</a> dan halaman produk <a href="/odontogram-digital">odontogram digital KlinikDent</a>.</p>
<h2>Penerapan di KlinikDent</h2>
<p>KlinikDent menyediakan odontogram sebagai bagian dari alur rekam medis kunjungan, bukan modul terpisah yang berdiri sendiri. Ini membantu dokter melihat riwayat dan status gigi saat pemeriksaan berlangsung.</p>
<h2>Batasan</h2>
<p>Penjelasan ini bersifat edukatif dan tidak menggantikan protokol klinis institusi Anda. Untuk mencoba alur produk, kunjungi <a href="/register">halaman registrasi</a>.</p>
HTML;
    }

    protected function articlePenomoranFdi(string $excerpt): string
    {
        return <<<HTML
<p><strong>Jawaban singkat:</strong> {$excerpt}</p>
<h2>Dasar sistem FDI</h2>
<p>Sistem FDI (Fédération Dentaire Internationale) membagi gigi geligi menjadi kuadran dan memberi nomor dua digit. Digit pertama menandai kuadran; digit kedua menandai posisi gigi di kuadran tersebut. Konsistensi nomor ini memudahkan komunikasi klinis dan pencatatan digital.</p>
<h2>Mengapa FDI dipakai di odontogram digital</h2>
<p>Ketika odontogram, diagnosis, dan tindakan memakai nomor yang sama, risiko salah gigi menurun. Di aplikasi, nomor FDI biasanya menjadi kunci untuk menandai kondisi pada diagram <a href="/odontogram-digital">odontogram digital</a>.</p>
<h2>Praktik pencatatan yang rapi</h2>
<ul>
<li>Sepakati standar penomoran di seluruh tim sebelum migrasi digital.</li>
<li>Catat kondisi per gigi pada kunjungan yang relevan, bukan di file terpisah.</li>
<li>Hubungkan temuan odontogram dengan catatan <a href="/rekam-medis-gigi">rekam medis</a> kunjungan.</li>
</ul>
<h2>Belajar lebih lanjut</h2>
<p>Baca juga <a href="/blog/apa-itu-odontogram-digital">Apa Itu Odontogram Digital</a>. Artikel ini tidak menggantikan pelatihan klinis formal.</p>
HTML;
    }

    protected function articleRme(string $excerpt): string
    {
        return <<<HTML
<p><strong>Jawaban singkat:</strong> {$excerpt}</p>
<h2>Rekam medis elektronik untuk praktik gigi</h2>
<p>Rekam medis elektronik (RME) dokter gigi menyimpan identitas pasien, riwayat kunjungan, keluhan, pemeriksaan, rencana, tindakan, dan dokumen pendukung dalam sistem digital. Untuk praktik gigi, RME idealnya terhubung dengan odontogram dan billing agar satu kunjungan punya jejak yang utuh.</p>
<h2>Komponen penting</h2>
<ul>
<li>Data pasien dan riwayat kunjungan.</li>
<li>Pemeriksaan (termasuk SOAP bila dipakai).</li>
<li><a href="/odontogram-digital">Odontogram</a> dan catatan kondisi gigi.</li>
<li>Diagnosis, tindakan, resep, dan instruksi.</li>
<li>Kontrol akses berdasarkan peran pengguna.</li>
</ul>
<h2>Manfaat operasional</h2>
<p>RME mengurangi duplikasi tulis-menulis, mempercepat kontrol pasien, dan memudahkan audit internal. Halaman <a href="/rekam-medis-gigi">rekam medis gigi KlinikDent</a> menjelaskan posisi fitur ini dalam produk.</p>
<h2>Keamanan data</h2>
<p>Data kesehatan memerlukan autentikasi, otorisasi, dan pembatasan akses. Jangan mempublikasikan rekam medis asli untuk keperluan marketing; gunakan data dummy pada pelatihan.</p>
<p><a href="/register">Coba KlinikDent</a> untuk melihat alur RME pada lingkungan uji Anda.</p>
HTML;
    }

    protected function articleDigitalisasi(string $excerpt): string
    {
        return <<<HTML
<p><strong>Jawaban singkat:</strong> {$excerpt}</p>
<h2>Urutan digitalisasi yang realistis</h2>
<p>Digitalisasi klinik gigi lebih aman jika bertahap: mulai dari pendaftaran dan data pasien, lanjut rekam medis kunjungan, kemudian odontogram, tindakan, dan kasir. Memaksa semua modul sekaligus sering membuat tim kewalahan.</p>
<h2>Checklist praktis</h2>
<ol>
<li>Petakan peran: front office, dokter, asisten, kasir.</li>
<li>Samakan master data tindakan dan tarif dasar.</li>
<li>Latih input kunjungan dengan data dummy.</li>
<li>Aktifkan <a href="/odontogram-digital">odontogram</a> setelah alur kunjungan stabil.</li>
<li>Hubungkan billing agar laporan harian konsisten.</li>
</ol>
<h2>Peran software</h2>
<p><a href="/klinik-gigi">Software manajemen klinik gigi</a> menjadi tulang punggung agar data tidak tersebar di spreadsheet. KlinikDent dirancang untuk alur tersebut dalam satu aplikasi web.</p>
<p>Lanjut ke <a href="/blog/cara-memilih-software-klinik-gigi">panduan memilih software</a> atau <a href="/register">daftar uji coba</a>.</p>
HTML;
    }

    protected function articlePraktikMandiri(string $excerpt): string
    {
        return <<<HTML
<p><strong>Jawaban singkat:</strong> {$excerpt}</p>
<h2>Persiapan nonklinis yang sering terlewat</h2>
<p>Dokter gigi yang membuka praktik mandiri perlu menyiapkan alur pasien, dokumentasi, dan pencatatan keuangan sejak hari pertama. Tanpa sistem, antrean dan riwayat perawatan mudah berantakan saat volume naik.</p>
<h2>Fondasi digital minimal</h2>
<ul>
<li>Pendaftaran pasien dan jadwal.</li>
<li>Rekam medis kunjungan.</li>
<li>Odontogram untuk dokumentasi gigi.</li>
<li>Kasir sederhana yang tercatat.</li>
</ul>
<p>Halaman <a href="/dokter-gigi-baru">untuk dokter gigi baru</a> merangkum sudut pandang produk KlinikDent untuk tahap ini.</p>
<h2>Langkah berikutnya</h2>
<p>Setelah operasional stabil, evaluasi kebutuhan multi pengguna, laporan, dan integrasi pendukung. Bandingkan opsi lewat <a href="/blog/memilih-aplikasi-klinik-gigi-sesuai-kebutuhan">panduan memilih aplikasi</a>.</p>
HTML;
    }

    protected function articleMultiCabang(string $excerpt): string
    {
        return <<<HTML
<p><strong>Jawaban singkat:</strong> {$excerpt}</p>
<h2>Tantangan klinik multi-cabang</h2>
<p>Saat klinik tumbuh ke beberapa lokasi, tantangan utamanya adalah konsistensi master data, pemisahan akses per cabang, dan laporan yang tetap bisa digabung secara aman. Spreadsheet terpisah per cabang biasanya sulit diaudit.</p>
<h2>Kapabilitas yang dibutuhkan</h2>
<ul>
<li>Isolasi data operasional per cabang.</li>
<li>Peran pengguna yang jelas per lokasi.</li>
<li>Laporan yang dapat difilter cabang.</li>
<li>Standar rekam medis dan odontogram yang sama.</li>
</ul>
<p>Pelajari pendekatan produk di <a href="/multi-cabang">halaman multi-cabang KlinikDent</a>.</p>
<h2>Kapan saatnya migrasi</h2>
<p>Pertimbangkan sistem multi-cabang ketika Anda sudah punya lebih dari satu lokasi aktif atau berencana membuka cabang dalam waktu dekat. Uji peran dan filter cabang sebelum go-live.</p>
HTML;
    }

    protected function articleSatusehat(string $excerpt): string
    {
        return <<<HTML
<p><strong>Jawaban singkat:</strong> {$excerpt}</p>
<h2>Posisi integrasi SATUSEHAT</h2>
<p>SATUSEHAT adalah platform interoperabilitas kesehatan. Untuk klinik gigi, kesiapan integrasi bergantung pada kelengkapan data kunjungan dan konfigurasi kredensial yang benar di sisi aplikasi klinik. Status fitur dapat berbeda antar vendor dan tahap implementasi.</p>
<h2>Persiapan data di klinik</h2>
<ul>
<li>Pastikan identitas pasien dan data kunjungan lengkap sesuai kebutuhan pengiriman.</li>
<li>Latih tim mengisi rekam medis secara konsisten.</li>
<li>Siapkan penanggung jawab teknis untuk kredensial dan pemantauan log.</li>
</ul>
<h2>Di KlinikDent</h2>
<p>KlinikDent mendukung integrasi SATUSEHAT untuk pengiriman encounter kunjungan sesuai konfigurasi klinik. Verifikasi status dan checklist di modul integrasi pada akun Anda; jangan menganggap seluruh resource SATUSEHAT otomatis tersedia tanpa konfigurasi.</p>
<p>Kaitkan kesiapan ini dengan kualitas <a href="/rekam-medis-gigi">rekam medis gigi</a> sehari-hari.</p>
HTML;
    }

    protected function articlePilihSesuaiKebutuhan(string $excerpt): string
    {
        return <<<HTML
<p><strong>Jawaban singkat:</strong> {$excerpt}</p>
<h2>Sesuaikan dengan skala praktik</h2>
<p>Praktik mandiri, klinik menengah, dan jaringan multi-cabang punya prioritas berbeda. Praktik kecil biasanya butuh onboarding cepat; klinik besar menekankan peran, laporan, dan kontrol akses.</p>
<h2>Matriks kebutuhan singkat</h2>
<ul>
<li>Fokus klinis: rekam medis + <a href="/odontogram-digital">odontogram</a>.</li>
<li>Fokus operasional: antrean, jadwal, billing.</li>
<li>Fokus pertumbuhan: <a href="/multi-cabang">multi-cabang</a> dan laporan.</li>
</ul>
<h2>Cara evaluasi vendor</h2>
<p>Minta demo dengan skenario milik Anda, uji dengan data dummy, dan pastikan harga/trial tertulis jelas. Untuk KlinikDent, lihat <a href="/#harga">harga</a>, <a href="/tentang-klinikdent">tentang KlinikDent</a>, lalu <a href="/register">registrasi</a> bila cocok.</p>
HTML;
    }
}
