<?php

namespace Database\Seeders;

use App\Enums\CmsPostStatus;
use App\Models\CmsAuthor;
use App\Models\CmsCategory;
use App\Models\CmsKeyword;
use App\Models\CmsPost;
use App\Models\CmsTag;
use App\Models\SeoSetting;
use App\Support\Cms\HtmlSanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CmsContentSeeder extends Seeder
{
    public function run(): void
    {
        SeoSetting::put('site_name', 'KlinikDent');
        SeoSetting::put('default_seo_title', 'Software Klinik Gigi & Odontogram Digital | KlinikDent');
        SeoSetting::put('default_meta_description', 'KlinikDent adalah aplikasi manajemen klinik gigi dengan rekam medis elektronik, odontogram digital, pendaftaran pasien, billing, dan multi-cabang. Coba gratis.');

        $author = CmsAuthor::query()->updateOrCreate(
            ['slug' => 'tim-klinikdent'],
            [
                'name' => 'Tim KlinikDent',
                'role_title' => 'Content & Product',
                'bio' => 'Tim produk KlinikDent menulis panduan praktis tentang software klinik gigi dan digitalisasi praktik.',
                'is_active' => true,
            ]
        );

        $categories = [
            ['name' => 'Software Klinik Gigi', 'slug' => 'software-klinik-gigi', 'pillar' => 'software-klinik-gigi'],
            ['name' => 'Odontogram Digital', 'slug' => 'odontogram-digital', 'pillar' => 'odontogram-digital'],
            ['name' => 'Rekam Medis Elektronik', 'slug' => 'rekam-medis-elektronik', 'pillar' => 'rekam-medis-elektronik'],
            ['name' => 'Operasional Klinik', 'slug' => 'operasional-klinik', 'pillar' => 'operasional-klinik'],
        ];

        $categoryMap = [];
        foreach ($categories as $index => $item) {
            $categoryMap[$item['slug']] = CmsCategory::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'description' => 'Artikel seputar '.$item['name'].'.',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        foreach ([
            'software klinik gigi',
            'odontogram digital',
            'rekam medis elektronik dokter gigi',
            'aplikasi klinik gigi',
            'SATUSEHAT klinik gigi',
        ] as $tagName) {
            CmsTag::query()->updateOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            );
        }

        foreach ([
            ['software klinik gigi', 'software-klinik-gigi', '/klinik-gigi'],
            ['aplikasi klinik gigi', 'software-klinik-gigi', '/'],
            ['odontogram digital', 'odontogram-digital', '/odontogram-digital'],
            ['aplikasi odontogram', 'odontogram-digital', '/odontogram-digital'],
            ['rekam medis elektronik dokter gigi', 'rekam-medis-elektronik', '/rekam-medis-gigi'],
            ['software klinik gigi multi-cabang', 'operasional-klinik', '/multi-cabang'],
            ['integrasi SATUSEHAT klinik gigi', 'rekam-medis-elektronik', '/rekam-medis-gigi'],
            ['harga software klinik gigi', 'software-klinik-gigi', '/#harga'],
        ] as [$keyword, $pillar, $url]) {
            CmsKeyword::query()->updateOrCreate(
                ['slug' => Str::slug($keyword)],
                [
                    'keyword' => $keyword,
                    'pillar' => $pillar,
                    'intent' => 'informational',
                    'status' => 'planned',
                    'priority' => 'high',
                    'target_url' => $url,
                ]
            );
        }

        $drafts = [
            [
                'title' => 'Apa Itu Software Klinik Gigi? Fitur dan Manfaatnya',
                'slug' => 'apa-itu-software-klinik-gigi',
                'category' => 'software-klinik-gigi',
                'keyword' => 'software klinik gigi',
                'clinical' => false,
                'excerpt' => 'Software klinik gigi membantu mencatat pasien, kunjungan, rekam medis, odontogram, tindakan, dan billing dalam satu sistem.',
            ],
            [
                'title' => '10 Hal yang Perlu Diperhatikan Saat Memilih Software Klinik Gigi',
                'slug' => 'cara-memilih-software-klinik-gigi',
                'category' => 'software-klinik-gigi',
                'keyword' => 'cara memilih software klinik gigi',
                'clinical' => false,
                'excerpt' => 'Pertimbangkan alur klinis, odontogram, keamanan data, multi-cabang, dan dukungan implementasi sebelum memilih aplikasi.',
            ],
            [
                'title' => 'Apa Itu Odontogram Digital? Pengertian dan Fungsinya',
                'slug' => 'apa-itu-odontogram-digital',
                'category' => 'odontogram-digital',
                'keyword' => 'odontogram digital',
                'clinical' => true,
                'excerpt' => 'Odontogram digital mencatat kondisi gigi secara terstruktur dan menjadi bagian dari rekam medis kunjungan.',
            ],
            [
                'title' => 'Panduan Memahami Penomoran Gigi FDI',
                'slug' => 'penomoran-gigi-fdi',
                'category' => 'odontogram-digital',
                'keyword' => 'penomoran gigi FDI',
                'clinical' => true,
                'excerpt' => 'Sistem FDI membagi mulut menjadi kuadran dan memberi nomor dua digit untuk setiap gigi.',
            ],
            [
                'title' => 'Rekam Medis Elektronik untuk Dokter Gigi',
                'slug' => 'rekam-medis-elektronik-dokter-gigi',
                'category' => 'rekam-medis-elektronik',
                'keyword' => 'rekam medis elektronik dokter gigi',
                'clinical' => true,
                'excerpt' => 'RME dokter gigi menghubungkan data pasien, SOAP, odontogram, diagnosis, dan riwayat perawatan.',
            ],
            [
                'title' => 'Cara Mengelola Klinik Gigi Secara Digital',
                'slug' => 'mengelola-klinik-gigi-secara-digital',
                'category' => 'operasional-klinik',
                'keyword' => 'digitalisasi klinik gigi',
                'clinical' => false,
                'excerpt' => 'Digitalisasi klinik dimulai dari pendaftaran, rekam medis, tindakan, hingga billing yang saling terhubung.',
            ],
            [
                'title' => 'Persiapan Membuka Praktik Dokter Gigi Mandiri',
                'slug' => 'persiapan-membuka-praktik-dokter-gigi',
                'category' => 'software-klinik-gigi',
                'keyword' => 'membuka praktik dokter gigi',
                'clinical' => false,
                'excerpt' => 'Praktik mandiri membutuhkan alur pasien, dokumentasi klinis, dan pencatatan keuangan yang rapi sejak hari pertama.',
            ],
            [
                'title' => 'Manfaat Aplikasi Manajemen Klinik Gigi Multi-Cabang',
                'slug' => 'manfaat-aplikasi-klinik-multi-cabang',
                'category' => 'operasional-klinik',
                'keyword' => 'software klinik gigi multi-cabang',
                'clinical' => false,
                'excerpt' => 'Sistem multi-cabang membantu mengelola peran, isolasi data, dan laporan antar lokasi klinik.',
            ],
            [
                'title' => 'Persiapan Integrasi SATUSEHAT untuk Klinik Gigi',
                'slug' => 'persiapan-integrasi-satusehat-klinik-gigi',
                'category' => 'rekam-medis-elektronik',
                'keyword' => 'integrasi SATUSEHAT klinik gigi',
                'clinical' => true,
                'excerpt' => 'Integrasi SATUSEHAT membutuhkan data kunjungan yang lengkap dan konfigurasi kredensial klinik yang benar.',
            ],
            [
                'title' => 'Cara Memilih Aplikasi Klinik Gigi Sesuai Kebutuhan',
                'slug' => 'memilih-aplikasi-klinik-gigi-sesuai-kebutuhan',
                'category' => 'software-klinik-gigi',
                'keyword' => 'aplikasi klinik gigi',
                'clinical' => false,
                'excerpt' => 'Sesuaikan pilihan aplikasi dengan skala praktik, kebutuhan odontogram, tim klinis, dan rencana pertumbuhan.',
            ],
        ];

        foreach ($drafts as $draft) {
            $body = $this->body($draft['title'], $draft['excerpt'], $draft['slug']);
            CmsPost::query()->updateOrCreate(
                ['slug' => $draft['slug']],
                [
                    'title' => $draft['title'],
                    'excerpt' => $draft['excerpt'],
                    'body' => HtmlSanitizer::clean($body),
                    'status' => CmsPostStatus::Draft,
                    'content_type' => 'article',
                    'category_id' => $categoryMap[$draft['category']]->id,
                    'author_id' => $author->id,
                    'seo_title' => $draft['title'].' | KlinikDent',
                    'meta_description' => Str::limit($draft['excerpt'], 155, ''),
                    'primary_keyword' => $draft['keyword'],
                    'secondary_keywords' => ['klinik gigi', 'KlinikDent'],
                    'needs_clinical_review' => $draft['clinical'],
                    'cta_label' => 'Coba KlinikDent Gratis',
                    'cta_url' => '/register',
                    'reading_time_minutes' => 5,
                    'faq' => [
                        ['q' => 'Apakah artikel ini menggantikan konsultasi profesional?', 'a' => 'Tidak. Artikel bersifat edukatif umum dan tidak menggantikan penilaian klinis tenaga kesehatan.'],
                    ],
                ]
            );
        }
    }

    protected function body(string $title, string $excerpt, string $slug): string
    {
        return <<<HTML
<p><strong>Jawaban singkat:</strong> {$excerpt}</p>
<h2>Mengapa topik ini penting</h2>
<p>Klinik gigi membutuhkan pencatatan yang konsisten agar alur pasien, dokumentasi klinis, dan operasional tidak tersebar di spreadsheet atau catatan terpisah. Artikel “{$title}” merangkum poin praktis yang dapat diverifikasi dari kebutuhan operasional sehari-hari.</p>
<h2>Poin utama</h2>
<ul>
<li>Mulai dari alur pasien dan dokumentasi yang dipakai tim setiap hari.</li>
<li>Pastikan odontogram dan rekam medis saling terhubung pada kunjungan.</li>
<li>Pertimbangkan keamanan data, peran pengguna, dan kebutuhan multi-cabang.</li>
<li>Gunakan internal link ke halaman produk yang relevan, misalnya <a href="/odontogram-digital">odontogram digital</a> dan <a href="/rekam-medis-gigi">rekam medis gigi</a>.</li>
</ul>
<h2>Contoh penerapan</h2>
<p>Pada praktik mandiri, dokumentasi kunjungan biasanya mencakup identitas pasien, keluhan, pemeriksaan, rencana tindakan, dan billing. Sistem digital membantu menyimpan riwayat agar kontrol berikutnya lebih cepat.</p>
<h2>Batasan artikel</h2>
<p>Draft ini belum dipublikasikan. Konten klinis wajib ditinjau tenaga profesional yang relevan sebelum publish. Tidak ada klaim peringkat pencarian, statistik fiktif, atau testimoni yang tidak terverifikasi.</p>
<p>Slug target: <code>{$slug}</code>. CTA: coba KlinikDent melalui halaman registrasi bila alur cocok dengan kebutuhan klinik Anda.</p>
HTML;
    }
}
