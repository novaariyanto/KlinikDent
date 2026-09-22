@extends('marketing.layout')

@section('title', $page['title'].' — '.$appName)
@section('og_title', $page['title'].' — '.$appName)
@section('description', $page['description'])
@section('canonical', $canonical)

@section('content')
<section class="hero hero--page">
    <div class="wrap">
        <p class="eyebrow">Software klinik gigi</p>
        <h1>{{ $page['h1'] }}</h1>
        <p class="lede">{{ $page['lede'] }}</p>
        @include('marketing.partials.cta-pair', ['primaryTrack' => 'hero_cta_clicked'])
    </div>
</section>

<section class="section">
    <div class="wrap prose">
        <h2>Cara mengelola klinik gigi dalam satu alur</h2>
        <p>Aplikasi klinik gigi yang berguna adalah yang mengikuti pekerjaan harian: pendaftaran, antrean, pemeriksaan, odontogram, tindakan, resep, billing, lalu laporan. {{ $appName }} merangkai proses itu sebagai sistem manajemen klinik gigi, bukan kumpulan modul yang tidak saling terhubung.</p>
        <h2>Yang tercatat setiap hari</h2>
        <ul>
            <li>Pasien dan kunjungan</li>
            <li>Rekam medis dan odontogram</li>
            <li>Tindakan, tarif, dan pembayaran</li>
            <li>Farmasi, stok, dan resep</li>
            <li>Laporan kunjungan, tindakan, dan pendapatan</li>
        </ul>
        <h2>Untuk klinik yang sudah berjalan</h2>
        <p>Owner melihat ringkasan operasional. Staf memakai peran masing-masing. Data antar klinik terisolasi. Jika Anda membuka lokasi baru, lihat juga halaman <a href="{{ route('marketing.multi-cabang') }}">multi-cabang</a>.</p>
        <p><a href="{{ route('marketing.dokter-baru') }}">Untuk dokter gigi baru</a> · <a href="{{ route('marketing.rekam-medis') }}">Rekam medis gigi</a></p>
    </div>
</section>

<section class="section section--soft">
    <div class="wrap">
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
    </div>
</section>

<section class="final section--navy">
    <div class="wrap">
        <h2 class="section-title">Satu sistem untuk operasional klinik gigi Anda.</h2>
        @include('marketing.partials.cta-pair')
    </div>
</section>
@endsection
