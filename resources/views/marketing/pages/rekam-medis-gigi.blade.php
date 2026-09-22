@extends('marketing.layout')

@section('title', $page['title'].' — '.$appName)
@section('og_title', $page['title'].' — '.$appName)
@section('description', $page['description'])
@section('canonical', $canonical)

@section('content')
<section class="hero hero--page">
    <div class="wrap">
        <p class="eyebrow">Rekam medis gigi</p>
        <h1>{{ $page['h1'] }}</h1>
        <p class="lede">{{ $page['lede'] }}</p>
        @include('marketing.partials.cta-pair', ['primaryTrack' => 'hero_cta_clicked'])
    </div>
</section>

<section class="section">
    <div class="wrap prose">
        <h2>Cara membuat rekam medis pasien gigi</h2>
        <p>Rekam medis dokter gigi dimulai dari data pasien dan kunjungan. Pada pemeriksaan, dokter mencatat keluhan, anamnesis, temuan objektif, diagnosis, serta rencana instruksi. Odontogram masuk ke kunjungan yang sama.</p>
        <h2>Cara mencatat pemeriksaan gigi</h2>
        <ul>
            <li>SOAP: keluhan, pemeriksaan, assessment, instruksi</li>
            <li>Odontogram FDI per gigi dan permukaan</li>
            <li>Diagnosis dan tindakan</li>
            <li>Resep, rujukan, dan instruksi pasien</li>
        </ul>
        <h2>Contoh catatan yang rapi</h2>
        <p>Pasien kontrol scaling: kunjungan baru dibuka, odontogram menampilkan tumpatan lama, tindakan hari ini dicatat, billing mengikuti tarif layanan, riwayat tetap ada saat kunjungan berikutnya.</p>
        <p><a href="{{ route('marketing.odontogram') }}">Odontogram digital</a> · <a href="{{ route('marketing.klinik') }}">Mengelola klinik gigi</a></p>
    </div>
</section>

<section class="final section--navy">
    <div class="wrap">
        <h2 class="section-title">Simpan riwayat perawatan di satu tempat.</h2>
        @include('marketing.partials.cta-pair')
    </div>
</section>
@endsection
