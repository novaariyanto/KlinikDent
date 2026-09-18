@extends('layouts.app')

@section('title', $invoice->number)

@section('content')
    <x-page-header :title="$invoice->number" :breadcrumb="['Tagihan' => route('billing.invoices'), $invoice->number => null]" />
    <x-alert />

    @if (auth()->user()?->hasRole(\App\Enums\RoleName::Cashier) && ! $shift && $invoice->isOpen())
        <div class="alert alert-warning">Buka shift kasir sebelum menerima pembayaran. <a href="{{ route('cashier.shifts.open') }}">Buka shift</a></div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Item">
                <p class="mb-1"><strong>Pasien:</strong> {{ $invoice->patient?->name }}</p>
                <p class="mb-1"><strong>Penjamin:</strong> {{ $invoice->payer?->name ?: '-' }}</p>
                <p class="mb-1"><strong>Cabang:</strong> {{ $invoice->branch?->name }}</p>
                <p class="mb-3">
                    <strong>Status:</strong>
                    <span class="{{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span>
                </p>
                <x-table>
                    <thead>
                        <tr>
                            <th>Jenis</th>
                            <th>Deskripsi</th>
                            <th>Qty</th>
                            <th>Harga</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td>{{ $item->source_type->label() }}</td>
                                <td>{{ $item->description }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                <td>{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Total</th>
                            <th>{{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-end">Dibayar</th>
                            <th>{{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-end">Sisa</th>
                            <th>{{ number_format((float) $invoice->remainingAmount(), 0, ',', '.') }}</th>
                        </tr>
                    </tfoot>
                </x-table>
            </x-card>

            <x-card title="Pembayaran">
                @forelse ($invoice->payments as $payment)
                    <p class="mb-1">
                        {{ $payment->paid_at?->format('d M Y H:i') }} —
                        {{ number_format((float) $payment->amount, 0, ',', '.') }}
                        ({{ $payment->method->label() }})
                        — {{ $payment->cashier?->name }}
                    </p>
                @empty
                    <p class="text-muted">Belum ada pembayaran.</p>
                @endforelse
            </x-card>
        </div>
        <div class="col-lg-4">
            @if ($invoice->isOpen() && auth()->user()?->can('create', App\Models\Payment::class))
                <x-card title="Terima pembayaran">
                    <form method="POST" action="{{ route('billing.invoices.pay', $invoice) }}">
                        @csrf
                        <x-input name="amount" type="number" label="Nominal" :value="old('amount', $invoice->remainingAmount())" required />
                        <x-select name="method" label="Metode" :options="$methods" :selected="old('method', 'cash')" required />
                        <x-input name="notes" label="Catatan" :value="old('notes')" />
                        <x-button type="submit" variant="success" icon="bx bx-money">Bayar</x-button>
                    </form>
                </x-card>
            @endif

            @can('void', $invoice)
                <x-card title="Batalkan tagihan">
                    <form method="POST" action="{{ route('billing.invoices.void', $invoice) }}" onsubmit="return confirm('Void tagihan ini?')">
                        @csrf
                        <x-input name="reason" label="Alasan" :value="old('reason')" required />
                        <x-button type="submit" variant="danger">Void</x-button>
                    </form>
                </x-card>
            @endcan

            @if ($invoice->isVoid())
                <x-card title="Void">
                    <p class="mb-0">{{ $invoice->void_reason }}</p>
                    <p class="text-muted mb-0">{{ $invoice->voided_at?->format('d M Y H:i') }}</p>
                </x-card>
            @endif
        </div>
    </div>
@endsection
