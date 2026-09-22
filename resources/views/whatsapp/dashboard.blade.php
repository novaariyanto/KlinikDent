@extends('layouts.app')

@section('title', 'WhatsApp Otomatis')

@section('content')
    <x-page-header title="WhatsApp Otomatis" :breadcrumb="['WhatsApp Otomatis' => null, 'Dashboard' => route('whatsapp.dashboard')]" />
    <x-alert />
    @include('whatsapp.partials.nav')

    <div class="row">
        <div class="col-xl-4">
            <x-card title="Status">
                @if ($setting->isReady())
                    <p class="mb-0"><span class="badge bg-success">● Terhubung</span></p>
                    <p class="text-muted small mt-2 mb-0">{{ $setting->business_name ?: 'WhatsCRM' }} siap mengirim pesan.</p>
                    @if ($account)
                        <p class="mt-3 mb-1">Saldo: <strong>{{ $account['formatted_balance'] }}</strong></p>
                        @if ($account['has_usage'])
                            <p class="text-muted small mb-2">Akun: {{ number_format($account['messages_today'], 0, ',', '.') }} pesan hari ini · {{ number_format($account['messages_this_month'], 0, ',', '.') }} bulan ini</p>
                        @endif
                        <x-button href="{{ route('whatsapp.balance') }}" variant="secondary" icon="bx bx-wallet">Isi saldo</x-button>
                    @elseif ($accountError)
                        <p class="text-warning small mt-3 mb-0">Saldo tidak bisa diambil: {{ $accountError }}</p>
                    @endif
                @elseif ($setting->enabled)
                    <p class="mb-0"><span class="badge bg-warning">● Belum lengkap</span></p>
                    <p class="text-muted small mt-2 mb-0">Aktif, tetapi API key belum diisi.</p>
                @else
                    <p class="mb-0"><span class="badge bg-secondary">○ Tidak terhubung</span></p>
                    <p class="text-muted small mt-2 mb-0">Hubungkan API di Pengaturan WhatsApp.</p>
                @endif
            </x-card>
        </div>
        <div class="col-xl-8">
            <div class="row">
                <div class="col-md-3">
                    <x-card><p class="text-muted mb-1">Pesan hari ini</p><h4 class="mb-0">{{ $todayTotal }}</h4></x-card>
                </div>
                <div class="col-md-3">
                    <x-card><p class="text-muted mb-1">Berhasil</p><h4 class="mb-0 text-success">{{ $todaySent }}</h4></x-card>
                </div>
                <div class="col-md-3">
                    <x-card><p class="text-muted mb-1">Gagal</p><h4 class="mb-0 text-danger">{{ $todayFailed }}</h4></x-card>
                </div>
                <div class="col-md-3">
                    <x-card>
                        <p class="text-muted mb-1">Automation aktif</p>
                        <h4 class="mb-0">{{ $activeRules }}</h4>
                        <p class="text-muted small mb-0 mt-1">Template aktif: {{ $activeTemplates }}</p>
                    </x-card>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7">
            <x-card title="Ringkasan automation">
                <ul class="list-unstyled mb-0">
                    @foreach ($groups as $label => $active)
                        <li class="d-flex justify-content-between py-2 border-bottom">
                            <span>{{ $label }}</span>
                            @if ($active)
                                <span class="text-success">✓ Aktif</span>
                            @else
                                <span class="text-muted">○ Tidak aktif</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>
        <div class="col-xl-5">
            <x-card title="Rekomendasi Otomatis">
                <p class="text-muted">Untuk klinik kecil, aktifkan preset pengingat tanpa mengatur satu per satu.</p>
                <ul class="text-muted">
                    <li>Pengingat jadwal H-1</li>
                    <li>Pengingat jadwal hari-H</li>
                    <li>Konfirmasi appointment</li>
                    <li>Perubahan &amp; pembatalan jadwal</li>
                    <li>Pengingat kontrol</li>
                </ul>
                @if ($canManage)
                    <form method="POST" action="{{ route('whatsapp.reminders.preset') }}">
                        @csrf
                        <x-button type="submit" icon="bx bx-bolt-circle">Aktifkan Pengingat Otomatis</x-button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
@endsection
