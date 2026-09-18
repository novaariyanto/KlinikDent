<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 16mm 16mm 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.45; }
        table { border-collapse: collapse; }
        p { margin: 0 0 8px; }
        .kop { width: 100%; margin-bottom: 6px; }
        .kop td { vertical-align: middle; }
        .kop-logo { width: 92px; }
        .kop-logo img { max-height: 78px; max-width: 86px; }
        .kop-identity { text-align: center; padding: 0 8px; }
        .clinic-name { font-size: 18px; font-weight: bold; letter-spacing: 0.6px; text-transform: uppercase; margin: 0 0 2px; color: #111; }
        .clinic-legal { font-size: 10px; margin: 0 0 2px; }
        .clinic-tagline { font-size: 10px; font-style: italic; color: #444; margin: 0 0 4px; }
        .clinic-meta { font-size: 9.5px; color: #333; margin: 0; line-height: 1.4; }
        .kop-rule { border-top: 2.4px solid #111; margin-top: 6px; }
        .kop-rule-thin { border-top: 0.6px solid #111; margin-top: 1.5px; margin-bottom: 14px; }
        .doc-title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; margin: 0 0 4px; text-decoration: underline; }
        .doc-number { text-align: center; font-size: 10.5px; margin: 0 0 14px; }
        .letter-meta { width: 100%; margin-bottom: 12px; }
        .letter-meta td { vertical-align: top; font-size: 11px; }
        .letter-meta .lbl { width: 72px; }
        .letter-meta .sep { width: 12px; }
        .addressee { margin: 0 0 12px; }
        .addressee .to { margin: 0; }
        .opening, .closing { text-align: justify; margin: 0 0 10px; }
        .section { margin: 0 0 12px; }
        table.section-head { width: 100%; margin: 0 0 6px; }
        table.section-head td { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 0.5px solid #ccc; padding: 0 0 3px; }
        table.facts { width: 100%; border: 0.8px solid #222; }
        table.facts th, table.facts td { border: 0.5px solid #999; padding: 5px 7px; text-align: left; vertical-align: top; }
        table.facts th { background: #f3f3f3; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; width: 32%; }
        ol.instructions { margin: 4px 0 0 18px; padding: 0; }
        ol.instructions li { margin: 0 0 5px; }
        .sign { width: 100%; margin-top: 28px; }
        .sign td { width: 50%; vertical-align: top; }
        .sign .block { width: 220px; margin-left: auto; text-align: center; }
        .sign .space { height: 62px; }
        .sign .name { font-weight: bold; text-decoration: underline; }
        .sign .role { font-size: 10px; }
        .footer { margin-top: 22px; border-top: 0.6px solid #bbb; padding-top: 6px; font-size: 8.5px; color: #555; text-align: center; line-height: 1.4; }
    </style>
</head>
<body>
    @php
        $contactParts = collect([
            ! empty($clinicPhone) ? 'Telp. '.$clinicPhone : ($branch?->phone ? 'Telp. '.$branch->phone : null),
            $clinicEmail ?? null,
            $clinicWebsite ?? null,
        ])->filter()->values();
        $permitParts = collect([
            ! empty($clinicLicense) ? 'Izin Klinik: '.$clinicLicense : null,
            ! empty($clinicNpwp) ? 'NPWP: '.$clinicNpwp : null,
        ])->filter()->values();
    @endphp

    <table class="kop">
        <tr>
            <td class="kop-logo">
                @if (! empty($logoSrc))
                    <img src="{{ $logoSrc }}" alt="Logo klinik">
                @endif
            </td>
            <td class="kop-identity">
                <div class="clinic-name">{{ $clinicName }}</div>
                @if (! empty($clinicLegalName))
                    <div class="clinic-legal">{{ $clinicLegalName }}</div>
                @endif
                @if (! empty($clinicTagline))
                    <div class="clinic-tagline">{{ $clinicTagline }}</div>
                @endif
                @foreach ($clinicAddressLines ?? [] as $line)
                    <p class="clinic-meta">{{ $line }}</p>
                @endforeach
                @if (empty($clinicAddressLines) && $branch?->address)
                    <p class="clinic-meta">{{ $branch->address }}</p>
                @endif
                @if ($branch?->name)
                    <p class="clinic-meta">{{ $branch->name }}</p>
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

    <table class="sign">
        <tr>
            <td></td>
            <td>
                <div class="block">
                    {{ $printCity ?: $branch?->name }}, {{ $printedDateLabel ?? $printedAt->format('d-m-Y') }}<br>
                    Dokter pemeriksa
                    <div class="space"></div>
                    <div class="name">{{ $visit->doctor?->name ?: '........................' }}</div>
                    <div class="role">Dokter Gigi</div>
                    @if (! empty($picSip) && $visit->doctor?->name && $picName && strcasecmp((string) $picName, (string) $visit->doctor->name) === 0)
                        <div class="role">SIP {{ $picSip }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        @if ($printFooter)
            {{ $printFooter }}<br>
        @endif
        Dokumen ini dicetak dari sistem {{ $clinicName }} pada {{ $printedAt->format('d-m-Y H:i') }}.
    </div>
</body>
</html>
