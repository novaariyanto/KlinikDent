@extends('layouts.app')

@section('title', 'Tagihan Paket')

@section('content')
    <x-page-header title="Tagihan Paket" :breadcrumb="['Langganan' => route('subscription.index'), 'Tagihan' => route('subscription.invoices')]" />
    <x-alert />

    @if ($subscription)
        <div class="alert alert-info">
            Paket saat ini: <strong>{{ $subscription->package?->name ?? '-' }}</strong>
            · Status <span class="{{ $subscription->status->badgeClass() }}">{{ $subscription->status->label() }}</span>
            · Berlaku sampai {{ $subscription->ends_at?->format('d M Y') ?? '-' }}
        </div>
    @endif

    <x-card title="Tagihan langganan klinik">
        <x-table>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Paket</th>
                    <th class="text-end">Nominal</th>
                    <th>Status</th>
                    <th>Terbit</th>
                    <th>Jatuh tempo</th>
                    <th>Dibayar</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->number }}</td>
                        <td>{{ $invoice->subscription?->package?->name ?? '-' }}</td>
                        <td class="text-end">Rp {{ number_format((float) $invoice->amount, 0, ',', '.') }}</td>
                        <td><span class="{{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></td>
                        <td>{{ $invoice->issued_at?->format('d M Y') }}</td>
                        <td>{{ $invoice->due_at?->format('d M Y') }}</td>
                        <td>{{ $invoice->paid_at?->format('d M Y H:i') ?? '-' }}</td>
                        <td>
                            @if ($canPay && $invoice->status === \App\Enums\SaasInvoiceStatus::Unpaid)
                                <form method="POST" action="{{ route('subscription.invoices.pay', $invoice) }}" @if (! $midtransReady) onsubmit="return confirm('Konfirmasi pembayaran tagihan {{ $invoice->number }}?')" @endif>
                                    @csrf
                                    <x-button type="submit">{{ $midtransReady ? 'Bayar via Midtrans' : 'Bayar sekarang' }}</x-button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">Belum ada tagihan paket.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        {{ $invoices->links() }}
    </x-card>
@endsection
