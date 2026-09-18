@extends('layouts.app')

@section('title', 'Billing')

@section('content')
    <x-page-header title="Billing & Kasir" :breadcrumb="['Billing' => route('billing.index'), 'Ringkasan' => null]" />
    <x-alert />
    @if ($shift)
        <div class="alert alert-success">Shift terbuka sejak {{ $shift->opened_at?->format('d M Y H:i') }} — saldo awal {{ number_format((float) $shift->opening_balance, 0, ',', '.') }}</div>
    @endif
    <div class="row">
        <div class="col-md-4">
            <x-card title="Tagihan terbuka">
                <h3 class="mb-2">{{ $openInvoices }}</h3>
                <a href="{{ route('billing.invoices') }}">Lihat tagihan</a>
            </x-card>
        </div>
        <div class="col-md-4">
            <x-card title="Tagihan hari ini">
                <h3 class="mb-2">{{ $todayInvoices }}</h3>
                <a href="{{ route('billing.invoices.today') }}">Tagihan hari ini</a>
            </x-card>
        </div>
        <div class="col-md-4">
            <x-card title="Pembayaran hari ini">
                <h3 class="mb-2">{{ number_format((float) $todayPaid, 0, ',', '.') }}</h3>
                <a href="{{ route('billing.payments') }}">Lihat pembayaran</a>
            </x-card>
        </div>
    </div>
@endsection
