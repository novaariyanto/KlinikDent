<!DOCTYPE html>
<html lang="id">
<head>
    @php
        $seo = $seo ?? \App\Support\Marketing\SeoMeta::defaults();
        $defaultTitle = $seo['default_seo_title'] ?? ($appName.' — Sistem Manajemen Klinik Gigi & Odontogram Digital');
        $defaultDescription = $seo['default_meta_description'] ?? ($seo['site_description'] ?? '');
        $ogImage = \App\Support\Marketing\SeoMeta::ogImageUrl(trim($__env->yieldContent('og_image')) ?: null);
        $robots = trim($__env->yieldContent('robots')) ?: 'index,follow';
        $ga4 = $seo['ga4_id'] ?? '';
        $gtm = $seo['gtm_id'] ?? '';
        $googleVerify = $seo['google_verification'] ?? '';
        $bingVerify = $seo['bing_verification'] ?? '';
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $defaultTitle)</title>
    <meta name="description" content="@yield('description', $defaultDescription)">
    <link rel="canonical" href="@yield('canonical', url('/'))">
    <meta name="robots" content="{{ $robots }}">
    <meta name="theme-color" content="#00796b">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="{{ $seo['site_name'] ?? $appName }}">
    <meta property="og:title" content="@yield('og_title', $defaultTitle)">
    <meta property="og:description" content="@yield('description', $defaultDescription)">
    <meta property="og:url" content="@yield('canonical', url('/'))">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $appName }} — software manajemen klinik gigi">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', $defaultTitle)">
    <meta name="twitter:description" content="@yield('description', $defaultDescription)">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <link rel="icon" href="{{ $appLogo }}" type="{{ $appLogoType }}">
    <link rel="apple-touch-icon" href="{{ $appLogo }}">
    @if ($googleVerify)
        <meta name="google-site-verification" content="{{ $googleVerify }}">
    @endif
    @if ($bingVerify)
        <meta name="msvalidate.01" content="{{ $bingVerify }}">
    @endif
    <link rel="preload" href="{{ asset('fonts/inter-latin-600.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/marketing.min.css') }}?v={{ filemtime(public_path('css/marketing.min.css')) }}">
    @stack('head')
    @if ($gtm)
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $gtm }}');</script>
    @elseif ($ga4)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $ga4 }}');</script>
    @endif
</head>
<body>
    @if ($gtm)
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    <a class="skip-link" href="#main">Langsung ke konten</a>
    @include('marketing.partials.header')
    <main id="main">
        @yield('content')
    </main>
    @include('marketing.partials.footer')
    <script src="{{ asset('js/marketing.js') }}?v=3" defer></script>
</body>
</html>
