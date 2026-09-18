<?php

namespace App\Support\Clinical;

use App\Enums\BillingStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\ToothStatus;
use App\Models\MedicalRecord;
use App\Models\OdontogramLog;
use App\Models\OdontogramTooth;
use App\Models\Prescription;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\Models\Visit;

class ClinicalCareService
{
    public function __construct(protected TariffResolver $tariffs) {}

    public function ensureRecord(Visit $visit): MedicalRecord
    {
        return MedicalRecord::query()->firstOrCreate(['visit_id' => $visit->id]);
    }

    public function updateTooth(
        Visit $visit,
        User $user,
        string $number,
        ToothStatus|string $status,
        ?string $notes = null,
        array $surfaces = [],
    ): OdontogramTooth {
        if (! $status instanceof ToothStatus) {
            $status = ToothStatus::from($status);
        }

        $tooth = OdontogramTooth::query()->firstOrNew([
            'patient_id' => $visit->patient_id,
            'tooth_number' => $number,
        ]);

        $previous = $tooth->exists ? $tooth->status : ToothStatus::Healthy;

        $tooth->status = $status;
        $tooth->notes = $notes;

        if ($status === ToothStatus::Healthy || $status->isWholeTooth()) {
            $tooth->surfaces = null;
        } else {
            $mapped = [];
            foreach ($surfaces as $surface) {
                $key = $surface instanceof \BackedEnum ? $surface->value : (string) $surface;
                if ($key !== '') {
                    $mapped[$key] = $status->value;
                }
            }
            $tooth->surfaces = $mapped !== [] ? $mapped : null;
        }
        $tooth->save();

        if ($previous !== $status) {
            OdontogramLog::query()->create([
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'tooth_number' => $number,
                'previous_status' => $previous,
                'new_status' => $status,
                'recorded_by' => $user->id,
            ]);
        }

        return $tooth;
    }

    public function addProcedure(Visit $visit, int $procedureId, ?string $toothNumber, int $quantity): ProcedureRecord
    {
        return ProcedureRecord::query()->create([
            'visit_id' => $visit->id,
            'procedure_id' => $procedureId,
            'tooth_number' => $toothNumber,
            'quantity' => $quantity,
            'price_at_time' => $this->tariffs->priceFor($visit, $procedureId),
            'billing_status' => BillingStatus::Unbilled,
        ]);
    }

    public function draftPrescription(Visit $visit, User $user): Prescription
    {
        return $visit->prescriptions()
            ->where('status', PrescriptionStatus::Draft)
            ->first()
            ?? $visit->prescriptions()->create([
                'doctor_id' => $user->id,
                'status' => PrescriptionStatus::Draft,
            ]);
    }
}
