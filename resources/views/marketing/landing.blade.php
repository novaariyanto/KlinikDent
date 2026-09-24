@extends('marketing.layout')

@section('title', $appName.' — Sistem Manajemen Klinik Gigi & Odontogram Digital')
@section('og_title', $appName.' — Sistem Manajemen Klinik Gigi & Odontogram Digital')
@section('description', 'Kelola pasien, rekam medis, odontogram, pemeriksaan, tindakan, billing, dan operasional klinik gigi dalam satu sistem.')
@section('canonical', url('/'))

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => $appName,
            'url' => url('/'),
            'logo' => url($appLogo),
            'description' => 'Sistem manajemen klinik gigi dengan odontogram sebagai bagian dari rekam medis.',
        ],
        [
            '@type' => 'SoftwareApplication',
            'name' => $appName,
            'applicationCategory' => 'HealthApplication',
            'applicationSubCategory' => 'Dental Clinic Management',
            'operatingSystem' => 'Web',
            'inLanguage' => 'id-ID',
            'url' => url('/'),
            'description' => 'Sistem manajemen klinik gigi untuk pasien, rekam medis, odontogram, pemeriksaan, tindakan, billing, dan operasional klinik.',
            'featureList' => $softwareFeatures,
            'publisher' => ['@id' => url('/').'#organization'],
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['a'],
                ],
            ])->values()->all(),
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS) !!}
</script>
@endpush

@section('content')
<section class="hero">
    <div class="wrap hero-grid">
        <div>
            <p class="eyebrow">Sistem manajemen klinik gigi</p>
            <h1>Bangun Klinik Gigi yang Rapi Sejak Pasien Pertama.</h1>
            <p class="lede">Kelola pasien, rekam medis, odontogram, pemeriksaan, tindakan, resep, billing, dan operasional klinik dalam satu sistem yang dirancang khusus untuk klinik gigi.</p>
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

<section class="section section--tight" id="trust">
    <div class="wrap">
        <p class="trust-line">Dibangun khusus untuk alur kerja klinik gigi</p>
        <ul class="trust">
            <li>Dirancang untuk dokter gigi dan tim klinik</li>
            <li>Odontogram di dalam rekam medis</li>
            <li>Siap dipakai sejak pasien pertama</li>
        </ul>
    </div>
</section>

<section class="section section--soft" id="masalah">
    <div class="wrap">
        <div class="section-head">
            <h2 class="section-title">Masih Mengelola Klinik dengan Cara Manual?</h2>
            <p class="section-lede">{{ $appName }} menyatukan alur tersebut dalam satu sistem yang dibuat khusus untuk klinik gigi.</p>
        </div>
        <div class="cards cards--four">
            <article class="card">
                <h3>Rekam medis tersebar</h3>
                <p>Data pasien masih tersimpan di kertas, Excel, atau file yang berbeda.</p>
            </article>
            <article class="card">
                <h3>Odontogram tidak terdokumentasi dengan baik</h3>
                <p>Riwayat kondisi gigi sulit dilacak ketika pasien kembali.</p>
            </article>
            <article class="card">
                <h3>Administrasi semakin kompleks</h3>
                <p>Pasien, pemeriksaan, tindakan, resep, pembayaran, dan laporan harus dicatat di tempat berbeda.</p>
            </article>
            <article class="card">
                <h3>Klinik mulai berkembang</h3>
                <p>Semakin banyak dokter, staf, dan cabang, semakin sulit menjaga data tetap rapi.</p>
            </article>
        </div>
        <div class="mid-cta">
            @include('marketing.partials.cta-pair')
        </div>
    </div>
</section>

<section class="section" id="odontogram">
    <div class="wrap feature-hero">
        <div class="feature-copy">
            <span class="badge">Fitur inti</span>
            <h2 class="section-title">Odontogram Digital yang Menjadi Bagian dari Rekam Medis Pasien.</h2>
            <p class="section-lede">Catat kondisi gigi secara terstruktur dan lihat riwayatnya kembali saat pasien datang untuk kunjungan berikutnya.</p>
            <ul class="check">
                <li>Penomoran FDI</li>
                <li>Kondisi gigi per gigi</li>
                <li>Kondisi berdasarkan permukaan gigi</li>
                <li>Riwayat perubahan kondisi</li>
                <li>Terhubung dengan kunjungan, pemeriksaan, diagnosis, dan tindakan</li>
            </ul>
            <p class="quote">Bukan sekadar gambar gigi. Odontogram menjadi bagian dari data rekam medis pasien.</p>
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

<section class="section section--soft" id="alur">
    <div class="wrap">
        <div class="section-head">
            <h2 class="section-title">Satu Alur dari Pasien Datang hingga Kunjungan Selesai.</h2>
            <p class="section-lede">Tidak perlu berpindah-pindah sistem untuk mencatat proses pelayanan pasien.</p>
        </div>
        <ol class="steps">
            <li><b>01</b><span>Pasien datang</span></li>
            <li><b>02</b><span>Pendaftaran</span></li>
            <li><b>03</b><span>Pemeriksaan</span></li>
            <li><b>04</b><span>Odontogram</span></li>
            <li><b>05</b><span>Diagnosis</span></li>
            <li><b>06</b><span>Tindakan</span></li>
            <li><b>07</b><span>Resep / Rujukan</span></li>
            <li><b>08</b><span>Billing</span></li>
            <li><b>09</b><span>Rekam medis tersimpan</span></li>
        </ol>
        <div class="mid-cta">
            @include('marketing.partials.cta-pair')
        </div>
    </div>
</section>

<section class="section" id="rekam-medis">
    <div class="wrap feature-hero">
        <div class="feature-copy">
            <h2 class="section-title">Rekam Medis Gigi yang Mengikuti Kunjungan Pasien.</h2>
            <p class="section-lede">Dokter mencatat di ruang periksa. Data kunjungan tetap utuh ketika pasien kembali.</p>
            <ul class="check">
                <li>Data pasien dan kunjungan</li>
                <li>Pemeriksaan SOAP</li>
                <li>Odontogram dan diagnosis</li>
                <li>Tindakan, resep, rujukan, dan instruksi pasien</li>
                <li>Riwayat perawatan yang dapat ditelusuri</li>
            </ul>
            <a class="btn btn--ghost" href="{{ route('marketing.rekam-medis') }}">Cara mencatat rekam medis gigi</a>
        </div>
        <div class="tablet" aria-label="Contoh daftar pasien hari ini">
            <div class="tablet__screen">
                <div class="mock__bar">Pasien hari ini · drg. Andi</div>
                <ul class="plist">
                    <li><span>Siti Rahayu</span><span>Pemeriksaan</span></li>
                    <li><span>Budi Santoso</span><span>Kontrol</span></li>
                    <li><span>Maya Putri</span><span>Tumpatan</span></li>
                    <li><span>Raka Wijaya</span><span>Scaling</span></li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="section section--soft" id="fitur">
    <div class="wrap">
        <div class="section-head">
            <h2 class="section-title">Fitur yang Mengikuti Alur Kerja Klinik Gigi</h2>
            <p class="section-lede">Dikelompokkan menurut pekerjaan harian, bukan daftar menu teknis.</p>
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
        <p class="note">Integrasi pendukung: pengingat WhatsApp sesuai aktivitas klinik, pengiriman encounter kunjungan ke SATUSEHAT, dan cek kepesertaan BPJS VClaim pada pendaftaran. Ketiganya dikonfigurasi per klinik.</p>
    </div>
</section>

<section class="section" id="baru">
    <div class="wrap feature-hero">
        <div class="feature-copy">
            <h2 class="section-title">Baru Lulus dan Mau Membuka Klinik Gigi?</h2>
            <p class="section-lede">Jangan mulai dengan spreadsheet yang akan semakin rumit ketika pasien mulai bertambah.</p>
            <ul class="check">
                <li><strong>Mulai sederhana.</strong> Buat klinik dan mulai mencatat pasien.</li>
                <li><strong>Rekam medis lebih terstruktur.</strong> Semua kunjungan tersimpan dalam satu tempat.</li>
                <li><strong>Siap berkembang.</strong> Bangun alur yang rapi sejak awal agar tidak perlu berganti sistem.</li>
            </ul>
            <div class="hero-actions">
                <a class="btn btn--primary" href="{{ route('register') }}" data-track="register_clicked">Mulai Klinik Anda</a>
                <a class="btn btn--ghost" href="{{ route('marketing.dokter-baru') }}">Untuk dokter gigi baru</a>
            </div>
        </div>
        <div class="mock" aria-label="Langkah setup klinik">
            <div class="mock__bar">Onboarding · Setup klinik</div>
            <ol class="onboard onboard--stack">
                <li>Profil klinik</li>
                <li>Cabang utama</li>
                <li>Dokter dan staf</li>
                <li>Layanan dan tarif</li>
            </ol>
        </div>
    </div>
</section>

<section class="section section--soft" id="starter-kit">
    <div class="wrap pricing">
        <div>
            <h2 class="section-title">Sedang Mempersiapkan Klinik Gigi Pertama Anda?</h2>
            <p class="section-lede">Dapatkan checklist praktis untuk mempersiapkan administrasi dan operasional klinik gigi.</p>
            <ul class="plain">
                <li>Checklist persiapan klinik dan peralatan</li>
                <li>Struktur data pasien dan contoh alur kunjungan</li>
                <li>Checklist rekam medis dan panduan dasar odontogram</li>
            </ul>
        </div>
        @include('marketing.partials.starter-form')
    </div>
</section>

<section class="section" id="multi-cabang">
    <div class="wrap">
        <div class="section-head">
            <h2 class="section-title">Ketika Klinik Anda Berkembang, Sistemnya Harus Ikut Berkembang.</h2>
            <p class="section-lede">Kelola operasional klinik dengan struktur data dan workflow yang siap mendukung pertumbuhan.</p>
        </div>
        <div class="org" aria-label="Struktur owner dan cabang">
            <div class="org__root">Owner</div>
            <div class="org__branches">
                <div>
                    <strong>Cabang A</strong>
                    <span>Dokter · Staf</span>
                </div>
                <div>
                    <strong>Cabang B</strong>
                    <span>Dokter · Staf</span>
                </div>
                <div>
                    <strong>Cabang C</strong>
                    <span>Dokter · Staf</span>
                </div>
            </div>
        </div>
        <ul class="check check--grid">
            <li>Data lebih terstruktur per cabang</li>
            <li>Role dan kontrol akses</li>
            <li>Laporan operasional dan keuangan</li>
            <li>Standar alur kerja yang sama</li>
        </ul>
        <p class="note">Dirancang untuk berkembang bersama klinik Anda. Jumlah cabang mengikuti paket langganan.</p>
        <div class="mid-cta">
            <a class="btn btn--primary" href="{{ route('home', ['intent' => 'cabang']).'#demo' }}" data-track="demo_clicked">Diskusikan Kebutuhan Klinik</a>
            <a class="btn btn--ghost" href="{{ route('marketing.multi-cabang') }}">Pelajari multi-cabang</a>
        </div>
    </div>
</section>

<section class="section section--navy" id="dental">
    <div class="wrap">
        <div class="section-head">
            <h2 class="section-title">Bukan Software Klinik Umum yang Ditambahkan Odontogram.</h2>
            <p class="section-lede">{{ $appName }} dibangun dengan memahami workflow klinik gigi — mulai dari pasien, kunjungan, pemeriksaan, odontogram, diagnosis, tindakan, hingga billing.</p>
        </div>
        <div class="compare">
            <article>
                <h3>Software klinik umum</h3>
                <ul>
                    <li>Pasien</li>
                    <li>Pemeriksaan</li>
                    <li>Billing</li>
                </ul>
            </article>
            <article class="compare--after">
                <h3>{{ $appName }}</h3>
                <ul>
                    <li>Pasien</li>
                    <li>Kunjungan</li>
                    <li>Pemeriksaan</li>
                    <li>Odontogram</li>
                    <li>Diagnosis</li>
                    <li>Tindakan</li>
                    <li>Resep</li>
                    <li>Billing</li>
                    <li>Riwayat perawatan</li>
                </ul>
            </article>
        </div>
    </div>
</section>

<section class="section" id="harga" data-track-view="pricing_viewed">
    <div class="wrap">
        <div class="section-head">
            <h2 class="section-title">Pilih paket yang sesuai dengan tahap perkembangan klinik Anda.</h2>
            <p class="section-lede">Mulai dari klinik kecil. Upgrade ketika klinik berkembang. Untuk kebutuhan multi-cabang, hubungi kami.</p>
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
        <p class="note">Harga mengikuti paket aktif di sistem. Diskusikan kebutuhan multi-cabang jika klinik Anda melewati batas paket.</p>
    </div>
</section>

<section class="section section--soft" id="demo">
    <div class="wrap pricing">
        <div>
            <h2 class="section-title">Ingin Melihat {{ $appName }} Bekerja di Klinik Anda?</h2>
            <p class="section-lede">Konsultasikan kebutuhan klinik dan lihat bagaimana {{ $appName }} menyesuaikan alur operasional Anda.</p>
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
        <div class="section-head">
            <h2 class="section-title">Belajar Mengelola Klinik Gigi</h2>
            <p class="section-lede">Artikel singkat tentang odontogram, rekam medis gigi, dan pengelolaan klinik.</p>
        </div>
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
        <div class="section-head">
            <h2 class="section-title">Pertanyaan yang sering diajukan</h2>
        </div>
        @include('marketing.partials.faq')
    </div>
</section>

<section class="final section--navy">
    <div class="wrap">
        <h2 class="section-title">Siapkan Sistem Klinik Gigi Anda dari Sekarang.</h2>
        <p class="section-lede">Mulai dengan pasien pertama. Bangun workflow yang rapi. Kembangkan klinik Anda dengan sistem yang siap mengikuti pertumbuhan.</p>
        @include('marketing.partials.cta-pair', ['secondaryClass' => 'btn--ghost'])
    </div>
</section>
@endsection
