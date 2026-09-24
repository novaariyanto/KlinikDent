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

            @if ($pharmacyPending->isNotEmpty() || $pharmacyWaiting->isNotEmpty())
                <x-card title="Farmasi">
                    @if ($pharmacyPending->isNotEmpty())
                        <p class="mb-2">Obat yang sudah diserahkan farmasi belum masuk tagihan ini.</p>
                        <x-table>
                            <thead>
                                <tr>
                                    <th>Obat</th>
                                    <th>Qty</th>
                                    <th>Harga</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pharmacyPending as $item)
                                    @php
                                        $price = (string) ($item->medicine?->sell_price ?? $item->medicine?->base_price ?? '0');
                                        $subtotal = (float) $price * (int) $item->quantity;
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ $item->medicine?->name ?? 'Obat' }}
                                            @if ($item->dosage || $item->frequency)
                                                <span class="text-muted">({{ collect([$item->dosage, $item->frequency])->filter()->implode(' / ') }})</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>{{ number_format((float) $price, 0, ',', '.') }}</td>
                                        <td>{{ number_format($subtotal, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table>
                        @if ($invoice->isOpen())
                            @can('create', App\Models\Invoice::class)
                                <form method="POST" action="{{ route('billing.invoices.generate') }}" class="mt-3">
                                    @csrf
                                    <input type="hidden" name="visit_id" value="{{ $invoice->visit_id }}">
                                    <x-button type="submit" variant="primary" icon="bx bx-plus">Tambahkan ke tagihan</x-button>
                                </form>
                            @endcan
                        @else
                            <p class="text-muted mb-0 mt-2">Tagihan ini sudah tertutup. Buat tagihan baru dari daftar kunjungan belum ditagih.</p>
                        @endif
                    @endif

                    @if ($pharmacyWaiting->isNotEmpty())
                        <p class="{{ $pharmacyPending->isNotEmpty() ? 'mt-3' : '' }} mb-0 text-muted">
                            Resep belum bisa ditagih karena farmasi belum menyerahkan:
                            {{ $pharmacyWaiting->map(fn ($prescription) => '#'.$prescription->id.' ('.$prescription->status->label().')')->implode(', ') }}.
                        </p>
                    @endif
                </x-card>
            @endif

            <x-card title="Pembayaran">
                @forelse ($invoice->payments as $payment)
                    <p class="mb-1">
                        {{ $payment->paid_at?->format('d M Y H:i') }} —
                        {{ $payment->isRefund() ? '−' : '' }}{{ number_format((float) $payment->amount, 0, ',', '.') }}
                        ({{ $payment->kind?->label() ?? 'Pembayaran' }} · {{ $payment->method->label() }})
                        — {{ $payment->cashier?->name }}
                        @if ($payment->notes) <span class="text-muted">{{ $payment->notes }}</span> @endif
                    </p>
                @empty
                    <p class="text-muted">Belum ada pembayaran.</p>
                @endforelse
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card title="Dokumen">
                <x-button href="{{ route('billing.invoices.print', $invoice) }}" variant="light" icon="bx bx-printer" target="_blank">Cetak</x-button>
            </x-card>

            @php
                $showPay = $invoice->isOpen() && auth()->user()?->can('create', App\Models\Payment::class);
                $showRefund = ! $invoice->isVoid() && (float) $invoice->paid_amount > 0 && auth()->user()?->can('create', App\Models\Payment::class);
                $openRefund = $showRefund && ($errors->has('reason') || filled(old('reason')));
            @endphp

            @if ($showPay && $showRefund)
                <ul class="nav nav-tabs nav-tabs-custom mb-3">
                    <li class="nav-item">
                        <button class="nav-link {{ $openRefund ? '' : 'active' }}" type="button" data-bs-toggle="tab" data-bs-target="#invoice-pay">Pembayaran</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link {{ $openRefund ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#invoice-refund">Pengembalian</button>
                    </li>
                </ul>
            @endif

            <div @class(['tab-content' => $showPay && $showRefund])>
            @if ($showPay)
                <div @class(['tab-pane fade' => $showPay && $showRefund, 'show active' => $showPay && $showRefund && ! $openRefund]) id="invoice-pay">
                <x-card title="Terima pembayaran">
                    <form method="POST" action="{{ route('billing.invoices.pay', $invoice) }}">
                        @csrf
                        <x-input name="amount" type="number" label="Nominal" :value="old('amount', $invoice->remainingAmount())" required />
                        <x-select name="method" label="Metode" :options="$methods" :selected="old('method', 'cash')" required />
                        <x-input name="notes" label="Catatan" :value="old('notes')" />
                        <x-button type="submit" variant="success" icon="bx bx-money">Bayar</x-button>
                    </form>
                </x-card>
                </div>
            @endif

            @if ($showRefund)
                <div @class(['tab-pane fade' => $showPay && $showRefund, 'show active' => $openRefund]) id="invoice-refund">
                <x-card title="Pengembalian dana">
                    <form method="POST" action="{{ route('billing.invoices.refund', $invoice) }}">
                        @csrf
                        <x-input name="amount" id="refund_amount" type="number" label="Nominal" :value="old('amount')" required />
                        <x-select name="method" id="refund_method" label="Metode" :options="$methods" :selected="old('method', 'cash')" required />
                        <x-input name="reason" label="Alasan" :value="old('reason')" required />
                        <x-button type="submit" variant="warning">Kembalikan</x-button>
                    </form>
                </x-card>
                </div>
            @endif
            </div>

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
