@extends('layouts.app')

@section('title', 'Saldo WhatsApp')

@section('content')
    <x-page-header title="Saldo WhatsApp" :breadcrumb="['WhatsApp Otomatis' => route('whatsapp.dashboard'), 'Saldo' => null]" />
    <x-alert />
    @include('whatsapp.partials.nav')

    @if ($error && ! $account)
        <div class="alert alert-warning">Saldo WhatsCRM tidak bisa diambil: {{ $error }}</div>
    @elseif (! $setting->isReady())
        <div class="alert alert-info">
            Hubungkan API di <a href="{{ route('whatsapp.settings') }}">Pengaturan WhatsApp</a> untuk melihat saldo dan isi ulang.
        </div>
    @endif

    <div class="row">
        <div class="col-xl-4">
            <x-card title="Saldo akun">
                @if ($account)
                    <h3 class="mb-1">{{ $account['formatted_balance'] }}</h3>
                    <p class="text-muted small mb-3">{{ $account['currency'] }} · diperbarui dari WhatsCRM</p>
                    @if ($account['has_usage'])
                        <div class="d-flex justify-content-between py-2 border-top">
                            <span class="text-muted">Pesan hari ini</span>
                            <strong>{{ number_format($account['messages_today'], 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-top">
                            <span class="text-muted">Pesan bulan ini</span>
                            <strong>{{ number_format($account['messages_this_month'], 0, ',', '.') }}</strong>
                        </div>
                    @endif
                @else
                    <p class="text-muted mb-0">Saldo belum tersedia.</p>
                @endif
            </x-card>

            @if ($topup)
                @php
                    $status = strtoupper((string) ($topup['status'] ?? ''));
                    $badge = match (true) {
                        in_array($status, ['PAID', 'SUCCESS', 'SETTLEMENT', 'COMPLETED'], true) => 'bg-success',
                        in_array($status, ['EXPIRED', 'FAILED', 'CANCELLED', 'CANCELED'], true) => 'bg-danger',
                        default => 'bg-warning',
                    };
                @endphp
                <x-card title="Pembayaran terakhir">
                    <p class="mb-1">Order: <code>{{ $topup['order_id'] }}</code></p>
                    <p class="mb-1">Status: <span class="badge {{ $badge }}">{{ $topup['status'] }}</span></p>
                    @if ($topup['gateway_display_name'])
                        <p class="mb-1">Gateway: {{ $topup['gateway_display_name'] }}</p>
                    @endif
                    @if ($topup['payment_channel'])
                        <p class="mb-1">Channel: <code>{{ $topup['payment_channel'] }}</code></p>
                    @endif
                    <p class="mb-1">Nominal: {{ $topup['formatted_amount'] }}</p>
                    <p class="mb-1">Admin: Rp {{ number_format((int) $topup['admin_fee'], 0, ',', '.') }}</p>
                    <p class="mb-3">Total: <strong>{{ $topup['formatted_total_amount'] }}</strong></p>
                    @if ($topup['expired_at'])
                        <p class="text-muted small">Berlaku sampai: {{ $topup['expired_at'] }}</p>
                    @endif
                    @if ($topup['payment_url'] && ! in_array($status, ['PAID', 'SUCCESS', 'SETTLEMENT', 'COMPLETED', 'EXPIRED', 'FAILED', 'CANCELLED', 'CANCELED'], true))
                        <a href="{{ $topup['payment_url'] }}" class="btn btn-primary mb-2" target="_blank" rel="noopener">Buka pembayaran</a>
                    @endif
                    <form method="POST" action="{{ route('whatsapp.balance.check') }}">
                        @csrf
                        <input type="hidden" name="order_id" value="{{ $topup['order_id'] }}">
                        <x-button type="submit" variant="secondary" icon="bx bx-sync">Cek status</x-button>
                    </form>
                    <p class="text-muted small mt-3 mb-0">Saldo bertambah otomatis setelah pembayaran sukses (via callback gateway).</p>
                </x-card>
            @endif
        </div>

        <div class="col-xl-8">
            <x-card title="Isi saldo">
                @if (! $canManage)
                    <p class="text-muted mb-0">Hanya pengguna dengan akses pengaturan WhatsApp yang dapat membuat link topup.</p>
                @elseif (! $setting->isReady())
                    <p class="text-muted mb-0">Lengkapi API key terlebih dahulu.</p>
                @else
                    @php
                        $gatewayName = collect($channels)->pluck('gateway_display_name')->first(fn ($name) => filled($name));
                    @endphp
                    <p class="text-muted">Link pembayaran memakai gateway aktif di WhatsCRM{{ $gatewayName ? ' ('.$gatewayName.')' : ' (Midtrans atau Pakar Digital)' }}. Minimal topup Rp 1.000.</p>
                    <form method="POST" action="{{ route('whatsapp.balance.topup') }}" id="whatsapp-topup-form">
                        @csrf
                        <div class="row">
                            <div class="col-md-4">
                                <x-input name="amount" type="number" label="Nominal" :value="old('amount', 50000)" min="1000" step="1000" required />
                            </div>
                            <div class="col-md-4">
                                @php
                                    $channelOptions = collect($channels)->mapWithKeys(fn ($channel) => [$channel['code'] => $channel['name']])->all();
                                @endphp
                                <x-select
                                    name="payment_channel"
                                    label="Channel"
                                    :options="$channelOptions"
                                    :selected="old('payment_channel')"
                                    placeholder="Default gateway"
                                />
                            </div>
                            <div class="col-md-4">
                                <x-input name="phone" label="Nomor HP" :value="old('phone', $defaultPhone)" placeholder="081234567890" />
                            </div>
                        </div>
                        <p class="text-muted small" id="phone-hint">Nomor HP wajib untuk e-wallet (DANA, OVO, ShopeePay, QRIS).</p>
                        <x-button type="submit" icon="bx bx-wallet">Buat link pembayaran</x-button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const channels = @json(collect($channels)->mapWithKeys(fn ($channel) => [$channel['code'] => $channel['requires_phone']]));
        const select = document.getElementById('payment_channel');
        const phone = document.getElementById('phone');
        const hint = document.getElementById('phone-hint');
        if (! select || ! phone) {
            return;
        }
        const sync = function () {
            const required = channels[select.value] !== undefined ? Boolean(channels[select.value]) : true;
            phone.required = required;
            if (hint) {
                hint.classList.toggle('d-none', ! required);
            }
        };
        select.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
