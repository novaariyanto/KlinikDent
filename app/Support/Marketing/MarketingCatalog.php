<?php

namespace App\Support\Marketing;

final class MarketingCatalog
{
    /**
     * @return list<array{slug: string, route: string, title: string, teaser: string}>
     */
    public static function hubs(): array
    {
        return [
            [
                'slug' => 'odontogram-digital',
                'route' => 'marketing.odontogram',
                'title' => 'Odontogram digital',
                'teaser' => 'Cara mencatat kondisi gigi, penomoran FDI, dan menghubungkan odontogram dengan rekam medis kunjungan.',
            ],
            [
                'slug' => 'rekam-medis-gigi',
                'route' => 'marketing.rekam-medis',
                'title' => 'Rekam medis gigi',
                'teaser' => 'Alur mencatat pasien, kunjungan, pemeriksaan SOAP, diagnosis, dan riwayat perawatan.',
            ],
            [
                'slug' => 'dokter-gigi-baru',
                'route' => 'marketing.dokter-baru',
                'title' => 'Untuk dokter gigi baru',
                'teaser' => 'Mulai klinik pertama tanpa spreadsheet. Siapkan pasien, rekam medis, dan billing dari hari pertama.',
            ],
            [
                'slug' => 'klinik-gigi',
                'route' => 'marketing.klinik',
                'title' => 'Mengelola klinik gigi',
                'teaser' => 'Software dan sistem manajemen klinik gigi untuk operasional harian yang terhubung.',
            ],
            [
                'slug' => 'multi-cabang',
                'route' => 'marketing.multi-cabang',
                'title' => 'Klinik multi-cabang',
                'teaser' => 'Cabang, peran pengguna, isolasi data, dan laporan yang mengikuti pertumbuhan klinik.',
            ],
        ];
    }

    /**
     * @return list<array{title: string, items: list<string>}>
     */
    public static function featureGroups(): array
    {
        return [
            [
                'title' => 'Rekam Medis',
                'items' => [
                    'Data pasien',
                    'Kunjungan',
                    'Pemeriksaan SOAP',
                    'Diagnosis',
                    'Odontogram',
                    'Riwayat perawatan',
                ],
            ],
            [
                'title' => 'Pelayanan',
                'items' => [
                    'Tindakan',
                    'Resep',
                    'Rujukan',
                    'Instruksi pasien',
                ],
            ],
            [
                'title' => 'Operasional',
                'items' => [
                    'Pendaftaran',
                    'Antrean',
                    'Jadwal dokter',
                    'Billing & pembayaran',
                    'Farmasi',
                ],
            ],
            [
                'title' => 'Manajemen',
                'items' => [
                    'Dashboard',
                    'Laporan',
                    'Users & roles',
                    'Cabang',
                    'Master data',
                    'Audit log',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function softwareFeatures(): array
    {
        return [
            'Odontogram digital',
            'Rekam medis gigi',
            'Pendaftaran dan antrean',
            'Billing dan kasir',
            'Farmasi',
            'Laporan klinik',
            'Multi-cabang',
            'WhatsApp otomatis',
            'Integrasi SATUSEHAT (Encounter)',
            'Cek kepesertaan BPJS VClaim',
        ];
    }

    /**
     * @return array{title: string, description: string, h1: string, lede: string}|null
     */
    public static function page(string $slug): ?array
    {
        return match ($slug) {
            'odontogram-digital' => [
                'title' => 'Odontogram Digital untuk Rekam Medis Gigi',
                'description' => 'Pelajari cara mencatat odontogram digital, penomoran FDI, kondisi gigi, dan kaitannya dengan rekam medis kunjungan pasien.',
                'h1' => 'Odontogram Digital yang Menjadi Bagian dari Rekam Medis.',
                'lede' => 'Catat kondisi gigi secara terstruktur, lalu buka kembali riwayatnya saat pasien datang untuk kunjungan berikutnya.',
            ],
            'rekam-medis-gigi' => [
                'title' => 'Rekam Medis Gigi yang Terhubung dengan Kunjungan',
                'description' => 'Cara membuat rekam medis pasien gigi: kunjungan, pemeriksaan SOAP, odontogram, diagnosis, tindakan, dan riwayat perawatan.',
                'h1' => 'Rekam Medis Gigi yang Mengikuti Setiap Kunjungan Pasien.',
                'lede' => 'Data pasien, pemeriksaan, odontogram, dan tindakan tersimpan dalam satu alur — bukan file terpisah.',
            ],
            'dokter-gigi-baru' => [
                'title' => 'Software Klinik Gigi untuk Dokter yang Baru Membuka Praktik',
                'description' => 'Mulai klinik gigi pertama dengan sistem yang sudah siap: pasien, rekam medis, odontogram, billing, tanpa spreadsheet yang semakin rumit.',
                'h1' => 'Baru Lulus dan Mau Membuka Klinik Gigi?',
                'lede' => 'Jangan mulai dengan spreadsheet yang akan semakin rumit ketika pasien mulai bertambah.',
            ],
            'klinik-gigi' => [
                'title' => 'Software dan Sistem Manajemen Klinik Gigi',
                'description' => 'Kelola klinik gigi dengan satu sistem: pendaftaran, rekam medis, odontogram, tindakan, billing, farmasi, dan laporan operasional.',
                'h1' => 'Sistem Manajemen Klinik Gigi untuk Operasional Harian.',
                'lede' => 'Dari pasien datang sampai pembayaran, alur klinik gigi tercatat dalam satu tempat yang dirancang untuk dokter dan tim klinik.',
            ],
            'multi-cabang' => [
                'title' => 'Kelola Klinik Gigi Multi-Cabang',
                'description' => 'Struktur cabang, peran pengguna, isolasi data, dan laporan untuk klinik gigi yang sedang berkembang ke beberapa lokasi.',
                'h1' => 'Ketika Klinik Anda Berkembang, Sistemnya Harus Ikut Berkembang.',
                'lede' => 'Kelola operasional klinik dengan struktur data dan workflow yang siap mendukung pertumbuhan.',
            ],
            default => null,
        };
    }

    /**
     * @return list<array{q: string, a: string}>
     */
    public static function faqs(string $appName): array
    {
        return [
            [
                'q' => 'Apa itu '.$appName.'?',
                'a' => $appName.' adalah sistem manajemen klinik gigi untuk membantu mengelola pasien, rekam medis, odontogram, pemeriksaan, tindakan, billing, dan operasional klinik. Odontogram menjadi bagian dari rekam medis kunjungan, bukan modul terpisah.',
            ],
            [
                'q' => 'Apakah '.$appName.' cocok untuk dokter gigi yang baru membuka klinik?',
                'a' => 'Ya. Setelah daftar, owner menyiapkan klinik lewat langkah onboarding. Anda dapat mulai dari skala kecil — pasien, cabang utama, rekam medis, dan billing — lalu menambah dokter, staf, dan layanan seiring klinik berkembang.',
            ],
            [
                'q' => 'Apakah '.$appName.' memiliki odontogram digital?',
                'a' => 'Ya. Odontogram adalah salah satu fitur inti '.$appName.', dengan penomoran FDI, kondisi per gigi dan permukaan, serta riwayat perubahan yang tersimpan di rekam medis pasien.',
            ],
            [
                'q' => 'Apakah data pasien tersimpan dalam rekam medis?',
                'a' => 'Ya. Setiap kunjungan dapat mencatat pemeriksaan SOAP, odontogram, diagnosis, tindakan, resep, rujukan, dan instruksi pasien. Riwayat dapat dibuka kembali pada kunjungan berikutnya.',
            ],
            [
                'q' => 'Apakah '.$appName.' bisa digunakan untuk klinik multi-cabang?',
                'a' => 'Ya. Klinik tetap memakai konsep cabang, termasuk klinik dengan satu lokasi. Owner dapat menambah cabang, mengatur pengguna dan peran, serta menjaga isolasi data antar klinik. Paket langganan membatasi jumlah cabang sesuai pilihan paket.',
            ],
            [
                'q' => 'Apakah '.$appName.' bisa digunakan oleh beberapa role?',
                'a' => 'Ya. Peran yang tersedia meliputi Owner, Manager, Dokter Gigi, Asisten Dokter, Perawat, Petugas Pendaftaran, Kasir, Petugas Farmasi, Keuangan, dan Auditor. Satu pengguna dapat memiliki lebih dari satu peran.',
            ],
            [
                'q' => 'Apakah tersedia demo?',
                'a' => 'Ya. Isi formulir demo di halaman ini. Tim akan menghubungi Anda melalui email atau WhatsApp. Anda juga dapat mendaftar langsung dan mencoba alur setup klinik.',
            ],
            [
                'q' => $appName.' terintegrasi SATUSEHAT dan BPJS?',
                'a' => $appName.' dapat mengirim data encounter kunjungan ke SATUSEHAT sesuai konfigurasi klinik, dan menyediakan cek kepesertaan BPJS VClaim pada proses pendaftaran. Integrasi perlu diatur oleh klinik dan bukan pengiriman otomatis tanpa kredensial.',
            ],
        ];
    }
}
