<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Dokumen Klinik' }}</title>
    <style>
        @page { margin: 12mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.35; }
        table { border-collapse: collapse; }
        p { margin: 0 0 6px; }
        .kop { width: 100%; margin-bottom: 4px; }
        .kop td { vertical-align: middle; }
        .kop-logo { width: 72px; }
        .kop-logo img { max-height: 64px; max-width: 68px; }
        .kop-identity { text-align: center; padding: 0 6px; }
        .clinic-name { font-size: 16px; font-weight: bold; letter-spacing: 0.4px; text-transform: uppercase; margin: 0; }
        .clinic-legal { font-size: 9px; margin: 1px 0 0; }
        .clinic-tagline { font-size: 9px; font-style: italic; margin: 1px 0 0; }
        .clinic-meta { font-size: 9px; margin: 1px 0 0; line-height: 1.35; }
        .kop-rule { border-top: 2.2px solid #111; margin-top: 5px; }
        .kop-rule-thin { border-top: 0.6px solid #111; margin-top: 1.5px; margin-bottom: 8px; }
        .doc-title { text-align: center; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6px; margin: 2px 0 8px; text-decoration: underline; }
        .doc-number { text-align: center; font-size: 10px; margin: -4px 0 8px; }
        .letter-meta { width: 100%; margin-bottom: 8px; }
        .letter-meta td { vertical-align: top; font-size: 10px; }
        .addressee { margin: 0 0 8px; }
        .addressee .to { margin: 0; }
        .opening, .closing { text-align: justify; margin: 0 0 8px; }
        .section { margin: 0 0 8px; }
        table.section-head { width: 100%; margin: 0 0 4px; }
        table.section-head td { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 0.5px solid #ccc; padding: 0 0 2px; }
        table.identity { width: 100%; margin: 0 0 8px; }
        table.identity td { vertical-align: top; padding: 1px 3px 1px 0; font-size: 9.5px; }
        table.identity .lbl { width: 108px; white-space: nowrap; }
        table.identity .sep { width: 8px; }
        table.identity .val { width: 34%; }
        table.soap { width: 100%; margin: 0 0 6px; border: 0.7px solid #222; }
        table.soap th, table.soap td { border: 0.4px solid #888; padding: 3px 5px; vertical-align: top; text-align: left; font-weight: normal; }
        table.soap th.group { width: 72px; background: #f3f3f3; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.2px; }
        table.soap th.label { width: 118px; background: #fafafa; font-size: 9px; font-weight: bold; }
        table.soap td { font-size: 9.5px; }
        table.vitals { width: 100%; }
        table.vitals th, table.vitals td { border: 0.4px solid #888; padding: 2px 3px; text-align: center; font-size: 8px; }
        table.vitals th { background: #f7f7f7; font-weight: bold; }
        table.grid, table.facts, table.items { width: 100%; border: 0.7px solid #222; }
        table.grid th, table.grid td, table.facts th, table.facts td, table.items th, table.items td { border: 0.4px solid #999; padding: 4px 6px; text-align: left; vertical-align: top; }
        table.grid th, table.facts th, table.items th { background: #f3f3f3; font-size: 9px; text-transform: uppercase; letter-spacing: 0.2px; }
        table.facts th { width: 32%; }
        .num { text-align: right; white-space: nowrap; }
        ol.instructions { margin: 4px 0 0 16px; padding: 0; }
        ol.instructions li { margin: 0 0 4px; }
        .sign { width: 100%; margin-top: 14px; }
        .sign td { vertical-align: top; }
        .sign .block { width: 230px; margin-left: auto; text-align: center; }
        .sign .space { height: 48px; }
        .sign .qr { height: 78px; width: 78px; margin: 3px auto 1px; }
        .sign .name { font-weight: bold; text-decoration: underline; margin-top: 2px; }
        .sign .role { font-size: 9px; }
        .footer { margin-top: 12px; border-top: 0.6px solid #222; padding-top: 5px; font-size: 8px; color: #333; text-align: center; line-height: 1.4; }
        .hash { font-family: DejaVu Sans Mono, monospace; font-size: 7.5px; word-break: break-all; margin-top: 2px; }
    </style>
</head>
<body>
    @php
        $contactParts = collect([
            ! empty($clinicPhone) ? 'Telp. '.$clinicPhone : null,
            ! empty($clinicEmail) ? $clinicEmail : null,
            ! empty($clinicWebsite) ? $clinicWebsite : null,
        ])->filter()->values();
        $permitParts = collect([
            ! empty($clinicLicense) ? 'Izin Klinik: '.$clinicLicense : null,
            ! empty($clinicNpwp) ? 'NPWP: '.$clinicNpwp : null,
        ])->filter()->values();
        $addressLine = collect($clinicAddressLines ?? [])->filter()->implode(', ');
        $documentHash = $documentHash ?? (
            isset($sheet) && $sheet instanceof \App\Support\Clinical\PrintSheet
                ? $sheet->documentHash($verificationCode ?? null)
                : null
        );
    @endphp

    <table class="kop">
        <tr>
            <td class="kop-logo">
                @if (! empty($logoSrc))
                    <img src="{{ $logoSrc }}" alt="Logo klinik">
                @endif
            </td>
            <td class="kop-identity">
                <div class="clinic-name">{{ $clinicName ?? 'Klinik' }}</div>
                @if (! empty($clinicLegalName))
                    <div class="clinic-legal">{{ $clinicLegalName }}</div>
                @endif
                @if (! empty($clinicTagline))
                    <div class="clinic-tagline">{{ $clinicTagline }}</div>
                @endif
                @if ($addressLine !== '')
                    <p class="clinic-meta">{{ $addressLine }}</p>
                @endif
                @if ($contactParts->isNotEmpty())
                    <p class="clinic-meta">{{ $contactParts->join('  ·  ') }}</p>
                @endif
                @if ($permitParts->isNotEmpty())
                    <p class="clinic-meta">{{ $permitParts->join('  ·  ') }}</p>
                @endif
            </td>
            <td class="kop-logo"></td>
        </tr>
    </table>
    <div class="kop-rule"></div>
    <div class="kop-rule-thin"></div>

    @yield('content')

    @hasSection('signature')
        @yield('signature')
    @else
        @include('clinical.care.print.partials.validation')
    @endif

    <div class="footer">
        @if (! empty($printFooter))
            {{ $printFooter }}<br>
        @endif
        Dokumen ini telah divalidasi dan dicetak otomatis oleh sistem {{ $clinicName ?? 'klinik' }}@if (! empty($printedAt)) pada {{ $printedAt->format('d-M-Y H:i') }}@endif. Tanda tangan basah tidak diperlukan.
        @if (! empty($verificationCode))
            <br>Cek keaslian: {{ url('/cek/'.$verificationCode) }} · kode {{ $verificationCode }}
        @endif
        @if (! empty($documentHash))
            <div class="hash">Hash Data: {{ $documentHash }}</div>
        @endif
    </div>
</body>
</html>
