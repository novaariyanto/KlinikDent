<?php

namespace App\Support\Clinical;

use App\Enums\VisitStatus;
use App\Models\Visit;

class DischargeSummary
{
    /**
     * @return array{
     *     groups: array<string, array<string, mixed>>,
     *     vitalItems: array<string, string>,
     *     dischargeMode: string,
     *     registrationNumber: string,
     *     registrationDate: string
     * }
     */
    public static function for(Visit $visit): array
    {
        $visit->loadMissing([
            'patient',
            'doctor.doctorProfile',
            'branch',
            'room',
            'payer',
            'queue',
            'diagnoses',
            'procedureRecords.procedure',
            'prescriptions.items.medicine',
            'prescriptions.items.ingredients.medicine',
            'referrals',
            'medicalRecord',
        ]);

        $sheet = PrintSheet::for($visit);
        $row = new ExaminationQueueRow($visit);
        $record = $visit->medicalRecord;
        $dash = fn ($value) => filled(trim((string) $value)) ? trim((string) $value) : '-';

        $plan = is_array($record?->plan_instructions) ? $record->plan_instructions : [];
        $history = is_array($record?->systemic_history) ? $record->systemic_history : [];
        $vitals = is_array($record?->vital_signs) ? $record->vital_signs : [];
        $systemicLabels = CareExamOptions::systemic();

        $diseaseLine = collect($history['conditions'] ?? [])
            ->filter(fn ($key) => is_string($key) && $key !== '' && $key !== 'allergy')
            ->map(fn ($key) => $systemicLabels[$key] ?? $key)
            ->implode(', ');

        $allergyLine = match ((string) ($history['allergy_status'] ?? '')) {
            'none_confirmed' => 'Tidak ada',
            'unknown' => 'Tidak diketahui',
            'present' => trim(collect([
                CareExamOptions::allergyCategories()[$history['allergy_category'] ?? ''] ?? null,
                $history['allergy_detail'] ?? null,
            ])->filter(fn ($value) => filled($value))->implode(' — ')) ?: 'Ada',
            default => '',
        };

        $height = is_numeric($sheet->vital('height')) ? (float) $sheet->vital('height') : null;
        $weight = is_numeric($sheet->vital('weight')) ? (float) $sheet->vital('weight') : null;
        $bmiLine = null;
        if ($height > 0 && $weight > 0) {
            $bmi = round($weight / (($height / 100) ** 2), 1);
            $bmiLine = $bmi.' ('.match (true) {
                $bmi < 18.5 => 'Berat badan kurang',
                $bmi < 23 => 'Normal',
                $bmi < 25 => 'Kelebihan berat badan',
                $bmi < 30 => 'Obesitas I',
                default => 'Obesitas II',
            }.')';
        }
        $bp = $sheet->vital('sys') !== '-' && $sheet->vital('dia') !== '-'
            ? $sheet->vital('sys').'/'.$sheet->vital('dia').' mmHg'
            : null;
        $vitalItems = array_filter([
            'Tinggi' => $height ? $sheet->vital('height').' Cm' : null,
            'Berat' => $weight ? $sheet->vital('weight').' Kg' : null,
            'IMT' => $bmiLine,
            'Lingkar Perut' => $sheet->vital('waist') !== '-' ? $sheet->vital('waist').' cm' : null,
            'Tekanan Darah' => $bp,
            'Suhu' => $sheet->vital('temperature') !== '-' ? $sheet->vital('temperature').' °C' : null,
            'Nadi' => $sheet->vital('pulse') !== '-' ? $sheet->vital('pulse').' bpm' : null,
            'Frekuensi Napas' => $sheet->vital('respiration') !== '-' ? $sheet->vital('respiration').' per Menit' : null,
        ]);

        $diagnoses = $visit->diagnoses
            ->map(fn ($diagnosis) => trim($diagnosis->code.'-'.$diagnosis->description, '- '))
            ->filter()
            ->values();

        $procedureLines = $visit->procedureRecords
            ->filter(fn ($item) => filled($item->icd9_code))
            ->map(fn ($item) => Icd9Catalog::label((string) $item->icd9_code))
            ->unique()
            ->values();
        if ($procedureLines->isEmpty()) {
            $procedureLines = $visit->procedureRecords
                ->map(fn ($item) => ($item->procedure?->name ?: 'Tindakan').(filled($item->tooth_number) ? ' (gigi '.$item->tooth_number.')' : ''))
                ->values();
        }

        $therapyLines = $visit->prescriptions
            ->flatMap(fn ($prescription) => $prescription->items->map(
                fn ($item) => implode(' ', array_filter([$item->label(), $item->dosage, $item->frequency, $item->duration]))
            ))
            ->filter()
            ->values();

        $instructionLabels = CareExamOptions::instructions();
        $educationLine = collect($plan['items'] ?? [])
            ->map(fn ($key) => $instructionLabels[$key] ?? (is_string($key) ? $key : null))
            ->filter()
            ->push($plan['extra'] ?? null)
            ->filter(fn ($value) => filled($value))
            ->implode('; ');

        $followUp = collect();
        foreach ($visit->referrals as $referral) {
            $followUp->push('Rujuk ke '.$dash($referral->referred_to));
        }
        if ($record?->control_date) {
            $followUp->push('Kontrol Ulang '.$record->control_date->format('d-m-Y'));
        }
        if ($record?->followup_date) {
            $followUp->push('Follow up '.$record->followup_date->format('d-m-Y'));
        }

        $dischargeMode = match (true) {
            $visit->status === VisitStatus::Cancelled => 'BATAL',
            $visit->referrals->isNotEmpty() => 'DIRUJUK',
            $visit->status === VisitStatus::Done => 'BEROBAT JALAN',
            default => 'BELUM PULANG',
        };

        $lines = fn ($items) => collect($items)->filter(fn ($value) => filled($value))->values()->all();

        return [
            'groups' => [
                'Subjektif' => [
                    'Anamnesa' => $lines(explode("\n", $sheet->anamnesis())),
                    'Riwayat Penyakit' => $lines([$diseaseLine]),
                    'Riwayat Alergi' => $lines([$allergyLine]),
                ],
                'Objektif' => [
                    'Tanda Vital' => 'vitals',
                    'Laboratorium' => [],
                    'Radiologi' => [],
                    'Penunjang Lain' => $lines([$sheet->supporting() !== '-' ? $sheet->supporting() : null]),
                ],
                'Asesmen' => [
                    'Diagnosa Utama' => $lines([$diagnoses->first()]),
                    'Diagnosa Sekunder' => $lines($diagnoses->slice(1)),
                    'Diagnosa Keperawatan' => $lines([$sheet->nursingDiagnosis() !== '-' ? $sheet->nursingDiagnosis() : null]),
                ],
                'Tata Laksana' => [
                    'Tindakan / Prosedur' => $lines($procedureLines),
                    'Terapi Obat' => $lines($therapyLines),
                    'BHP' => [],
                    'Diet' => $lines([$plan['diet'] ?? null]),
                    'Edukasi' => $lines([$educationLine]),
                ],
                'Kondisi Pulang' => [
                    'Keadaan Umum' => $lines([$vitals['general_condition'] ?? null]),
                    'Kesadaran' => $lines([$vitals['consciousness'] ?? null]),
                    'Catatan Penting' => $lines(explode("\n", $sheet->specialNotes() !== '-' ? $sheet->specialNotes() : '')),
                ],
                'Rencana Pulang' => [
                    'Rencana Tindak Lanjut' => $lines($followUp),
                    'Prognosa' => $lines([$plan['prognosis'] ?? null]),
                    'Cara Keluar' => 'mode',
                ],
            ],
            'vitalItems' => $vitalItems,
            'dischargeMode' => $dischargeMode,
            'registrationNumber' => $row->registrationNumber(),
            'registrationDate' => $row->registrationDate(),
        ];
    }
}
