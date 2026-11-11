<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 6mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
            margin: 0;
        }
        .clinic { font-size: 8px; text-transform: uppercase; letter-spacing: 0.02em; margin-bottom: 4px; }
        .patient { font-size: 10px; font-weight: bold; margin-bottom: 6px; }
        .drug { font-size: 12px; font-weight: bold; margin-bottom: 6px; line-height: 1.25; }
        .row { margin-bottom: 3px; }
        .muted { color: #555; }
    </style>
</head>
<body>
    <div class="clinic">{{ $clinicName }}</div>
    <div class="patient">{{ $patientName }}</div>
    <div class="drug">{{ $item->label() }}</div>
    <div class="row"><span class="muted">Dosis</span> {{ $item->dosage ?: '-' }}</div>
    <div class="row"><span class="muted">Frekuensi</span> {{ $item->frequency ?: '-' }}</div>
    <div class="row"><span class="muted">Aturan pakai</span> {{ $item->duration ?: '-' }}</div>
    <div class="row"><span class="muted">Qty</span> {{ $item->quantity }}{{ $item->isCompound() ? ' bks' : '' }}</div>
</body>
</html>
