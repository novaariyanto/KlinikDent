@extends('clinical.care.print.layout')

@section('content')
    @if (! empty($interactive))
        <style>
            @media print { .no-print { display: none !important; } }
            .no-print { margin: 0 0 10px; font-family: DejaVu Sans, sans-serif; }
            .no-print a, .no-print button { font-size: 12px; margin-right: 8px; }
        </style>
        <div class="no-print">
            @if (! empty($success))
                <p><strong>{{ $success }}</strong></p>
            @endif
            @if (! empty($draft))
                <p><strong>Pratinjau.</strong> Surat belum disimpan. Periksa isi, lalu terbitkan.</p>
                <form method="POST" action="{{ $letter->revises_id ? route('medical-letters.revise.store', $letter->revises_id) : route('care.letters.store', $visit) }}">
                    @csrf
                    @foreach ($fields as $key => $value)
                        @if (is_array($value))
                            @foreach ($value as $item)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                            @endforeach
                        @elseif ($value !== null && $value !== '')
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <button type="submit">Terbitkan surat</button>
                    <a href="{{ route('care.letters.create', $visit) }}">Kembali ke form</a>
                </form>
            @else
                <p>
                    <a href="{{ route('medical-letters.index') }}">Daftar surat</a>
                    <a href="{{ route('medical-letters.pdf', $letter) }}" target="_blank">PDF</a>
                    <a href="{{ route('medical-letters.pdf', [$letter, 'download' => 1]) }}">Unduh PDF</a>
                    <a href="{{ route('medical-letters.show', [$letter, 'cetak' => 1]) }}">Cetak</a>
                    @if ($letter->visit)
                        <a href="{{ route('care.show', $letter->visit) }}">Kunjungan</a>
                    @endif
                </p>
                @if ($letter->isIssued() && auth()->user()?->can('medical_record.update'))
                    <p>
                        <a href="{{ route('medical-letters.revise', $letter) }}">Revisi</a>
                    </p>
                    <form method="POST" action="{{ route('medical-letters.cancel', $letter) }}" onsubmit="return confirm('Batalkan surat ini? Riwayat tetap tersimpan.')">
                        @csrf
                        <label>Alasan pembatalan
                            <input name="reason" required maxlength="500" style="min-width: 280px;">
                        </label>
                        <button type="submit">Batalkan surat</button>
                    </form>
                @endif
                @if ($letter->replacement)
                    <p>Diganti oleh <a href="{{ route('medical-letters.show', $letter->replacement) }}">{{ $letter->replacement->number }}</a></p>
                @endif
                @if ($letter->previous)
                    <p>Revisi dari <a href="{{ route('medical-letters.show', $letter->previous) }}">{{ $letter->previous->number }}</a></p>
                @endif
            @endif
        </div>
        @if (! empty($autoprint))
            <script>window.addEventListener('load', function () { window.print(); });</script>
        @endif
    @endif

    @php
        $snapshot = $letter->snapshot ?? [];
        $patient = $snapshot['patient'] ?? [];
        $diagnoses = $snapshot['diagnoses'] ?? [];
        $issuedLabel = $letter->issued_at?->format('d-M-Y') ?: now()->format('d-M-Y');
    @endphp

    <div class="doc-title">{{ $letter->type?->label() ?: 'Surat Keterangan Medis' }}</div>
    <p class="doc-number">Nomor: {{ $letter->number === 'DRAFT' ? 'akan diterbitkan' : $letter->number }}</p>

    @if (! empty($draft))
        <p style="text-align: center; font-weight: bold; letter-spacing: 0.4px;">PRATINJAU — BELUM DITERBITKAN</p>
    @endif

    @if ($letter->status === \App\Enums\MedicalLetterStatus::Cancelled)
        <p style="border: 1px solid #222; padding: 6px 8px;"><strong>Dibatalkan.</strong> {{ $letter->cancel_reason }}</p>
    @endif

    <table class="identity">
        <tr>
            <td class="lbl">Nama pasien</td><td class="sep">:</td><td class="val">{{ $patient['name'] ?? '-' }}</td>
            <td class="lbl">No. RM</td><td class="sep">:</td><td class="val">{{ $patient['medical_record_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">NIK</td><td class="sep">:</td><td class="val">{{ $patient['nik'] ?? '-' }}</td>
            <td class="lbl">J. Kelamin</td><td class="sep">:</td><td class="val">{{ $patient['gender'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tempat / tgl lahir</td><td class="sep">:</td><td class="val" colspan="4">{{ trim(($patient['birth_place'] ?? '-').' / '.($patient['dob'] ?? '-'), ' /') }}</td>
        </tr>
        <tr>
            <td class="lbl">Alamat</td><td class="sep">:</td><td class="val" colspan="4">{{ $patient['address'] ?? '-' }}</td>
        </tr>
        @if (filled($letter->purpose))
            <tr>
                <td class="lbl">Keperluan</td><td class="sep">:</td><td class="val" colspan="4">{{ $letter->purpose }}</td>
            </tr>
        @endif
    </table>

    <div class="section" style="text-align: justify;">
        {!! nl2br(e($letter->body)) !!}
    </div>

    @if ($diagnoses !== [])
        <div class="section">
            <table class="section-head"><tr><td>Diagnosa sementara</td></tr></table>
            <table class="facts">
                @foreach ($diagnoses as $diagnosis)
                    <tr>
                        <th>ICD-10</th>
                        <td>{{ trim((filled($diagnosis['code'] ?? null) ? $diagnosis['code'].' — ' : '').($diagnosis['description'] ?? '')) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <p>Surat ini diterbitkan pada {{ $issuedLabel }}.</p>
@endsection

@section('signature')
    @php
        $doctor = ($letter->snapshot ?? [])['doctor'] ?? [];
        $city = strtoupper((string) ($printCity ?: ''));
        $dateLabel = $letter->issued_at?->format('d-M-Y') ?: now()->format('d-M-Y');
        $signatureQr = \App\Support\Clinical\SignatureQr::dataUri(
            (string) ($documentHash ?? ''),
            (string) ($doctor['name'] ?? ''),
            $doctor['sip'] ?? null,
            $letter->issued_at?->toIso8601String(),
            $letter->number === 'DRAFT' ? ($letter->type?->label() ?: 'Surat Keterangan Medis') : $letter->number,
        );
    @endphp
    <table class="sign">
        <tr>
            <td></td>
            <td>
                <div class="block">
                    {{ $city }}{{ $dateLabel !== '' ? ', '.$dateLabel : '' }}<br>
                    Dokter pemeriksa
                    @if (! empty($signatureSrc))
                        <div><img src="{{ $signatureSrc }}" alt="Tanda tangan" style="height:48px"></div>
                    @else
                        <!-- <div class="space"></div> -->
                    @endif
                    @include('clinical.care.print.partials.signature-qr')
                    <div class="name">{{ $doctor['name'] ?? '........................' }}</div>
                    @if (filled($doctor['sip'] ?? null))
                        <div class="role">SIP {{ $doctor['sip'] }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>
@endsection
