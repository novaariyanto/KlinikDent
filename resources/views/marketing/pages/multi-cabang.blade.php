@extends('marketing.layout')

@section('title', $page['title'].' — '.$appName)
@section('og_title', $page['title'].' — '.$appName)
@section('description', $page['description'])
@section('canonical', $canonical)

@section('content')
<section class="hero hero--page">
    <div class="wrap">
        <p class="eyebrow">Klinik berkembang</p>
        <h1>{{ $page['h1'] }}</h1>
        <p class="lede">{{ $page['lede'] }}</p>
        <div class="hero-actions">
            <a class="btn btn--primary" href="{{ route('home', ['intent' => 'cabang']).'#demo' }}" data-track="demo_clicked">Diskusikan Kebutuhan Klinik</a>
            <a class="btn btn--ghost" href="{{ route('register') }}" data-track="register_clicked">Coba {{ $appName }}</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="org" aria-label="Struktur owner dan cabang">
            <div class="org__root">Owner</div>
            <div class="org__branches">
                <div><strong>Cabang A</strong><span>Dokter · Staf</span></div>
                <div><strong>Cabang B</strong><span>Dokter · Staf</span></div>
                <div><strong>Cabang C</strong><span>Dokter · Staf</span></div>
            </div>
        </div>
        <div class="prose">
            <h2>Satu sistem, beberapa lokasi</h2>
            <p>Klinik dengan satu lokasi tetap memakai cabang utama. Ketika lokasi bertambah, owner menambah cabang tanpa membuat sistem terpisah. Pengguna dapat dibatasi pada cabang tertentu. Data antar tenant klinik tetap terisolasi.</p>
            <h2>Yang tersedia saat ini</h2>
            <ul>
                <li>Master cabang, ruangan, dan jadwal dokter</li>
                <li>Users &amp; roles dengan permission</li>
                <li>Laporan kunjungan, tindakan, dan pendapatan</li>
                <li>Dashboard owner untuk ringkasan operasional</li>
            </ul>
            <p>Dirancang untuk berkembang bersama klinik Anda. Jumlah cabang dan pengguna mengikuti paket langganan. Untuk kebutuhan yang lebih besar, diskusikan bersama tim kami.</p>
            <p><a href="{{ route('marketing.klinik') }}">Sistem klinik gigi</a> · <a href="{{ route('home') }}#harga">Harga</a></p>
        </div>
    </div>
</section>

<section class="final section--navy">
    <div class="wrap">
        <h2 class="section-title">Siapkan struktur data sebelum cabang berikutnya dibuka.</h2>
        <div class="hero-actions">
            <a class="btn btn--light" href="{{ route('home', ['intent' => 'cabang']).'#demo' }}" data-track="demo_clicked">Diskusikan Kebutuhan Klinik</a>
            <a class="btn btn--ghost" href="{{ route('register') }}" data-track="register_clicked">Coba {{ $appName }}</a>
        </div>
    </div>
</section>
@endsection
