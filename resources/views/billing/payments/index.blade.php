@extends('layouts.app')

@section('title', 'Pembayaran')

@section('content')
    <x-page-header title="Pembayaran" :breadcrumb="['Billing' => route('billing.payments'), 'Pembayaran' => null]" />
    <x-alert />
    @if (auth()->user()?->hasRole(\App\Enums\RoleName::Cashier) && ! $shift)
        <div class="alert alert-warning">Shift belum dibuka. <a href="{{ route('cashier.shifts.open') }}">Buka shift</a></div>
    @endif
    <x-card title="Riwayat pembayaran">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label" for="date_from">Dari</label>
                <input id="date_from" type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="date_to">Sampai</label>
                <input id="date_to" type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <x-button type="submit" icon="bx bx-filter-alt">Filter</x-button>
            </div>
        </form>
        <x-table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Tagihan</th>
                    <th>Pasien</th>
                    <th>Metode</th>
                    <th>Nominal</th>
                    <th>Kasir</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td>{{ $payment->paid_at?->format('d M Y H:i') }}</td>
                        <td>
                            <a href="{{ route('billing.invoices.show', $payment->invoice) }}">{{ $payment->invoice?->number }}</a>
                        </td>
                        <td>{{ $payment->invoice?->patient?->name }}</td>
                        <td>{{ $payment->method->label() }}</td>
                        <td>{{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                        <td>{{ $payment->cashier?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Belum ada pembayaran.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($payments->hasPages())
            <div class="mt-3">{{ $payments->links() }}</div>
        @endif
    </x-card>
@endsection
