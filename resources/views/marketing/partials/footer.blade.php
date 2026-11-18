<footer class="site-footer">
    <div class="wrap footer-grid">
        <div class="footer-brand">
            <img src="{{ $appLogo }}" width="140" height="28" alt="{{ $appName }}">
            <h2>{{ $appName }}</h2>
            <p>Software manajemen klinik gigi dengan rekam medis elektronik dan odontogram digital FDI.</p>
        </div>
        <div>
            <h3>Navigasi</h3>
            <ul>
                <li><a href="{{ route('home') }}">Beranda</a></li>
                <li><a href="{{ route('home') }}#harga">Harga</a></li>
                <li><a href="{{ route('home') }}#demo">Demo</a></li>
                <li><a href="{{ route('home') }}#faq">FAQ</a></li>
                <li><a href="{{ route('blog.index') }}">Blog</a></li>
                <li><a href="{{ route('marketing.tentang') }}">Tentang</a></li>
            </ul>
        </div>
        <div>
            <h3>Produk</h3>
            <ul>
                <li><a href="{{ route('home') }}#fitur">Fitur</a></li>
                <li><a href="{{ route('marketing.odontogram') }}">Odontogram digital</a></li>
                <li><a href="{{ route('marketing.rekam-medis') }}">Rekam medis gigi</a></li>
                <li><a href="{{ route('marketing.dokter-baru') }}">Untuk dokter baru</a></li>
                <li><a href="{{ route('marketing.klinik') }}">Sistem klinik gigi</a></li>
                <li><a href="{{ route('marketing.multi-cabang') }}">Multi-cabang</a></li>
            </ul>
        </div>
        <div>
            <h3>Belajar & Legal</h3>
            <ul>
                <li><a href="{{ route('blog.index') }}">Artikel blog</a></li>
                <li><a href="{{ route('privacy') }}">Kebijakan privasi</a></li>
                <li><a href="{{ route('terms') }}">Ketentuan layanan</a></li>
                <li><a href="{{ url('/llms.txt') }}">llms.txt</a></li>
                <li><a href="{{ url('/sitemap.xml') }}">Sitemap</a></li>
            </ul>
        </div>
        <div>
            <h3>Mulai</h3>
            <ul class="footer-contact">
                <li><a href="{{ route('register') }}" data-track="register_clicked">Coba {{ $appName }}</a></li>
                <li><a href="{{ route('home') }}#demo" data-track="demo_clicked">Jadwalkan demo</a></li>
                <li><a href="{{ route('login') }}">Masuk</a></li>
            </ul>
        </div>
    </div>
    <div class="wrap">
        <p class="copy">© {{ now()->year }} {{ $appName }}. Semua hak dilindungi.</p>
    </div>
</footer>
