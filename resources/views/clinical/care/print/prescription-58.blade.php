<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: 58mm auto; margin: 2mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #111; }
        body {
            width: 54mm;
            font-family: "Segoe UI", Tahoma, sans-serif;
            font-size: 11px;
            line-height: 1.3;
        }
        h1, p { margin: 0; }
        .center { text-align: center; }
        .name { font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .muted { font-size: 10px; }
        .rule { border-top: 1px dashed #111; margin: 4px 0; }
        .title { font-weight: 700; letter-spacing: 0.4px; margin: 2px 0; }
        .item { margin: 0 0 6px; }
        .item strong { font-weight: 700; }
        .sub { padding-left: 8px; font-size: 10px; }
        .screen-bar { margin: 8px 0 0; }
        @media print {
            .screen-bar { display: none; }
        }
    </style>
</head>
<body>
    @php
        $unitOptions = \App\Models\Medicine::UNITS + ['ml' => 'ml', 'bungkus' => 'Bungkus'];
    @endphp
    <div class="center">
        <h1 class="name">{{ $clinicName }}</h1>
        @if (!empty($clinicAddressLines))
            <p class="muted">{{ implode(', ', $clinicAddressLines) }}</p>
        @endif
        @if ($clinicPhone)
            <p class="muted">Telp {{ $clinicPhone }}</p>
        @endif
        <p class="title">RESEP OBAT</p>
    </div>
    <div class="rule"></div>
    <p>No. RM: {{ $patient?->medical_record_number ?: '-' }}</p>
    <p>{{ $patient?->name ?: '-' }}</p>
    <p>{{ $visitDateLabel }}</p>
    <p>Dokter: {{ $visit->doctor?->name ?: ($prescriptions->first()?->doctor?->name ?: '-') }}</p>
    <div class="rule"></div>
    @php $n = 0; @endphp
    @foreach ($prescriptions as $prescription)
        @foreach ($prescription->items as $item)
            @php
                $n++;
                $unit = $item->unit ? ($unitOptions[$item->unit] ?? $item->unit) : ($item->medicine?->unitLabel() ?: '');
            @endphp
            <div class="item">
                <p><strong>{{ $n }}. {{ $item->label() }}</strong></p>
                @if ($item->isCompound())
                    @foreach ($item->ingredients as $ingredient)
                        <p class="sub">- {{ $ingredient->name }}{{ $ingredient->quantity ? ' '.$ingredient->quantity : '' }}{{ $ingredient->unit ? ' '.$ingredient->unit : '' }}</p>
                    @endforeach
                @endif
                @if ($item->instructionText() !== '')
                    <p>{{ $item->instructionText() }}</p>
                @endif
                <p>Qty: {{ $item->quantity }}{{ $unit ? ' '.$unit : '' }}</p>
            </div>
        @endforeach
    @endforeach
    <div class="rule"></div>
    <p class="center muted">Simpan resep ini. Gunakan obat sesuai aturan.</p>
    <p class="center screen-bar">
        <button type="button" onclick="window.print()">Cetak</button>
    </p>
    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
</body>
</html>
