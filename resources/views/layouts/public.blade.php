<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Klinik')</title>
    <link href="{{ theme('css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f4f7fb; }
        .public-wrap { max-width: 720px; margin: 32px auto; }
        .card { border: 0; box-shadow: 0 8px 24px rgba(15, 23, 42, .06); }
    </style>
</head>
<body>
    <div class="public-wrap">
        @yield('content')
    </div>
</body>
</html>
