<?php

namespace App\Support\Clinical;

use App\Enums\BillingStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\ToothStatus;
use App\Models\MedicalRecord;
use App\Models\OdontogramLog;
use App\Models\OdontogramSnapshot;
use App\Models\OdontogramTooth;
use App\Models\Prescription;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\Models\Visit;
use App\Support\Billing\DoctorFeeWaiverService;

class ClinicalCareService
{
    public function __construct(protected TariffResolver $tariffs) {}

    public function ensureRecord(Visit $visit): MedicalRecord
    {
        $record = MedicalRecord::query()->firstOrCreate(['visit_id' => $visit->id]);

        return app(CareEpisodeService::class)->assign($visit, $record);
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

        $conditions = [];

        if ($status !== ToothStatus::Healthy) {
            $conditions[] = [
                'code' => $status->value,
                'surfaces' => array_map(fn ($s) => $s instanceof \BackedEnum ? $s->value : (string) $s, $surfaces),
            ];
        }

        return $this->syncToothConditions($visit, $user, $number, $conditions, $notes, strict: false);
    }

    /**
     * @param  array<int, mixed>  $conditions
     */
    public function syncToothConditions(
        Visit $visit,
        User $user,
        string $number,
        array $conditions,
        ?string $notes = null,
        bool $strict = true,
    ): OdontogramTooth {
        $tooth = OdontogramTooth::query()->firstOrNew([
            'patient_id' => $visit->patient_id,
            'tooth_number' => $number,
        ]);

        $previous = $tooth->exists ? $tooth->status : ToothStatus::Healthy;
        $previousCodes = array_column(ToothConditions::fromRecord($tooth->exists ? $tooth : null), 'code');

        $items = ToothConditions::clean($number, $conditions, $strict);
        $status = ToothConditions::primary($items);
        $map = ToothConditions::surfaceMap($items);

        $tooth->status = $status;
        $tooth->surfaces = $map !== [] ? $map : null;
        $tooth->conditions = $items !== [] ? $items : null;
        $tooth->notes = $notes;
        $tooth->save();

        OdontogramSnapshot::query()->updateOrCreate(
            ['visit_id' => $visit->id, 'tooth_number' => $number],
            [
                'patient_id' => $visit->patient_id,
                'status' => $status,
                'conditions' => $items !== [] ? $items : null,
                'notes' => $notes,
                'recorded_by' => $user->id,
            ],
        );

        $added = array_values(array_diff(array_column($items, 'code'), $previousCodes));
        $logged = $added !== [] ? $added : ($previous !== $status ? [$status->value] : []);

        foreach ($logged as $code) {
            OdontogramLog::query()->create([
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'tooth_number' => $number,
                'previous_status' => $previous,
                'new_status' => $code,
                'recorded_by' => $user->id,
            ]);
        }

        return $tooth;
    }

    /**
     * @param  array<string, mixed>  $adjustment
     */
    public function addProcedure(
        Visit $visit,
        int $procedureId,
        ?string $toothNumber,
        int $quantity,
        ?string $icd9Code = null,
        ?User $actor = null,
        array $adjustment = [],
    ): ProcedureRecord {
        $pricing = [
            'price_at_time' => $this->tariffs->priceFor($visit, $procedureId),
            'tariff_price_at_time' => $this->tariffs->priceFor($visit, $procedureId),
            'doctor_fee_at_time' => '0.00',
            'bhp_amount_at_time' => '0.00',
            'clinic_fee_at_time' => $this->tariffs->priceFor($visit, $procedureId),
            'adjustment_type' => 'normal',
            'waiver_status' => 'none',
            'include_waived_in_sharing_fee' => false,
        ];

        $resolved = null;
        $quote = null;

        if ($actor) {
            $waiver = app(DoctorFeeWaiverService::class);
            $attrs = $waiver->attributesForCreate($visit, $procedureId, $adjustment, $actor);
            $resolved = $attrs['_resolved'];
            $quote = $attrs['_quote'];
            unset($attrs['_resolved'], $attrs['_quote']);
            $pricing = $attrs;
        } else {
            $components = $this->tariffs->componentsFor($visit, $procedureId);
            $pricing['tariff_price_at_time'] = $components['price'];
            $pricing['doctor_fee_at_time'] = $components['doctor_fee'];
            $pricing['bhp_amount_at_time'] = $components['bhp_amount'];
            $pricing['clinic_fee_at_time'] = $components['clinic_fee'];
            $pricing['price_at_time'] = $components['price'];
        }

        $record = ProcedureRecord::query()->create(array_merge($pricing, [
            'visit_id' => $visit->id,
            'procedure_id' => $procedureId,
            'tooth_number' => $toothNumber,
            'icd9_code' => $icd9Code,
            'quantity' => $quantity,
            'billing_status' => BillingStatus::Unbilled,
        ]));

        if ($actor && $resolved && $quote) {
            app(DoctorFeeWaiverService::class)->recordLog($visit, $record, $resolved, $quote, $actor);
        }

        return $record;
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
