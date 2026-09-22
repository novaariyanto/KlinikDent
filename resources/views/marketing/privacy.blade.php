@extends('marketing.layout')

@section('title', 'Privacy Policy — '.$appName)
@section('og_title', 'Privacy Policy — '.$appName)
@section('description', 'Kebijakan privasi '.$appName.' menjelaskan data yang dikumpulkan saat Anda menggunakan situs dan layanan manajemen klinik gigi.')
@section('canonical', url('/privacy'))

@section('content')
<article class="wrap legal">
    <h1 class="page-title">Privacy Policy</h1>
    <p>Terakhir diperbarui: 22 September 2026</p>
    <p>Kebijakan ini menjelaskan bagaimana {{ $appName }} menangani informasi yang Anda berikan melalui situs pemasaran, pendaftaran akun, dan permintaan demo.</p>

    <h2>Data yang kami terima</h2>
    <p>Jika Anda mendaftar, kami memproses data klinik dan akun owner yang Anda isi, seperti nama klinik, subdomain, nama, email, dan kata sandi. Jika Anda mengirim permintaan demo, kami memproses nama, nama klinik, email, nomor WhatsApp, dan pesan yang Anda tulis. Jika Anda mengunduh starter kit, kami memproses nama, email, dan nomor WhatsApp untuk mengirim checklist.</p>

    <h2>Penggunaan data</h2>
    <p>Data digunakan untuk menyediakan layanan manajemen klinik, menghubungi Anda terkait demo atau akun, serta menjaga keamanan sistem. Kami tidak menjual data pasien atau data klinik kepada pihak ketiga untuk kepentingan iklan.</p>

    <h2>Data operasional klinik</h2>
    <p>Data pasien, rekam medis, dan operasional lain disimpan dalam tenant klinik masing-masing. Akses diatur berdasarkan peran yang ditetapkan klinik Anda.</p>

    <h2>Penyimpanan dan keamanan</h2>
    <p>Kami menerapkan kontrol akses, isolasi data per klinik, dan praktik keamanan wajar untuk lingkungan aplikasi web. Tidak ada sistem yang bebas dari risiko; klinik tetap bertanggung jawab atas kredensial penggunanya.</p>

    <h2>Kontak</h2>
    <p>Untuk pertanyaan privasi, kirim permintaan melalui formulir demo di halaman utama atau email yang Anda gunakan saat mendaftar.</p>
</article>
@endsection
