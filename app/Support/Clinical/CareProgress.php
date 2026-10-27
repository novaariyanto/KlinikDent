<?php

namespace App\Support\Clinical;

use App\Enums\ToothStatus;
use App\Models\MedicalRecord;
use App\Models\Visit;
use Illuminate\Support\Collection;

final class CareProgress
{
    /**
     * @param  Collection<string, mixed>  $teeth
     * @return array<string, bool>
     */
    public static function for(Visit $visit, ?MedicalRecord $record, Collection $teeth): array
    {
        $vitals = $record?->vital_signs ?? [];
        $systemic = $record?->systemic_history ?? [];
        $exam = $record?->dental_exam ?? [];

        return [
            'keluhan' => filled($record?->chief_complaint) || filled($record?->anamnesis),
            'screening' => self::screeningComplete($vitals, $systemic),
            'pemeriksaan' => self::examComplete($exam, $record?->initial_examination),
            'odontogram' => $teeth->contains(function ($tooth) {
                $status = $tooth->status ?? null;

                return $status instanceof ToothStatus
                    ? $status !== ToothStatus::Healthy
                    : filled($status) && $status !== ToothStatus::Healthy->value;
            }),
            'diagnosis' => $visit->diagnoses->isNotEmpty(),
            'tindakan' => $visit->procedureRecords->isNotEmpty(),
            'resep' => $visit->prescriptions->contains(fn ($prescription) => $prescription->items->isNotEmpty()),
            'rujukan' => $visit->referrals->isNotEmpty(),
            'instruksi' => self::instructionComplete($record),
        ];
    }

    /**
     * @param  array<string, mixed>  $vitals
     * @param  array<string, mixed>  $systemic
     */
    public static function screeningComplete(array $vitals, array $systemic): bool
    {
        foreach (['blood_pressure', 'blood_pressure_sys', 'blood_pressure_dia', 'pulse', 'temperature', 'respiration', 'weight', 'height'] as $key) {
            if (filled($vitals[$key] ?? null)) {
                return true;
            }
        }

        if (filter_var($systemic['none'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $conditions = $systemic['conditions'] ?? [];

        if (is_array($conditions) && $conditions !== []) {
            return true;
        }

        foreach (['blood_type', 'rhesus', 'pregnancy_status', 'other_detail', 'allergy_detail', 'allergy_reaction', 'allergy_note', 'medication_detail', 'allergy_category', 'allergy_type', 'allergy_clinical_status', 'allergy_severity'] as $key) {
            if (filled($systemic[$key] ?? null)) {
                return true;
            }
        }

        $allergy = (string) ($systemic['allergy_status'] ?? '');

        if (in_array($allergy, ['unknown', 'none_confirmed', 'present'], true)) {
            return true;
        }

        return in_array((string) ($systemic['medication_status'] ?? ''), ['none_confirmed', 'present'], true);
    }

    /**
     * @param  array<string, mixed>  $exam
     */
    public static function examComplete(array $exam, mixed $initial = null): bool
    {
        if (filled($initial)) {
            return true;
        }

        $ohis = $exam['ohis'] ?? null;
        unset($exam['ohis']);

        if (self::hasValues($exam)) {
            return true;
        }

        return is_array($ohis)
            && is_numeric($ohis['debris_index'] ?? null)
            && is_numeric($ohis['calculus_index'] ?? null);
    }

    public static function instructionComplete(?MedicalRecord $record): bool
    {
        if ($record === null) {
            return false;
        }

        $instructions = is_array($record->plan_instructions) ? $record->plan_instructions : [];
        $items = $instructions['items'] ?? [];

        return filled($record->clinical_notes)
            || (is_array($items) && $items !== [])
            || filled($instructions['extra'] ?? null)
            || filled($instructions['goal'] ?? null)
            || filled($instructions['prognosis'] ?? null)
            || filled($record->control_date)
            || filled($record->care_notes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function hasValues(array $data): bool
    {
        return collect($data)->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])->isNotEmpty();
    }
}
