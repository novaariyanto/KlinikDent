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
        $instructions = $record?->plan_instructions ?? [];

        return [
            'keluhan' => filled($record?->chief_complaint) || filled($record?->anamnesis),
            'screening' => self::hasValues($vitals) || self::hasValues($systemic),
            'pemeriksaan' => self::hasValues($exam) || filled($record?->initial_examination),
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
            'instruksi' => filled($record?->clinical_notes)
                || filled($instructions['items'] ?? null)
                || filled($instructions['extra'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function hasValues(array $data): bool
    {
        return collect($data)->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])->isNotEmpty();
    }
}
