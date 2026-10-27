<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk {{ $invoice->number }}</title>
    <style>
        @page { size: {{ $paper }}mm auto; margin: 2mm; }
        body { width: {{ $paper === '58' ? '48mm' : '72mm' }}; margin: 0 auto; font-family: "Courier New", monospace; font-size: 12px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 4px; text-align: center; }
        p { margin: 0 0 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        td { vertical-align: top; padding: 2px 0; }
        .right { text-align: right; }
        .line { border-top: 1px dashed #111; margin: 6px 0; }
        .muted { color: #444; }
        .actions { margin-top: 12px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <h1>{{ $clinicName }}</h1>
    <p style="text-align:center">{{ $invoice->branch?->name }}</p>
    <div class="line"></div>
    <p>{{ $invoice->number }}</p>
    <p>{{ $invoice->created_at?->format('d/m/Y H:i') }}</p>
    <p>{{ $invoice->patient?->name }}</p>
    <p>RM {{ $invoice->patient?->medical_record_number ?: '-' }}</p>
    <p>Dokter {{ $invoice->visit?->doctor?->name ?: '-' }}</p>
    <p>Status {{ $invoice->status->label() }}</p>
    <div class="line"></div>
    <table>
        @foreach ($invoice->items as $item)
            @continue((int) $item->quantity < 1)
            <tr>
                <td colspan="2">{{ $item->description }}</td>
            </tr>
            <tr>
                <td>{{ $item->quantity }} x {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                <td class="right">{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>
    @if ($invoice->items->contains(fn ($item) => (int) $item->quantity < 1))
        <div class="line"></div>
        <p class="muted">Tidak diambil</p>
        @foreach ($invoice->items as $item)
            @continue((int) $item->quantity > 0)
            <p class="muted">{{ $item->description }}</p>
        @endforeach
    @endif
    <div class="line"></div>
    <table>
        <tr><td>Total</td><td class="right">{{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td></tr>
        <tr><td>Dibayar</td><td class="right">{{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}</td></tr>
        <tr><td>Sisa</td><td class="right">{{ number_format((float) $invoice->remainingAmount(), 0, ',', '.') }}</td></tr>
    </table>
    <div class="line"></div>
    @forelse ($invoice->payments as $payment)
        <p>{{ $payment->paid_at?->format('d/m/Y H:i') }} {{ $payment->isRefund() ? 'Kembali' : 'Bayar' }} {{ number_format((float) $payment->amount, 0, ',', '.') }} {{ $payment->method->label() }}</p>
        @if ($payment->notes)<p class="muted">{{ $payment->notes }}</p>@endif
    @empty
        <p class="muted">Belum ada pembayaran.</p>
    @endforelse
    <div class="line"></div>
    <p style="text-align:center">Terima kasih</p>
    <div class="actions">
        <button type="button" onclick="window.print()">Cetak</button>
    </div>
    @if ($autoprint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
