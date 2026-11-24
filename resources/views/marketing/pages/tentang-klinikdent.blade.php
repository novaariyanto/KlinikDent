@extends('marketing.layout')

@section('title', 'Tentang KlinikDent — Software Manajemen Klinik Gigi')
@section('og_title', 'Tentang KlinikDent — Software Manajemen Klinik Gigi')
@section('description', 'KlinikDent adalah software manajemen klinik gigi berbasis web dengan rekam medis elektronik, odontogram digital FDI, SOAP, resep, kasir, laporan, dan multi-cabang.')
@section('canonical', $canonical)

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'AboutPage',
            '@id' => $canonical.'#webpage',
            'url' => $canonical,
            'name' => 'Tentang KlinikDent',
            'description' => 'Identitas produk KlinikDent, target pengguna, fitur, dan cara menghubungi.',
            'isPartOf' => ['@id' => url('/').'#website'],
            'about' => ['@id' => url('/').'#organization'],
        ],
        [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => $appName,
            'url' => url('/'),
            'logo' => url($appLogo),
            'description' => 'Software manajemen klinik gigi dengan odontogram sebagai bagian dari rekam medis.',
        ],
        \App\Support\Marketing\SeoMeta::breadcrumbList([
            ['name' => 'Beranda', 'item' => url('/')],
            ['name' => 'Tentang KlinikDent', 'item' => $canonical],
        ]),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
<section class="hero hero--page hero--splash">
    <div class="wrap">
        <p class="eyebrow">Tentang produk</p>
        <h1>KlinikDent adalah software manajemen klinik gigi berbasis web.</h1>
        <p class="lede">Produk ini membantu praktik dan klinik gigi mengelola pasien, rekam medis elektronik, odontogram digital FDI, pemeriksaan SOAP, tindakan, resep, kasir, laporan, serta operasional multi-cabang dalam satu sistem.</p>
        @include('marketing.partials.cta-pair', ['primaryTrack' => 'hero_cta_clicked'])
    </div>
</section>

<section class="section">
    <div class="wrap prose">
        <h2>Nama resmi dan situs</h2>
        <p><strong>KlinikDent</strong> adalah nama produk aplikasi manajemen klinik gigi. Situs resmi: <a href="{{ url('/') }}">{{ url('/') }}</a>.</p>

        <h2>Untuk siapa</h2>
        <ul>
            <li>Dokter gigi yang baru membuka praktik mandiri.</li>
            <li>Klinik gigi satu lokasi yang ingin meninggalkan spreadsheet.</li>
            <li>Klinik yang berkembang ke beberapa cabang dengan kebutuhan peran dan laporan.</li>
        </ul>

        <h2>Fitur yang tersedia di produk</h2>
        <ul>
            @foreach ($softwareFeatures as $feature)
                <li>{{ $feature }}</li>
            @endforeach
        </ul>
        <p>Detail alur dapat dilihat di <a href="{{ route('marketing.odontogram') }}">odontogram digital</a>, <a href="{{ route('marketing.rekam-medis') }}">rekam medis gigi</a>, dan <a href="{{ route('marketing.multi-cabang') }}">multi-cabang</a>.</p>

        <h2>Platform</h2>
        <p>KlinikDent berjalan sebagai aplikasi web. Pengguna mengaksesnya melalui browser pada komputer atau perangkat yang didukung; tidak diperlukan klaim instalasi native di luar cakupan produk web ini.</p>

        <h2>Paket dan trial</h2>
        @if ($packages->isNotEmpty())
            <ul>
                @foreach ($packages as $package)
                    <li>
                        <strong>{{ $package->name }}</strong>
                        —
                        @if ($package->isFree())
                            gratis
                        @else
                            Rp {{ number_format((float) $package->price, 0, ',', '.') }} / {{ $package->interval->label() }}
                        @endif
                        @if ((int) $package->trial_days > 0)
                            (masa trial {{ $package->trial_days }} hari)
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p>Informasi paket ditampilkan di <a href="{{ route('home') }}#harga">bagian harga beranda</a> sesuai konfigurasi yang aktif.</p>
        @endif

        <h2>Keamanan dan data kesehatan</h2>
        <p>Halaman privat (dashboard, rekam medis, kasir, dan sejenisnya) memerlukan autentikasi dan otorisasi. Data pasien tidak dipublikasikan melalui halaman marketing, sitemap, atau structured data. Kebijakan privasi tersedia di <a href="{{ route('privacy') }}">/privacy</a>.</p>

        <h2>Kontak dan dukungan</h2>
        <p>Untuk uji coba, gunakan <a href="{{ route('register') }}">registrasi</a>. Untuk permintaan demo, isi formulir demo di <a href="{{ route('home') }}#demo">beranda</a>. Pertanyaan layanan diatur melalui kanal yang Anda cantumkan saat mengirim formulir.</p>

        <h2>Belajar lebih lanjut</h2>
        <p>Baca artikel di <a href="{{ route('blog.index') }}">blog KlinikDent</a> atau unduh ringkasan produk melalui materi starter kit di beranda bila tersedia.</p>
    </div>
</section>

@include('marketing.partials.cta-band', [
    'title' => 'Siap melihat alur KlinikDent di klinik Anda?',
    'lede' => 'Daftar untuk onboarding, atau jadwalkan demo sesuai kebutuhan klinik.',
])
@endsection
