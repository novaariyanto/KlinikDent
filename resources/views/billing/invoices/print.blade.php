@extends('clinical.care.print.layout')

@section('content')
    <style>
        table.items { width: 100%; border: 0.8px solid #222; margin-top: 4px; }
        table.items th, table.items td { border: 0.5px solid #999; padding: 5px 7px; vertical-align: top; }
        table.items th { background: #f3f3f3; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
        table.items td.num, table.items th.num { text-align: right; white-space: nowrap; }
        table.totals { width: 240px; margin-left: auto; margin-top: 8px; }
        table.totals td { padding: 3px 0; font-size: 11px; }
        table.totals td.num { text-align: right; font-weight: bold; }
        .void-note { margin: 0 0 10px; padding: 6px 8px; border: 0.8px solid #222; font-size: 11px; }
    </style>

    <table style="width: 100%; margin-bottom: 12px;">
        <tr>
            <td style="text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; text-decoration: underline; padding-bottom: 4px;">
                Tagihan
            </td>
        </tr>
        <tr>
            <td style="text-align: center; font-size: 10.5px; padding-bottom: 8px;">
                Nomor: {{ $invoice->number }}
            </td>
        </tr>
    </table>

    @if ($invoice->isVoid())
        <p class="void-note"><strong>Dibatalkan.</strong> {{ $invoice->void_reason }}</p>
    @endif

    <div class="section">
        <table class="section-head"><tr><td>Identitas</td></tr></table>
        <table class="facts">
            <tr>
                <th>Nama pasien</th>
                <td>{{ $invoice->patient?->name ?: '-' }}</td>
            </tr>
            <tr>
                <th>Nomor rekam medis</th>
                <td>{{ $invoice->patient?->medical_record_number ?: '-' }}</td>
            </tr>
            <tr>
                <th>Penjamin</th>
                <td>{{ $invoice->payer?->name ?: '-' }}</td>
            </tr>
            <tr>
                <th>Cabang</th>
                <td>{{ $invoice->branch?->name ?: '-' }}</td>
            </tr>
            <tr>
                <th>Tanggal tagihan</th>
                <td>{{ $invoiceDateLabel }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ $invoice->status->label() }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="section-head"><tr><td>Rincian</td></tr></table>
        <table class="items">
            <thead>
                <tr>
                    <th>Jenis</th>
                    <th>Deskripsi</th>
                    <th class="num">Qty</th>
                    <th class="num">Harga</th>
                    <th class="num">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->source_type->label() }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Belum ada item.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <table class="totals">
            <tr>
                <td>Total</td>
                <td class="num">Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Dibayar</td>
                <td class="num">Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Sisa</td>
                <td class="num">Rp {{ number_format((float) $invoice->remainingAmount(), 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    @if ($invoice->payments->isNotEmpty())
        <div class="section">
            <table class="section-head"><tr><td>Pembayaran</td></tr></table>
            <table class="items">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Metode</th>
                        <th>Kasir</th>
                        <th class="num">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->payments as $payment)
                        <tr>
                            <td>{{ $payment->paid_at?->format('d-m-Y H:i') }}</td>
                            <td>{{ $payment->method->label() }}</td>
                            <td>{{ $payment->cashier?->name ?: '-' }}</td>
                            <td class="num">{{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

@section('signature')
    <table class="sign">
        <tr>
            <td></td>
            <td>
                <div class="block">
                    {{ $printCity ?: $branch?->name }}, {{ $printedDateLabel ?? $printedAt->format('d-m-Y') }}<br>
                    Kasir
                    <div class="space"></div>
                    <div class="name">{{ $cashierName ?: '........................' }}</div>
                    <div class="role">Petugas kasir</div>
                </div>
            </td>
        </tr>
    </table>
@endsection
