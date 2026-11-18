@extends('marketing.layout')

@section('title', $page['title'].' — '.$appName)
@section('og_title', $page['title'].' — '.$appName)
@section('description', $page['description'])
@section('canonical', $canonical)

@include('marketing.partials.hub-schema')

@section('content')
<section class="hero hero--page hero--splash">
    <div class="wrap">
        <p class="eyebrow">Untuk dokter gigi baru</p>
        <h1>{{ $page['h1'] }}</h1>
        <p class="lede">{{ $page['lede'] }}</p>
        <div class="hero-actions">
            <a class="btn btn--primary" href="{{ route('register') }}" data-track="register_clicked">Mulai Klinik Anda</a>
            <a class="btn btn--ghost" href="{{ route('home') }}#demo" data-track="demo_clicked">Lihat Demo</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap prose">
        <h2>Jawaban singkat</h2>
        <p>{{ $page['lede'] }}</p>
        <h2>Mulai sederhana</h2>
        <p>Buat klinik, isi profil, dan mulai mencatat pasien pertama. Setelah daftar, {{ $appName }} menampilkan langkah onboarding: cabang utama, dokter, staf, layanan, dan tarif. Yang wajib hanya profil klinik, cabang, dan akun owner. Sisanya dapat dilanjutkan kemudian.</p>
        <h2>Rekam medis lebih terstruktur</h2>
        <p>Tidak perlu Excel terpisah untuk pasien, kunjungan, dan catatan gigi. Pemeriksaan SOAP, odontogram, diagnosis, tindakan, resep, dan billing mengikuti satu kunjungan.</p>
        <h2>Siap berkembang</h2>
        <p>Ketika staf bertambah, tetapkan peran yang sudah ada — pendaftaran, kasir, farmasi, dokter — termasuk satu orang dengan beberapa peran. Ketika cabang bertambah, data tetap memakai struktur cabang.</p>
        <p><a href="{{ route('marketing.odontogram') }}">Odontogram digital</a> · <a href="{{ route('marketing.rekam-medis') }}">Rekam medis gigi</a> · <a href="{{ route('home') }}#harga">Harga</a></p>
    </div>
</section>

<section class="section section--soft" id="starter-kit">
    <div class="wrap pricing">
        <div>
            <h2 class="section-title">Sedang Mempersiapkan Klinik Gigi Pertama Anda?</h2>
            <p class="section-lede">Unduh checklist administrasi, alur pasien, rekam medis, dan odontogram dasar.</p>
        </div>
        @include('marketing.partials.starter-form')
    </div>
</section>

@include('marketing.partials.cta-band', [
    'title' => 'Mulai dengan sistem yang sudah siap digunakan.',
    'lede' => 'Daftar, siapkan klinik, dan catat pasien pertama tanpa spreadsheet yang semakin rumit.',
])
@endsection
