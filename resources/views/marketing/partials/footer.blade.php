<footer class="site-footer">
    <div class="wrap footer-grid">
        <div>
            <img src="{{ $appLogo }}" height="28" alt="">
            <h2>{{ $appName }}</h2>
            <p>Software manajemen klinik gigi dengan odontogram sebagai bagian dari rekam medis.</p>
        </div>
        <div>
            <h3>Produk</h3>
            <ul>
                <li><a href="{{ route('home') }}#fitur">Fitur</a></li>
                <li><a href="{{ route('marketing.dokter-baru') }}">Untuk dokter gigi baru</a></li>
                <li><a href="{{ route('marketing.klinik') }}">Sistem klinik gigi</a></li>
                <li><a href="{{ route('marketing.multi-cabang') }}">Multi-cabang</a></li>
                <li><a href="{{ route('home') }}#harga">Harga</a></li>
                <li><a href="{{ route('home') }}#demo">Demo</a></li>
            </ul>
        </div>
        <div>
            <h3>Belajar</h3>
            <ul>
                <li><a href="{{ route('marketing.odontogram') }}">Odontogram digital</a></li>
                <li><a href="{{ route('marketing.rekam-medis') }}">Rekam medis gigi</a></li>
                <li><a href="{{ route('privacy') }}">Kebijakan privasi</a></li>
                <li><a href="{{ route('terms') }}">Ketentuan layanan</a></li>
            </ul>
        </div>
    </div>
    <div class="wrap">
        <p class="copy">© {{ now()->year }} {{ $appName }}. Semua hak dilindungi.</p>
    </div>
</footer>
