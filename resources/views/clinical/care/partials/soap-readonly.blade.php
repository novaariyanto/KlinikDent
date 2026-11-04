@php
    $sheetVisit = $sheetVisit ?? $visit;
    $sheet = \App\Support\Clinical\PrintSheet::for($sheetVisit);
    $sheetRecord = $sheetVisit->medicalRecord;
    $vitals = is_array($sheetRecord?->vital_signs) ? $sheetRecord->vital_signs : [];
    $bpSys = $sheet->vital('sys');
    $bpDia = $sheet->vital('dia');
    $vitalBits = array_filter([
        ($bpSys !== '-' || $bpDia !== '-') ? 'TD '.trim($bpSys.'/'.$bpDia, '/').' mmHg' : null,
        filled($vitals['pulse'] ?? null) ? 'Nadi '.$vitals['pulse'].' x/mnt' : null,
        filled($vitals['respiration'] ?? null) ? 'Napas '.$vitals['respiration'].' x/mnt' : null,
        filled($vitals['temperature'] ?? null) ? 'Suhu '.$vitals['temperature'].' °C' : null,
        filled($vitals['weight'] ?? null) ? 'Berat '.$vitals['weight'].' kg' : null,
        filled($vitals['height'] ?? null) ? 'Tinggi '.$vitals['height'].' cm' : null,
        filled($vitals['general_condition'] ?? null) ? 'Keadaan umum '.$vitals['general_condition'] : null,
        filled($vitals['consciousness'] ?? null) ? 'Kesadaran '.$vitals['consciousness'] : null,
    ]);
    $notes = collect([
        $sheetRecord?->care_notes,
        $sheetRecord?->clinical_notes,
    ])->filter(fn ($line) => filled($line))->unique()->implode("\n");
@endphp

<div class="soap-sheet-wrap soap-sheet-wrap--readonly">
    <table class="soap-sheet">
        <tbody>
            <tr class="soap-row soap-row--keluhan">
                <th class="soap-sheet__group soap-sheet__group--s" rowspan="2">Subjektif</th>
                <th class="soap-sheet__label">Anamnesia / Keluhan</th>
                <td class="soap-sheet__value">
                    <span class="soap-sheet__colon">:</span>
                    <div class="soap-sheet__fill">{!! nl2br(e($sheet->anamnesis())) !!}</div>
                </td>
            </tr>
            <tr class="soap-row soap-row--alergi">
                <th class="soap-sheet__label">Riwayat Alergi</th>
                <td class="soap-sheet__value">
                    <span class="soap-sheet__colon">:</span>
                    <div class="soap-sheet__fill">
                        <div>Alergi Makanan : {{ $sheet->allergy('food') }}</div>
                        <div>Alergi Udara : {{ $sheet->allergy('air') }}</div>
                        <div>Alergi Obat : {{ $sheet->allergy('drug') }}</div>
                        <div>Alergi lainnya : {{ $sheet->allergy('other') }}</div>
                    </div>
                </td>
            </tr>
            <tr class="soap-row soap-row--vital">
                <th class="soap-sheet__group soap-sheet__group--o" rowspan="3">Objektif</th>
                <th class="soap-sheet__label">Vital Sign</th>
                <td class="soap-sheet__value">
                    <span class="soap-sheet__colon">:</span>
                    <div class="soap-sheet__fill">{{ $vitalBits === [] ? '—' : implode(' · ', $vitalBits) }}</div>
                </td>
            </tr>
            <tr class="soap-row soap-row--fisik">
                <th class="soap-sheet__label">Pemeriksaan Fisik</th>
                <td class="soap-sheet__value">
                    <span class="soap-sheet__colon">:</span>
                    <div class="soap-sheet__fill">{{ $sheet->physicalExam() }}</div>
                </td>
            </tr>
            <tr class="soap-row soap-row--penunjang">
                <th class="soap-sheet__label">Penunjang</th>
                <td class="soap-sheet__value">
                    <span class="soap-sheet__colon">:</span>
                    <div class="soap-sheet__fill">{{ $sheet->supporting() }}</div>
                </td>
            </tr>
            <tr class="soap-row soap-row--icd10">
                <th class="soap-sheet__group soap-sheet__group--a" rowspan="2">Asesmen</th>
                <th class="soap-sheet__label">Diagnosa-ICD-10</th>
                <td class="soap-sheet__value">
                    <span class="soap-sheet__colon">:</span>
                    <div class="soap-sheet__fill">{!! nl2br(e($sheet->icd10())) !!}</div>
                </td>
            </tr>
            <tr class="soap-row soap-row--icd9">
                <th class="soap-sheet__label">Diagnosa-ICD-9-CM</th>
                <td class="soap-sheet__value">
                    <span class="soap-sheet__colon">:</span>
                    <div class="soap-sheet__fill">{!! nl2br(e($sheet->icd9())) !!}</div>
                </td>
            </tr>
            <tr class="soap-row soap-row--rencana">
                <th class="soap-sheet__group soap-sheet__group--p" rowspan="4">Planning</th>
                <th class="soap-sheet__label">Rencana Asuhan</th>
                <td class="soap-sheet__value">
                    <span class="soap-sheet__colon">:</span>
                    <div class="soap-sheet__fill">{{ $sheet->carePlan() }}</div>
                </td>
            </tr>
            <tr class="soap-row soap-row--catatan">
                <th class="soap-sheet__label">Catatan Khusus</th>
                <td class="soap-sheet__value">
                    <span class="soap-sheet__colon">:</span>
                    <div class="soap-sheet__fill">{!! nl2br(e($notes !== '' ? $notes : '—')) !!}</div>
                </td>
            </tr>
            <tr class="soap-row soap-row--terapi">
                <th class="soap-sheet__label">Terapi / Resep</th>
                <td class="soap-sheet__value">
                    <span class="soap-sheet__colon">:</span>
                    <div class="soap-sheet__fill">{!! nl2br(e($sheet->therapy())) !!}</div>
                </td>
            </tr>
            <tr class="soap-row soap-row--tindakan">
                <th class="soap-sheet__label">Tindakan</th>
                <td class="soap-sheet__value">
                    <span class="soap-sheet__colon">:</span>
                    <div class="soap-sheet__fill">{!! nl2br(e($sheet->procedures())) !!}</div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
