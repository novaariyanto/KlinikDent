<header class="site-header" data-header>
    <div class="wrap header-inner">
        <a class="logos" href="{{ route('home') }}" aria-label="{{ $appName }} beranda">
            <img src="{{ $appLogo }}" height="52" alt="">
         
        </a>
        <nav class="nav" data-nav aria-label="Menu utama">
            <button
                class="nav-toggle"
                type="button"
                data-nav-toggle
                aria-controls="nav-menu"
                aria-expanded="false"
                aria-label="Buka menu"
            >
                <span></span>
            </button>
            @php
                $home = route('home');
            @endphp
            <ul class="nav-list" id="nav-menu">
                <li><a href="{{ $home }}#fitur">Fitur</a></li>
                <li><a href="{{ route('marketing.dokter-baru') }}">Untuk Dokter Baru</a></li>
                <li><a href="{{ route('marketing.klinik') }}">Klinik &amp; Multi-Cabang</a></li>
                <li><a href="{{ $home }}#edukasi">Artikel</a></li>
                <li><a href="{{ $home }}#harga">Harga</a></li>
            </ul>
            <div class="nav-cta">
                <a class="nav-login" href="{{ route('login') }}">Masuk</a>
                <a class="btn btn--primary" href="{{ route('register') }}" data-track="register_clicked">
                    <span class="btn-desktop">Coba {{ $appName }}</span>
                    <span class="btn-mobile">Coba Gratis</span>
                </a>
            </div>
        </nav>
    </div>
</header>
