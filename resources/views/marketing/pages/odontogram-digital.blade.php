@extends('marketing.layout')

@section('title', $page['title'].' — '.$appName)
@section('og_title', $page['title'].' — '.$appName)
@section('description', $page['description'])
@section('canonical', $canonical)

@section('content')
<section class="hero hero--page">
    <div class="wrap">
        <p class="eyebrow">Odontogram digital</p>
        <h1>{{ $page['h1'] }}</h1>
        <p class="lede">{{ $page['lede'] }}</p>
        @include('marketing.partials.cta-pair', ['primaryTrack' => 'hero_cta_clicked'])
    </div>
</section>

<section class="section">
    <div class="wrap feature-hero">
        <div class="prose">
            <h2>Cara membuat dan mengisi odontogram</h2>
            <p>Odontogram mencatat kondisi gigi pada kunjungan pasien. Di {{ $appName }}, odontogram memakai penomoran FDI dan dapat menandai kondisi per gigi maupun permukaan. Perubahan tersimpan sebagai riwayat, sehingga dokter tidak perlu mengingat kondisi dari kunjungan sebelumnya.</p>
            <h2>Simbol dan kondisi</h2>
            <p>Kondisi yang biasa dicatat meliputi karies, tumpatan, mahkota, impaksi, dan gigi yang sudah dicabut. Catatan ini menjadi bagian rekam medis, bukan gambar terpisah di kertas.</p>
            <h2>Contoh alur</h2>
            <ol>
                <li>Buka kunjungan pasien</li>
                <li>Isi pemeriksaan dan odontogram</li>
                <li>Tambahkan diagnosis dan tindakan</li>
                <li>Lihat kembali riwayat saat kontrol</li>
            </ol>
            <p><a href="{{ route('marketing.rekam-medis') }}">Rekam medis gigi</a> · <a href="{{ route('home') }}">Beranda {{ $appName }}</a></p>
        </div>
        <div class="mock" aria-label="Contoh odontogram digital">
            <div class="mock__bar">Odontogram · FDI</div>
            <div class="mock__body">
                @include('marketing.partials.odontogram')
            </div>
        </div>
    </div>
</section>

<section class="final section--navy">
    <div class="wrap">
        <h2 class="section-title">Catat kondisi gigi sebagai data, bukan hanya gambar.</h2>
        @include('marketing.partials.cta-pair')
    </div>
</section>
@endsection
