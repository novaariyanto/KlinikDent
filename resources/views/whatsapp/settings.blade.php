@extends('layouts.app')

@section('title', 'Pengaturan WhatsApp')

@section('content')
    <x-page-header title="Pengaturan WhatsApp" :breadcrumb="['WhatsApp Otomatis' => route('whatsapp.dashboard'), 'Pengaturan' => null]" />
    <x-alert />
    @include('whatsapp.partials.nav')

    <div class="row">
        <div class="col-xl-8">
            <x-card title="Kredensial WhatsCRM">
                <form method="POST" action="{{ route('whatsapp.settings.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled" @checked(old('enabled', $setting->enabled)) @disabled(! $canManage)>
                        <label class="form-check-label" for="enabled">Aktifkan WhatsApp Otomatis</label>
                    </div>

                    <x-input name="api_base_url" label="API Base URL" :value="old('api_base_url', $setting->api_base_url ?: config('services.whatsapp.base_url'))" :disabled="! $canManage" />
                    <x-input name="api_key" type="password" label="API Key" :value="''" autocomplete="new-password" :disabled="! $canManage" />
                    @if ($setting->api_key)
                        <p class="text-muted small">API key tersimpan: <code>{{ $setting->maskedApiKey() }}</code>. Isi ulang hanya jika ingin mengganti.</p>
                    @endif
                    <x-input name="phone_number" label="Nomor bisnis" :value="old('phone_number', $setting->phone_number)" :disabled="! $canManage" />
                    <x-input name="business_name" label="Nama bisnis" :value="old('business_name', $setting->business_name)" :disabled="! $canManage" />
                    <x-select
                        name="default_language"
                        label="Bahasa default"
                        :options="['id' => 'Indonesia', 'en' => 'English']"
                        :selected="old('default_language', $setting->default_language)"
                        :placeholder="null"
                        :disabled="! $canManage"
                    />

                    @if ($canManage)
                        <x-button type="submit" icon="bx bx-save">Simpan</x-button>
                    @endif
                </form>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card title="Status">
                @if ($setting->isReady())
                    <span class="badge bg-success">● Terhubung</span>
                @else
                    <span class="badge bg-secondary">○ Belum terhubung</span>
                @endif
                <p class="text-muted small mt-3 mb-0">API key disimpan terenkripsi dan tidak ditampilkan penuh.</p>
                <div class="mt-3">
                    <x-button href="{{ route('whatsapp.balance') }}" variant="secondary" icon="bx bx-wallet">Lihat saldo &amp; isi ulang</x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
