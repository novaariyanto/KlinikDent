@extends('layouts.app')

@section('title', 'Transaksi Shift')

@section('content')
    <x-page-header title="Transaksi Shift" :breadcrumb="['Kasir' => route('cashier.shifts.transactions'), 'Transaksi' => null]" />
    <x-alert />
    @if (! $shift)
        <div class="alert alert-warning">Belum ada shift. <a href="{{ route('cashier.shifts.open') }}">Buka shift</a></div>
    @else
        <x-card title="{{ $shift->isOpen() ? 'Shift berjalan' : 'Shift terakhir' }}">
            <p class="mb-1"><strong>Status:</strong> <span class="{{ $shift->status->badgeClass() }}">{{ $shift->status->label() }}</span></p>
            <p class="mb-1"><strong>Dibuka:</strong> {{ $shift->opened_at?->format('d M Y H:i') }}</p>
            <p class="mb-1"><strong>Saldo awal:</strong> {{ number_format((float) $shift->opening_balance, 0, ',', '.') }}</p>
            <p class="mb-3"><strong>Tunai masuk:</strong> {{ number_format((float) $cashIn, 0, ',', '.') }}</p>
            @if ($shift->isOpen())
                <x-button href="{{ route('cashier.shifts.close') }}" variant="danger">Tutup Shift</x-button>
            @endif
        </x-card>
        <x-card title="Pembayaran pada shift">
            <x-table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Tagihan</th>
                        <th>Metode</th>
                        <th>Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ $payment->paid_at?->format('d M Y H:i') }}</td>
                            <td>{{ $payment->invoice?->number }} — {{ $payment->invoice?->patient?->name }}</td>
                            <td>{{ $payment->method->label() }}</td>
                            <td>{{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">Belum ada transaksi.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
            @if ($payments && $payments->hasPages())
                <div class="mt-3">{{ $payments->links() }}</div>
            @endif
        </x-card>
    @endif
@endsection
