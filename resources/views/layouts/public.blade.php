<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Klinik')</title>
    <link href="{{ theme('css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        @font-face { font-family: Inter; font-style: normal; font-weight: 400; font-display: swap; src: url('/fonts/inter-latin-400.woff2') format('woff2'); }
        @font-face { font-family: Inter; font-style: normal; font-weight: 600; font-display: swap; src: url('/fonts/inter-latin-600.woff2') format('woff2'); }
        @font-face { font-family: Inter; font-style: normal; font-weight: 700; font-display: swap; src: url('/fonts/inter-latin-700.woff2') format('woff2'); }
        @font-face { font-family: Inter; font-style: normal; font-weight: 800; font-display: swap; src: url('/fonts/inter-latin-800.woff2') format('woff2'); }
        :root {
            --booking-ink: #455a64;
            --booking-muted: #757575;
            --booking-line: #bdbdbd;
            --booking-accent: #00796b;
            --booking-accent-hover: #00695c;
            --booking-accent-soft: #e0f2f1;
            --booking-surface: #ffffff;
            --booking-page: #f5f5f5;
        }
        body {
            background: var(--booking-page);
            color: var(--booking-ink);
            font-family: Inter, "Segoe UI", system-ui, -apple-system, sans-serif;
        }
        .public-wrap {
            max-width: 880px;
            margin: 28px auto 48px;
            padding: 0 16px;
        }
        .booking-hero {
            margin-bottom: 18px;
        }
        .booking-hero__eyebrow {
            color: var(--booking-accent);
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            margin-bottom: .35rem;
        }
        .booking-hero h1 {
            font-size: 1.65rem;
            font-weight: 700;
            margin-bottom: .35rem;
        }
        .booking-hero p {
            color: var(--booking-muted);
            margin-bottom: 0;
        }
        .booking-card {
            background: var(--booking-surface);
            border: 1px solid var(--booking-line);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .05);
            overflow: hidden;
        }
        .booking-section {
            padding: 1.25rem 1.35rem;
            border-bottom: 1px solid var(--booking-line);
        }
        .booking-section:last-child {
            border-bottom: 0;
        }
        .booking-section__title {
            font-size: .95rem;
            font-weight: 700;
            margin-bottom: .15rem;
        }
        .booking-section__hint {
            color: var(--booking-muted);
            font-size: .85rem;
            margin-bottom: 1rem;
        }
        .form-label {
            font-size: .85rem;
            font-weight: 600;
            color: #334155;
        }
        .form-control, .form-select {
            border-radius: .65rem;
            border-color: #cbd5e1;
            min-height: 42px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--booking-accent);
            box-shadow: 0 0 0 .2rem rgba(12, 111, 104, .18);
        }
        .btn-booking {
            background: var(--booking-accent);
            border-color: var(--booking-accent);
            color: #fff;
            font-weight: 600;
            border-radius: .75rem;
            padding: .7rem 1.25rem;
            min-width: 160px;
        }
        .btn-booking:hover, .btn-booking:focus {
            background: var(--booking-accent-hover);
            border-color: var(--booking-accent-hover);
            color: #fff;
        }
        .booking-result-number {
            font-size: 2.75rem;
            font-weight: 800;
            letter-spacing: .04em;
            color: var(--booking-accent);
            line-height: 1.1;
        }
        .booking-result-badge {
            display: inline-block;
            background: var(--booking-accent-soft);
            color: var(--booking-accent);
            border-radius: 999px;
            padding: .35rem .85rem;
            font-size: .8rem;
            font-weight: 700;
            margin-bottom: .75rem;
        }
        .booking-result-meta {
            text-align: start;
            background: #f8fafc;
            border: 1px solid var(--booking-line);
            border-radius: 12px;
            padding: 1rem;
        }
        .booking-result-meta dt {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--booking-muted);
            font-weight: 700;
        }
        .booking-result-meta dd {
            margin-bottom: .65rem;
            font-weight: 600;
        }
        .booking-result-meta dd:last-child {
            margin-bottom: 0;
        }
        .modal-content {
            border: 0;
            border-radius: 18px;
        }
        .modal-header, .modal-footer {
            border: 0;
        }
    </style>
    <style id="clinic-theme">{!! \App\Support\ClinicTheme::style() !!}</style>
    @stack('styles')
</head>
<body>
    <div class="public-wrap">
        @yield('content')
    </div>
    <script src="{{ theme('libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
