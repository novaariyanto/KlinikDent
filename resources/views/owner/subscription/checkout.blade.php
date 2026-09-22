@extends('layouts.app')

@section('title', 'Bayar Paket')

@section('content')
    <x-page-header title="Bayar Paket" :breadcrumb="['Langganan' => route('subscription.index'), 'Tagihan' => route('subscription.invoices'), 'Bayar' => null]" />
    <x-alert />

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <x-card title="Pembayaran Midtrans">
                <p class="mb-1">Nomor tagihan: <strong>{{ $invoice->number }}</strong></p>
                <p class="mb-1">Paket: {{ $invoice->subscription?->package?->name ?? '-' }}</p>
                <p class="mb-3">Nominal: <strong>Rp {{ number_format((float) $invoice->amount, 0, ',', '.') }}</strong></p>
                <p class="text-muted">Jendela pembayaran Midtrans akan terbuka. Jangan tutup halaman ini sampai pembayaran selesai.</p>
                <x-button href="{{ route('subscription.invoices') }}" variant="secondary">Kembali ke tagihan</x-button>
            </x-card>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ $snapScriptUrl }}" data-client-key="{{ $clientKey }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof snap === 'undefined') {
                return;
            }

            snap.pay(@json($snapToken), {
                onSuccess: function () {
                    window.location = @json($finishUrl);
                },
                onPending: function () {
                    window.location = @json($invoicesUrl);
                },
                onError: function () {
                    window.location = @json($invoicesUrl);
                },
                onClose: function () {
                    window.location = @json($invoicesUrl);
                }
            });
        });
    </script>
@endpush
