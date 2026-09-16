<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $appName ?? config('app.name')) | {{ $appName ?? config('app.name') }}</title>
    <link rel="shortcut icon" href="{{ theme('images/favicon.ico') }}">
    <link href="{{ theme('css/bootstrap.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ theme('css/icons.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ theme('css/app.min.css') }}" rel="stylesheet" type="text/css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body @yield('body_attributes')>
    @yield('layout')
    @include('layouts.scripts')
    @stack('scripts')
</body>
</html>
