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
use Database\Seeders\Concerns\CmsArticleBodies;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CmsContentSeeder extends Seeder
{
    use CmsArticleBodies;

    public function run(): void
    {
        SeoSetting::put('site_name', 'KlinikDent');
        SeoSetting::put('default_seo_title', 'Software Klinik Gigi & Odontogram Digital - KlinikDent');
        SeoSetting::put(
            'default_meta_description',
            'KlinikDent adalah software manajemen klinik gigi dengan rekam medis elektronik, odontogram digital FDI, SOAP, resep, kasir, dan laporan operasional.'
        );

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

        $articles = [
            [
                'title' => 'Apa Itu Software Klinik Gigi? Fitur dan Manfaatnya',
                'slug' => 'apa-itu-software-klinik-gigi',
                'category' => 'software-klinik-gigi',
                'keyword' => 'software klinik gigi',
                'clinical' => false,
                'publish' => true,
                'excerpt' => 'Software klinik gigi membantu mencatat pasien, kunjungan, rekam medis, odontogram, tindakan, dan billing dalam satu sistem.',
            ],
            [
                'title' => '10 Hal yang Perlu Diperhatikan Saat Memilih Software Klinik Gigi',
                'slug' => 'cara-memilih-software-klinik-gigi',
                'category' => 'software-klinik-gigi',
                'keyword' => 'cara memilih software klinik gigi',
                'clinical' => false,
                'publish' => true,
                'excerpt' => 'Pertimbangkan alur klinis, odontogram, keamanan data, multi-cabang, dan dukungan implementasi sebelum memilih aplikasi.',
            ],
            [
                'title' => 'Apa Itu Odontogram Digital? Pengertian dan Fungsinya',
                'slug' => 'apa-itu-odontogram-digital',
                'category' => 'odontogram-digital',
                'keyword' => 'odontogram digital',
                'clinical' => true,
                'publish' => true,
                'excerpt' => 'Odontogram digital adalah sistem pencatatan kondisi gigi pasien dalam bentuk diagram elektronik yang terhubung dengan rekam medis kunjungan.',
            ],
            [
                'title' => 'Panduan Memahami Penomoran Gigi FDI',
                'slug' => 'penomoran-gigi-fdi',
                'category' => 'odontogram-digital',
                'keyword' => 'penomoran gigi FDI',
                'clinical' => true,
                'publish' => true,
                'excerpt' => 'Sistem FDI membagi mulut menjadi kuadran dan memberi nomor dua digit untuk setiap gigi agar pencatatan klinis konsisten.',
            ],
            [
                'title' => 'Rekam Medis Elektronik untuk Dokter Gigi',
                'slug' => 'rekam-medis-elektronik-dokter-gigi',
                'category' => 'rekam-medis-elektronik',
                'keyword' => 'rekam medis elektronik dokter gigi',
                'clinical' => true,
                'publish' => true,
                'excerpt' => 'RME dokter gigi menghubungkan data pasien, SOAP, odontogram, diagnosis, dan riwayat perawatan dalam satu alur kunjungan.',
            ],
            [
                'title' => 'Cara Mengelola Klinik Gigi Secara Digital',
                'slug' => 'mengelola-klinik-gigi-secara-digital',
                'category' => 'operasional-klinik',
                'keyword' => 'digitalisasi klinik gigi',
                'clinical' => false,
                'publish' => true,
                'excerpt' => 'Digitalisasi klinik dimulai dari pendaftaran, rekam medis, tindakan, hingga billing yang saling terhubung.',
            ],
            [
                'title' => 'Persiapan Membuka Praktik Dokter Gigi Mandiri',
                'slug' => 'persiapan-membuka-praktik-dokter-gigi',
                'category' => 'software-klinik-gigi',
                'keyword' => 'membuka praktik dokter gigi',
                'clinical' => false,
                'publish' => true,
                'excerpt' => 'Praktik mandiri membutuhkan alur pasien, dokumentasi klinis, dan pencatatan keuangan yang rapi sejak hari pertama.',
            ],
            [
                'title' => 'Manfaat Aplikasi Manajemen Klinik Gigi Multi-Cabang',
                'slug' => 'manfaat-aplikasi-klinik-multi-cabang',
                'category' => 'operasional-klinik',
                'keyword' => 'software klinik gigi multi-cabang',
                'clinical' => false,
                'publish' => true,
                'excerpt' => 'Sistem multi-cabang membantu mengelola peran, isolasi data, dan laporan antar lokasi klinik.',
            ],
            [
                'title' => 'Persiapan Integrasi SATUSEHAT untuk Klinik Gigi',
                'slug' => 'persiapan-integrasi-satusehat-klinik-gigi',
                'category' => 'rekam-medis-elektronik',
                'keyword' => 'integrasi SATUSEHAT klinik gigi',
                'clinical' => true,
                'publish' => true,
                'excerpt' => 'Integrasi SATUSEHAT membutuhkan data kunjungan yang lengkap dan konfigurasi kredensial klinik yang benar sesuai status implementasi.',
            ],
            [
                'title' => 'Cara Memilih Aplikasi Klinik Gigi Sesuai Kebutuhan',
                'slug' => 'memilih-aplikasi-klinik-gigi-sesuai-kebutuhan',
                'category' => 'software-klinik-gigi',
                'keyword' => 'aplikasi klinik gigi',
                'clinical' => false,
                'publish' => true,
                'excerpt' => 'Sesuaikan pilihan aplikasi dengan skala praktik, kebutuhan odontogram, tim klinis, dan rencana pertumbuhan.',
            ],
        ];

        foreach ($articles as $article) {
            $body = $this->bodyFor($article['slug'], $article['title'], $article['excerpt']);
            $payload = [
                'title' => $article['title'],
                'excerpt' => $article['excerpt'],
                'body' => HtmlSanitizer::clean($body),
                'content_type' => 'article',
                'category_id' => $categoryMap[$article['category']]->id,
                'author_id' => $author->id,
                'seo_title' => Str::limit($article['title'].' | KlinikDent', 65, ''),
                'meta_description' => Str::limit($article['excerpt'], 155, ''),
                'primary_keyword' => $article['keyword'],
                'secondary_keywords' => ['klinik gigi', 'KlinikDent'],
                'needs_clinical_review' => $article['clinical'],
                'cta_label' => 'Coba KlinikDent',
                'cta_url' => '/register',
                'reading_time_minutes' => 6,
                'faq' => [
                    [
                        'q' => 'Apakah artikel ini menggantikan konsultasi profesional?',
                        'a' => 'Tidak. Artikel bersifat edukatif umum dan tidak menggantikan penilaian klinis tenaga kesehatan.',
                    ],
                ],
            ];

            if ($article['publish']) {
                $payload['status'] = CmsPostStatus::Published;
                $payload['published_at'] = now();
            } else {
                $payload['status'] = CmsPostStatus::Draft;
                $payload['published_at'] = null;
            }

            CmsPost::query()->updateOrCreate(
                ['slug' => $article['slug']],
                $payload
            );
        }
    }
}
