@extends('layouts.app')

@section('title', 'Payment Gateway')

@section('content')
    <x-page-header title="Payment Gateway" :breadcrumb="['Settings' => null, 'Payment Gateway' => route('saas.payment-gateway.index')]" />
    <x-alert />

    <div class="row">
        <div class="col-xl-8">
            <x-card title="Midtrans">
                <p class="text-muted">Konfigurasi ini dipakai owner klinik saat membayar paket langganan. Kredensial disimpan di database platform, bukan per klinik.</p>

                <form method="POST" action="{{ route('saas.payment-gateway.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled" @checked(old('enabled', $settings['enabled']))>
                        <label class="form-check-label" for="enabled">Aktifkan pembayaran Midtrans</label>
                    </div>

                    <x-select
                        name="environment"
                        label="Environment"
                        :options="['sandbox' => 'Sandbox (testing)', 'production' => 'Production']"
                        :selected="old('environment', $settings['environment'])"
                        required
                        :placeholder="null"
                    />

                    <x-input name="merchant_id" label="Merchant ID" :value="old('merchant_id', $settings['merchant_id'])" />
                    <x-input name="client_key" label="Client Key" :value="old('client_key', $settings['client_key'])" />
                    <x-input name="server_key" type="password" label="Server Key" :value="''" autocomplete="new-password" />
                    @if ($settings['has_server_key'])
                        <p class="text-muted small">Server Key sudah tersimpan. Isi ulang hanya jika ingin mengganti.</p>
                    @endif

                    @if ($canManage)
                        <x-button type="submit" icon="bx bx-save">Simpan</x-button>
                    @endif
                </form>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card title="URL callback">
                <p class="text-muted small">Salin URL ini ke dashboard Midtrans (Settings → Configuration).</p>
                <p class="mb-1 fw-semibold">Payment Notification</p>
                <code class="d-block mb-3">{{ $notificationUrl }}</code>
                <p class="mb-1 fw-semibold">Finish Redirect</p>
                <code class="d-block">{{ $finishUrl }}</code>
            </x-card>
            <x-card title="Status">
                @if ($settings['enabled'] && $settings['has_server_key'] && $settings['client_key'] !== '')
                    <span class="badge bg-success">Siap menerima pembayaran</span>
                    <p class="text-muted small mt-2 mb-0">Owner akan diarahkan ke Snap Midtrans saat membayar tagihan paket.</p>
                @else
                    <span class="badge bg-secondary">Belum aktif</span>
                    <p class="text-muted small mt-2 mb-0">Selama Midtrans nonaktif, owner masih bisa menandai tagihan lunas secara manual.</p>
                @endif
            </x-card>
        </div>
    </div>
@endsection
