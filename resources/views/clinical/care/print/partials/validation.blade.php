@php
    $doctorName = isset($sheet) && $sheet instanceof \App\Support\Clinical\PrintSheet
        ? $sheet->doctorName()
        : (isset($visit) ? (string) ($visit->doctor?->name ?? '') : '');
    $displayName = ($doctorName !== '' && $doctorName !== '-') ? $doctorName : '........................';
    $sip = isset($sheet) && $sheet instanceof \App\Support\Clinical\PrintSheet ? $sheet->doctorSip() : '';
    if ($sip === '' && $doctorName !== '-' && ! empty($picSip) && ! empty($picName) && strcasecmp((string) $picName, $doctorName) === 0) {
        $sip = $picSip;
    }
    $cityLabel = strtoupper((string) ($printCity ?: (($branch ?? null)?->name ?? '')));
    $dateLabel = ! empty($printedAt) ? $printedAt->format('d-M-Y') : ($printedDateLabel ?? '');
    $hash = isset($sheet) && $sheet instanceof \App\Support\Clinical\PrintSheet
        ? $sheet->documentHash($verificationCode ?? null)
        : (string) ($documentHash ?? '');
    $signatureQr = \App\Support\Clinical\SignatureQr::dataUri(
        $hash,
        $displayName,
        $sip !== '' ? $sip : null,
        ! empty($printedAt) ? $printedAt->toIso8601String() : null,
        $title ?? null,
    );
@endphp
<table class="sign">
    <tr>
        <td></td>
        <td>
            <div class="block">
                {{ $cityLabel }}{{ $dateLabel !== '' ? ', '.$dateLabel : '' }}<br>
                Dokter Penanggung Jawab Asesmen
                @if (! empty($signatureSrc))
                    <div><img src="{{ $signatureSrc }}" alt="Tanda tangan" style="height:48px"></div>
                @else
                    <!-- <div class="space"></div> -->
                @endif
                @include('clinical.care.print.partials.signature-qr')
                <div class="name">{{ $displayName }}</div>
                @if ($sip !== '')
                    <div class="role">SIP {{ $sip }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>
