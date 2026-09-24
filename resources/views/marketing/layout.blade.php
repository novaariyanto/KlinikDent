<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $appName.' — Sistem Manajemen Klinik Gigi & Odontogram Digital')</title>
    <meta name="description" content="@yield('description', 'Kelola pasien, rekam medis, odontogram, pemeriksaan, tindakan, billing, dan operasional klinik gigi dalam satu sistem.')">
    <link rel="canonical" href="@yield('canonical', url('/'))">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#0b1c2c">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="{{ $appName }}">
    <meta property="og:title" content="@yield('og_title', $appName.' — Sistem Manajemen Klinik Gigi & Odontogram Digital')">
    <meta property="og:description" content="@yield('description', 'Kelola pasien, rekam medis, odontogram, pemeriksaan, tindakan, billing, dan operasional klinik gigi dalam satu sistem.')">
    <meta property="og:url" content="@yield('canonical', url('/'))">
    <meta property="og:image" content="{{ url('/marketing/og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $appName }} — software manajemen klinik gigi">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', $appName.' — Sistem Manajemen Klinik Gigi & Odontogram Digital')">
    <meta name="twitter:description" content="@yield('description', 'Kelola pasien, rekam medis, odontogram, pemeriksaan, tindakan, billing, dan operasional klinik gigi dalam satu sistem.')">
    <meta name="twitter:image" content="{{ url('/marketing/og.png') }}">
    <link rel="icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    <link rel="favicon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    <link rel="favicon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    <link rel="favicon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    <link rel="favicon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    <link rel="favicon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    <link rel="favicon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    <link rel="favicon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    <link rel="favicon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    <link rel="favicon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    <link rel="favicon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    <link rel="favicon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="shortcut icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <meta name="google-site-verification" content="PP4J4lbbjulpthQ-xaoENPnr8gXhbiwua-qNLg7NVCs" />
    <link rel="preload" href="{{ asset('fonts/inter-latin-600.woff2') }}" as="font" type="font/woff2" crossorigin>
    <style>{!! file_get_contents(public_path('css/marketing.min.css')) !!}</style>
    @stack('head')
</head>
<body>
    <a class="skip-link" href="#main">Langsung ke konten</a>
    @include('marketing.partials.header')
    <main id="main">
        @yield('content')
    </main>
    @include('marketing.partials.footer')
    <script src="{{ asset('js/marketing.js') }}?v=3" defer></script>
</body>
</html>
