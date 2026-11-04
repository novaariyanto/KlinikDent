@extends('clinical.care.print.layout')

@php
    $identity = $payload['identity'] ?? [];
    $period = $payload['period'] ?? [];
    $fmt = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d-M-Y') : '-';
    $text = fn ($value) => filled($value) ? nl2br(e($value)) : '<span>Belum ada data</span>';
@endphp

@section('content')
    <div class="doc-title">Resume Medis Pasien</div>
    <p class="doc-number">Nomor: {{ $letterNumber }}</p>

    <table class="identity">
        <tr>
            <td class="lbl">Nama Pasien</td><td class="sep">:</td><td class="val">{{ $identity['name'] ?? '-' }}</td>
            <td class="lbl">No. RM</td><td class="sep">:</td><td class="val">{{ $identity['medical_record_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal Lahir</td><td class="sep">:</td><td class="val">{{ $fmt($identity['dob'] ?? null) }}</td>
            <td class="lbl">J. Kelamin</td><td class="sep">:</td><td class="val">{{ $identity['gender'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">NIK</td><td class="sep">:</td><td class="val">{{ $identity['nik'] ?? '-' }}</td>
            <td class="lbl">Periode</td><td class="sep">:</td><td class="val">{{ $fmt($period['from'] ?? null) }} s/d {{ $fmt($period['to'] ?? null) }}</td>
        </tr>
        <tr>
            <td class="lbl">Alamat</td><td class="sep">:</td><td class="val" colspan="4">{{ $identity['address'] ?? '-' }}</td>
        </tr>
    </table>

    <table class="soap">
        <tr>
            <th class="label">Kunjungan tercakup</th>
            <td>
                @foreach ($payload['visits'] ?? [] as $visit)
                    {{ $fmt($visit['date']) }} · {{ $visit['number'] }} · {{ $visit['doctor'] ?: '-' }}@if (! empty($visit['branch'])) · {{ $visit['branch'] }}@endif<br>
                @endforeach
            </td>
        </tr>
        <tr>
            <th class="label">Keluhan</th>
            <td>
                @forelse ($payload['complaints'] ?? [] as $row)
                    {{ $fmt($row['date']) }}: {{ $row['text'] }}<br>
                @empty
                    Belum ada data
                @endforelse
            </td>
        </tr>
        <tr><th class="label">Riwayat relevan</th><td>{!! $text($payload['relevant_history'] ?? null) !!}</td></tr>
        <tr>
            <th class="label">Diagnosis</th>
            <td>
                @forelse ($payload['diagnoses'] ?? [] as $row)
                    {{ trim(($row['code'] ? $row['code'].' ' : '').$row['description']) }}
                    @if (! empty($row['teeth'])) (gigi {{ implode(', ', $row['teeth']) }})@endif
                    {{ $row['primary'] ? '· utama' : '' }} · {{ $row['visits'] }} kunjungan<br>
                @empty
                    Belum ada data
                @endforelse
            </td>
        </tr>
        <tr>
            <th class="label">Tindakan</th>
            <td>
                @forelse ($payload['procedures'] ?? [] as $row)
                    {{ $row['name'] }} × {{ $row['count'] }}
                    @if (! empty($row['teeth'])) (gigi {{ implode(', ', $row['teeth']) }})@endif<br>
                @empty
                    Belum ada data
                @endforelse
            </td>
        </tr>
        <tr><th class="label">Perkembangan</th><td>{!! $text($payload['progress'] ?? null) !!}</td></tr>
        <tr>
            <th class="label">Terapi / obat</th>
            <td>
                @forelse ($payload['therapy'] ?? [] as $row)
                    {{ $fmt($row['date']) }}: {{ $row['label'] }}@if ($row['rule'] !== '') — {{ $row['rule'] }}@endif @if ($row['quantity']) ({{ $row['quantity'] }})@endif<br>
                @empty
                    Belum ada data
                @endforelse
            </td>
        </tr>
        <tr><th class="label">Kondisi terakhir</th><td>{!! $text($payload['last_condition'] ?? null) !!}</td></tr>
        <tr><th class="label">Rencana lanjutan</th><td>{!! $text($payload['plan'] ?? null) !!}</td></tr>
    </table>
@endsection

@section('signature')
    @php
        $authorName = data_get($payload, 'author.name') ?: '........................';
        $authorSip = (string) data_get($payload, 'author.sip', '');
        $signatureQr = \App\Support\Clinical\SignatureQr::dataUri($documentHash, $authorName, $authorSip !== '' ? $authorSip : null, $document->signed_at?->toIso8601String(), $title ?? null);
        $cityLabel = strtoupper((string) ($printCity ?: (($branch ?? null)?->name ?? '')));
    @endphp
    <table class="sign">
        <tr>
            <td></td>
            <td>
                <div class="block">
                    {{ $cityLabel }}, {{ ($document->signed_at ?? $document->created_at)?->format('d-M-Y') }}<br>
                    Dokter Penyusun Resume
                    @if (! empty($signatureSrc))
                        <div><img src="{{ $signatureSrc }}" alt="Tanda tangan" style="height:48px"></div>
                    @else
                        <!-- <div class="space"></div> -->
                    @endif
                    @include('clinical.care.print.partials.signature-qr')
                    <div class="name">{{ $authorName }}</div>
                    @if ($authorSip !== '')
                        <div class="role">SIP {{ $authorSip }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>
@endsection
