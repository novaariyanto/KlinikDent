@extends('marketing.layout')

@section('title', 'Software Klinik Gigi & Odontogram Digital - KlinikDent')
@section('og_title', 'Software Klinik Gigi & Odontogram Digital - KlinikDent')
@section('description', 'KlinikDent adalah software manajemen klinik gigi dengan rekam medis elektronik, odontogram digital FDI, SOAP, resep, kasir, dan laporan operasional.')
@section('canonical', url('/'))

@push('head')
@php
    $orgId = url('/').'#organization';
    $sameAs = collect($seo['social_profiles'] ?? [])->filter(fn ($u) => is_string($u) && $u !== '')->values()->all();
    $offers = collect($packages ?? [])->map(function ($package) {
        $amount = (float) $package->price;
        $offer = [
            '@type' => 'Offer',
            'name' => $package->name,
            'priceCurrency' => 'IDR',
            'price' => $amount <= 0 ? '0' : (string) $amount,
            'url' => url('/#harga'),
            'availability' => 'https://schema.org/InStock',
        ];
        if (method_exists($package, 'interval') && $package->interval) {
            $offer['description'] = 'Paket '.$package->name.' / '.strtolower($package->interval->label());
        }

        return $offer;
    })->values()->all();

    $howToSteps = collect($startSteps ?? [])->values()->map(fn ($step, $i) => [
        '@type' => 'HowToStep',
        'position' => $i + 1,
        'name' => $step['name'],
        'text' => $step['text'],
    ])->all();

    $organization = [
        '@type' => 'Organization',
        '@id' => $orgId,
        'name' => $seo['organization_name'] ?? $appName,
        'url' => url('/'),
        'logo' => url($appLogo),
        'description' => $seo['organization_description'] ?? 'Sistem manajemen klinik gigi dengan odontogram sebagai bagian dari rekam medis.',
    ];
    if ($sameAs !== []) {
        $organization['sameAs'] = $sameAs;
    }

    $softwareApp = [
        '@type' => 'SoftwareApplication',
        'name' => $appName,
        'applicationCategory' => 'HealthApplication',
        'applicationSubCategory' => 'Dental Clinic Management',
        'operatingSystem' => 'Web',
        'inLanguage' => 'id-ID',
        'url' => url('/'),
        'description' => 'Sistem manajemen klinik gigi untuk pasien, rekam medis, odontogram, pemeriksaan, tindakan, billing, dan operasional klinik.',
        'featureList' => $softwareFeatures,
        'publisher' => ['@id' => $orgId],
    ];
    if (count($offers) === 1) {
        $softwareApp['offers'] = $offers[0];
    } elseif (count($offers) > 1) {
        $softwareApp['offers'] = [
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'IDR',
            'lowPrice' => (string) collect($offers)->min(fn ($o) => (float) $o['price']),
            'highPrice' => (string) collect($offers)->max(fn ($o) => (float) $o['price']),
            'offerCount' => count($offers),
            'offers' => $offers,
        ];
    }

    $graph = [
        $organization,
        [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => $appName,
            'url' => url('/'),
            'publisher' => ['@id' => $orgId],
            'inLanguage' => 'id-ID',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url('/blog/cari').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
        $softwareApp,
        \App\Support\Marketing\SeoMeta::breadcrumbList([
            ['name' => 'Beranda', 'item' => url('/')],
        ]),
    ];
    if ($howToSteps !== []) {
        $graph[] = [
            '@type' => 'HowTo',
            'name' => 'Mulai memakai '.$appName,
            'description' => 'Langkah memulai sistem manajemen klinik gigi '.$appName.'.',
            'step' => $howToSteps,
        ];
    }
    $graph[] = [
        '@type' => 'FAQPage',
        'mainEntity' => collect($faqs)->map(fn ($item) => [
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $item['a'],
            ],
        ])->values()->all(),
    ];
@endphp
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => $graph,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS) !!}
</script>
@endpush

@section('content')
<section class="hero hero--splash">
    <div class="wrap hero-grid">
        <div>
            <p class="eyebrow">{{ $appName }}</p>
            <h1>Belum menemukan software klinik gigi yang tepat?</h1>
            <p class="lede">Kelola pasien, rekam medis elektronik, odontogram digital FDI, pemeriksaan, tindakan, resep, billing, dan operasional klinik dalam satu sistem untuk klinik gigi di Indonesia.</p>
            <ul class="hero-proof" aria-label="Keunggulan singkat">
                <li>Odontogram di RME</li>
                <li>PSE &amp; SatuSehat</li>
                <li>Siap multi-cabang</li>
            </ul>
            @include('marketing.partials.cta-pair', ['primaryTrack' => 'hero_cta_clicked', 'secondaryTrack' => 'demo_clicked'])
            <p class="micro">Tidak perlu membangun sistem sendiri. Mulai dari pasien pertama dan kembangkan bersama klinik Anda.</p>
        </div>
        <div class="mock mock--hero" aria-label="Contoh tampilan odontogram {{ $appName }}">
            <div class="mock__bar">
                <span class="mock__dots" aria-hidden="true"><i></i><i></i><i></i></span>
                Rekam medis · Odontogram
            </div>
            <div class="mock__body">
                <div class="patient-card">
                    <strong>Siti Rahayu</strong>
                    <span>32 tahun · Kunjungan hari ini</span>
                    <p>drg. Andi Pratama</p>
                    <p>Gigi 16: karies oklusal</p>
                </div>
                @include('marketing.partials.odontogram', ['simple' => true, 'interactive' => false])
            </div>
        </div>
    </div>
</section>

@include('marketing.partials.trust-regs')

@include('marketing.partials.benefit-grid', [
    'title' => 'Keuntungan memakai sistem RME '.$appName,
    'lede' => 'Dirancang khusus untuk dokter gigi dan tim klinik — bukan modul odontogram tambahan pada software umum.',
])

@include('marketing.partials.start-steps')

<section class="section section--soft" id="odontogram">
    <div class="wrap feature-spot">
        <div class="feature-copy">
            <span class="badge">Fitur inti</span>
            <h2 class="section-title">Odontogram digital di dalam rekam medis pasien</h2>
            <p class="section-lede">Catat kondisi gigi secara terstruktur dan buka riwayatnya saat pasien kembali.</p>
            <ul class="check">
                <li>Penomoran FDI</li>
                <li>Kondisi gigi per gigi dan permukaan</li>
                <li>Riwayat perubahan kondisi</li>
                <li>Terhubung kunjungan, diagnosis, dan tindakan</li>
            </ul>
            <a class="btn btn--primary" href="{{ route('marketing.odontogram') }}">Pelajari odontogram digital</a>
        </div>
        <div class="mock" aria-label="Mockup odontogram digital">
            <div class="mock__bar">
                <span class="mock__dots" aria-hidden="true"><i></i><i></i><i></i></span>
                Pasien · Odontogram · Diagnosis · Tindakan
            </div>
            <div class="mock__body">
                @include('marketing.partials.odontogram')
            </div>
        </div>
    </div>
</section>

<section class="section" id="fitur">
    <div class="wrap">
        <div class="section-head section-head--center">
            <h2 class="section-title">Fitur unggulan {{ $appName }}</h2>
            <p class="section-lede">Dikelompokkan menurut pekerjaan harian klinik gigi, bukan daftar menu teknis.</p>
        </div>
        <div class="cards">
            @foreach ($featureGroups as $group)
                <article class="card">
                    <h3>{{ $group['title'] }}</h3>
                    <ul class="plain">
                        @foreach ($group['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
        <p class="note" style="text-align:center">Integrasi pendukung: WhatsApp, SATUSEHAT Encounter, dan BPJS VClaim — dikonfigurasi per klinik.</p>
        <div class="mid-cta" style="justify-content:center">
            <a class="btn btn--ghost" href="{{ route('marketing.dokter-baru') }}">Untuk dokter gigi baru</a>
            <a class="btn btn--ghost" href="{{ route('marketing.multi-cabang') }}">Klinik multi-cabang</a>
            <a class="btn btn--ghost" href="{{ route('marketing.rekam-medis') }}">Rekam medis gigi</a>
        </div>
    </div>
</section>

@include('marketing.partials.guarantee-grid')

<section class="section" id="harga" data-track-view="pricing_viewed">
    <div class="wrap">
        <div class="section-head section-head--center">
            <h2 class="section-title">Pilih paket sesuai tahap klinik Anda</h2>
            <p class="section-lede">Mulai dari skala kecil. Upgrade saat klinik berkembang. Diskusikan multi-cabang jika melewati batas paket.</p>
        </div>
        @if ($packages->isNotEmpty())
            <div class="price-grid">
                @foreach ($packages as $package)
                    @php
                        $amount = (float) $package->price;
                    @endphp
                    <article class="card {{ $package->isFree() ? '' : 'card--accent' }}">
                        <h3>{{ $package->name }}</h3>
                        <p class="price-value">
                            @if ($amount <= 0)
                                Gratis
                            @else
                                Rp {{ number_format($amount, 0, ',', '.') }}
                                <span>/ {{ strtolower($package->interval->label()) }}</span>
                            @endif
                        </p>
                        <ul class="plain">
                            @if ($package->trial_days)
                                <li>Masa uji {{ $package->trial_days }} hari</li>
                            @endif
                            <li>{{ $package->max_branches ? 'Hingga '.$package->max_branches.' cabang' : 'Cabang tidak dibatasi' }}</li>
                            <li>{{ $package->max_users ? 'Hingga '.$package->max_users.' pengguna' : 'Pengguna tidak dibatasi' }}</li>
                        </ul>
                        <a class="btn btn--primary" href="{{ route('register') }}" data-track="register_clicked">Coba {{ $appName }}</a>
                    </article>
                @endforeach
            </div>
        @endif
        <p class="note" style="text-align:center">Harga mengikuti paket aktif di sistem.</p>
    </div>
</section>

<section class="section section--soft" id="demo">
    <div class="wrap pricing">
        <div>
            <h2 class="section-title">Ingin melihat {{ $appName }} di klinik Anda?</h2>
            <p class="section-lede">Konsultasikan kebutuhan klinik dan lihat bagaimana {{ $appName }} menyesuaikan alur operasional Anda.</p>
            <ul class="check">
                <li>Demo sesuai skenario klinik Anda</li>
                <li>Tanpa komitmen berlangganan di formulir ini</li>
                <li>Atau <a href="{{ route('register') }}">daftar langsung</a> dan mulai onboarding</li>
            </ul>
        </div>
        <form class="form" method="POST" action="{{ route('demo.store') }}" novalidate data-track-submit="demo_clicked">
            @csrf
            <p class="hp" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </p>
            @if (session('demo_status'))
                <p class="alert" role="status">{{ session('demo_status') }}</p>
            @endif
            @if ($errors->demo->any())
                <ul class="errors">
                    @foreach ($errors->demo->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <label>Nama
                <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required>
            </label>
            <label>Nama klinik
                <input type="text" name="clinic" value="{{ old('clinic') }}" required>
            </label>
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
            </label>
            <label>Nomor WhatsApp
                <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" required>
            </label>
            <label>Pesan
                <textarea name="message" rows="3">{{ old('message', request('intent') === 'cabang' ? 'Kami ingin mendiskusikan kebutuhan klinik multi-cabang.' : '') }}</textarea>
            </label>
            <button class="btn btn--primary" type="submit" data-track="demo_clicked">Jadwalkan Demo</button>
        </form>
    </div>
</section>

<section class="section" id="edukasi">
    <div class="wrap">
        <div class="section-head section-head--center">
            <h2 class="section-title">Belajar mengelola klinik gigi</h2>
            <p class="section-lede">Panduan produk dan artikel tentang odontogram, rekam medis gigi, dan digitalisasi klinik.</p>
        </div>
        @if (($latestPosts ?? collect())->isNotEmpty())
            <div class="cards" style="margin-bottom:1.5rem">
                @foreach ($latestPosts as $post)
                    <article class="card blog-card">
                        @if ($post->featuredMedia)
                            <img
                                class="blog-card__media"
                                src="{{ $post->featuredMedia->url() }}"
                                alt="{{ $post->featuredMedia->alt ?: $post->title }}"
                                width="640"
                                height="360"
                                loading="lazy"
                                decoding="async"
                            >
                        @endif
                        <div class="blog-card__body">
                            <h3>{{ $post->title }}</h3>
                            <p>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->body), 120) }}</p>
                            <a href="{{ route('blog.show', $post->slug) }}">Baca artikel</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <p style="margin-bottom:1.25rem;text-align:center"><a href="{{ route('blog.index') }}">Lihat semua artikel blog →</a></p>
        @endif
        <div class="cards">
            @foreach ($hubs as $hub)
                <article class="card">
                    <h3>{{ $hub['title'] }}</h3>
                    <p>{{ $hub['teaser'] }}</p>
                    <a href="{{ route($hub['route']) }}">Baca selengkapnya</a>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="section section--soft" id="faq">
    <div class="wrap">
        <div class="section-head section-head--center">
            <h2 class="section-title">Pertanyaan yang sering diajukan</h2>
        </div>
        @include('marketing.partials.faq')
    </div>
</section>

@include('marketing.partials.cta-band')
@endsection
