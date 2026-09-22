@extends('marketing.layout')

@section('title', 'Terms of Service — '.$appName)
@section('og_title', 'Terms of Service — '.$appName)
@section('description', 'Syarat penggunaan '.$appName.' untuk situs pemasaran, akun, dan layanan manajemen klinik gigi.')
@section('canonical', url('/terms'))

@section('content')
<article class="wrap legal">
    <h1 class="page-title">Terms of Service</h1>
    <p>Terakhir diperbarui: 22 September 2026</p>
    <p>Dengan menggunakan situs atau layanan {{ $appName }}, Anda menyetujui syarat berikut.</p>

    <h2>Layanan</h2>
    <p>{{ $appName }} adalah software manajemen klinik gigi berbasis web. Fitur yang tersedia mengikuti paket, konfigurasi klinik, dan implementasi yang disepakati, termasuk integrasi seperti WhatsApp, SATUSEHAT, dan BPJS VClaim.</p>

    <h2>Akun</h2>
    <p>Owner klinik bertanggung jawab atas pengguna, peran, dan data yang dimasukkan ke dalam tenant miliknya. Kredensial tidak boleh dibagikan kepada pihak yang tidak berwenang.</p>

    <h2>Data dan kepatuhan</h2>
    <p>Klinik tetap menjadi pengendali data pasiennya. {{ $appName }} menyediakan alat untuk mencatat dan mengirim data sesuai konfigurasi, tetapi tidak menggantikan kewajiban klinis, administratif, atau regulasi yang berlaku di klinik Anda.</p>

    <h2>Ketersediaan</h2>
    <p>Kami berupaya menjaga layanan tetap berjalan. Gangguan, pemeliharaan, atau perubahan fitur dapat terjadi. Syarat ini bukan jaminan hasil klinis atau jaminan pendapatan klinik.</p>

    <h2>Kontak</h2>
    <p>Pertanyaan terkait layanan dapat dikirim melalui formulir demo di halaman utama.</p>
</article>
@endsection
