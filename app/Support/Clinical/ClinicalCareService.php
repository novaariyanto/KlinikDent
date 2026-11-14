<?php

namespace App\Support\Clinical;

use App\Enums\AssessmentKind;
use App\Enums\AssessmentStatus;
use App\Enums\BillingStatus;
use App\Enums\ClinicalDocumentType;
use App\Enums\LabOrderStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\QueueStatus;
use App\Enums\ToothStatus;
use App\Enums\VisitStatus;
use App\Models\ClinicalDocument;
use App\Models\DentalLabOrder;
use App\Models\Diagnosis;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAmendment;
use App\Models\OdontogramLog;
use App\Models\OdontogramSnapshot;
use App\Models\OdontogramTooth;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ProcedureRecord;
use App\Models\Referral;
use App\Models\User;
use App\Models\Visit;
use App\Support\Billing\BillingService;
use App\Support\Billing\DoctorFeeWaiverService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRecord(Visit $visit, array $data): MedicalRecord
    {
        $record = $this->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);

        if (isset($data['plan_instructions'])) {
            $items = array_values(array_filter(
                (array) ($data['plan_instructions']['items'] ?? []),
                fn ($item) => is_string($item) && $item !== '',
            ));
            $extra = trim((string) ($data['plan_instructions']['extra'] ?? ''));
            $prognosis = trim((string) ($data['plan_instructions']['prognosis'] ?? ''));
            $goal = trim((string) ($data['plan_instructions']['goal'] ?? ''));
            $planStatus = (string) ($data['plan_instructions']['status'] ?? '');
            $statuses = CareExamOptions::carePlanStatuses();
            $labels = CareExamOptions::instructions();
            $lines = collect($items)
                ->map(fn ($key) => $labels[$key] ?? $key)
                ->filter()
                ->values();

            if ($extra !== '') {
                $lines->push($extra);
            }

            $data['clinical_notes'] = $lines->implode("\n");
            $data['plan_instructions'] = [
                'items' => $items,
                'extra' => $extra !== '' ? $extra : null,
                'prognosis' => $prognosis !== '' ? $prognosis : null,
                'goal' => $goal !== '' ? $goal : null,
                'status' => array_key_exists($planStatus, $statuses) ? $planStatus : null,
            ];
        }

        $before = activity_snapshot($record);
        $record->update($data);

        activity_audit('updated', $record, $before, 'Updated medical record', 'medical_records');

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateExamination(Visit $visit, array $data): MedicalRecord
    {
        $record = $this->ensureRecord($visit);
        $before = activity_snapshot($record);
        $record->update($data);

        activity_audit('updated', $record, $before, 'Updated initial examination', 'medical_records');

        return $record;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateVitals(Visit $visit, array $validated): MedicalRecord
    {
        $record = $this->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);

        [$sys, $dia] = $this->bloodPressureParts(
            $validated['blood_pressure_sys'] ?? null,
            $validated['blood_pressure_dia'] ?? null,
            $validated['blood_pressure'] ?? null,
        );
        $vitals = [
            'blood_pressure' => ($sys !== '' || $dia !== '') ? trim($sys.'/'.$dia, '/') : null,
            'blood_pressure_sys' => $sys !== '' ? $sys : null,
            'blood_pressure_dia' => $dia !== '' ? $dia : null,
            'pulse' => $validated['pulse'] ?? null,
            'temperature' => $validated['temperature'] ?? null,
            'respiration' => $validated['respiration'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'height' => $validated['height'] ?? null,
            'general_condition' => filled($validated['general_condition'] ?? null) ? trim((string) $validated['general_condition']) : data_get($record->vital_signs, 'general_condition'),
            'consciousness' => filled($validated['consciousness'] ?? null) ? trim((string) $validated['consciousness']) : data_get($record->vital_signs, 'consciousness'),
        ];
        $history = $record->systemic_history ?? [];
        $history['blood_pressure_sys'] = $vitals['blood_pressure_sys'];
        $history['blood_pressure_dia'] = $vitals['blood_pressure_dia'];

        $before = activity_snapshot($record);
        $record->update([
            'vital_signs' => $vitals,
            'systemic_history' => $history,
        ]);

        activity_audit('updated', $record, $before, 'Updated vital signs', 'medical_records');

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAnamnesis(Visit $visit, array $data): MedicalRecord
    {
        $record = $this->ensureRecord($visit);
        $before = activity_snapshot($record);
        $record->update($data);

        activity_audit('updated', $record, $before, 'Updated anamnesis', 'medical_records');

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFollowUp(Visit $visit, array $data, User $user): MedicalRecord
    {
        $record = $this->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);
        abort_unless($record->assessment_kind === AssessmentKind::FollowUp, 422);
        app(CareEpisodeService::class)->assertFollowUpAllowed($visit);

        $review = is_array($record->follow_up_review) ? $record->follow_up_review : [];
        foreach (['adverse_effect', 'plan_decision'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = trim((string) ($data[$key] ?? ''));
            $review[$key] = $value !== '' ? $value : null;
        }
        if (array_key_exists('indicators', $data)) {
            $review['indicators'] = array_filter((array) $data['indicators'], fn ($value) => filled($value));
        }
        if (array_key_exists('diagnosis_outcomes', $data)) {
            $review['diagnosis_outcomes'] = array_filter((array) $data['diagnosis_outcomes'], fn ($value) => filled($value));
        }
        if (array_key_exists('procedure_evals', $data)) {
            $existingEvals = is_array($review['procedure_evals'] ?? null) ? $review['procedure_evals'] : [];
            foreach ((array) $data['procedure_evals'] as $id => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $prior = is_array($existingEvals[$id] ?? null) ? $existingEvals[$id] : [];
                foreach (['result', 'response', 'success', 'complication', 'next'] as $key) {
                    if (! array_key_exists($key, $row)) {
                        continue;
                    }
                    $value = trim((string) $row[$key]);
                    if ($value === '') {
                        unset($prior[$key]);
                    } else {
                        $prior[$key] = $value;
                    }
                }
                if ($prior === []) {
                    unset($existingEvals[$id]);
                } else {
                    $existingEvals[$id] = $prior;
                }
            }
            $review['procedure_evals'] = $existingEvals;
        }

        $before = activity_snapshot($record);
        $record->follow_up_review = $review;
        if (array_key_exists('control_date', $data)) {
            $record->control_date = $data['control_date'] ?: null;
        }
        $record->save();
        activity_audit('updated', $record, $before, 'Updated follow-up assessment', 'medical_records', [], $user);

        if (($review['plan_decision'] ?? null) === 'end' && $user) {
            $episode = $visit->episode()->first();
            if ($episode) {
                app(CareEpisodeService::class)->close($episode, $user);
            }
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateNotes(Visit $visit, array $data): MedicalRecord
    {
        $record = $this->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);
        $before = activity_snapshot($record);
        $record->update($data);

        activity_audit('updated', $record, $before, 'Updated care notes', 'medical_records');

        return $record;
    }

    public function destroyTooth(Visit $visit, string $toothNumber, User $user): OdontogramTooth
    {
        abort_unless(FdiTeeth::isValid($toothNumber), 404);

        $tooth = $this->updateTooth(
            $visit,
            $user,
            $toothNumber,
            ToothStatus::Healthy,
            null,
            [],
        );

        activity_log('updated', $visit, ['tooth_number' => $toothNumber, 'status' => ToothStatus::Healthy->value], 'Removed odontogram finding '.$toothNumber, 'odontogram');

        return $tooth;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDentalExam(Visit $visit, array $data): MedicalRecord
    {
        $record = $this->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);

        $data = $this->normalizeDentalExam($data);
        $record->update(['dental_exam' => $data]);

        activity_log('updated', $record, $data, 'Updated dental examination', 'medical_records');

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSystemicHistory(Visit $visit, array $data, bool $syncBloodPressure): MedicalRecord
    {
        $record = $this->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);

        $data = $this->normalizeSystemicHistory($data);
        [$sys, $dia] = $this->bloodPressureParts($data['blood_pressure_sys'] ?? null, $data['blood_pressure_dia'] ?? null, null);
        $data['blood_pressure_sys'] = $sys !== '' ? $sys : null;
        $data['blood_pressure_dia'] = $dia !== '' ? $dia : null;

        $payload = ['systemic_history' => $data];

        if ($syncBloodPressure) {
            $vitals = $record->vital_signs ?? [];
            $vitals['blood_pressure_sys'] = $data['blood_pressure_sys'];
            $vitals['blood_pressure_dia'] = $data['blood_pressure_dia'];
            $vitals['blood_pressure'] = ($sys !== '' || $dia !== '') ? trim($sys.'/'.$dia, '/') : null;
            $payload['vital_signs'] = $vitals;
        }

        $record->update($payload);

        activity_log('updated', $record, $data, 'Updated systemic history', 'medical_records');

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeDiagnosis(Visit $visit, array $data, bool $single): Diagnosis
    {
        $diagnosis = $visit->diagnoses()->create($data);
        if ($single) {
            $visit->diagnoses()->where('id', '!=', $diagnosis->id)->delete();
        }

        activity_log('created', $diagnosis, $data, 'Added diagnosis', 'diagnoses');

        return $diagnosis;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDiagnosis(Visit $visit, Diagnosis $diagnosis, array $data, bool $single): Diagnosis
    {
        abort_unless((int) $diagnosis->visit_id === (int) $visit->id, 404);

        $before = activity_snapshot($diagnosis);
        $diagnosis->update($data);
        if ($single) {
            $visit->diagnoses()->where('id', '!=', $diagnosis->id)->delete();
        }

        activity_audit('updated', $diagnosis, $before, 'Updated diagnosis', 'diagnoses');

        return $diagnosis;
    }

    public function destroyDiagnosis(Visit $visit, Diagnosis $diagnosis): void
    {
        abort_unless((int) $diagnosis->visit_id === (int) $visit->id, 404);
        abort_unless($visit->status !== VisitStatus::Cancelled, 403);

        activity_log('deleted', $diagnosis, ['code' => $diagnosis->code], 'Deleted diagnosis', 'diagnoses');
        $diagnosis->delete();

        $record = $visit->medicalRecord()->first();
        if ($record && $record->assessment_kind === AssessmentKind::FollowUp && ! $visit->diagnoses()->exists()) {
            $review = is_array($record->follow_up_review) ? $record->follow_up_review : [];
            $review['diagnoses_seeded'] = true;
            $record->follow_up_review = $review;
            $record->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeProcedure(Visit $visit, array $data, User $user): ProcedureRecord
    {
        $record = $this->addProcedure(
            $visit,
            (int) $data['procedure_id'],
            $data['tooth_number'] ?? null,
            (int) $data['quantity'],
            $data['icd9_code'] ?? null,
            $user,
            [
                'doctor_fee' => $data['doctor_fee'] ?? null,
                'waive_doctor_fee' => $data['waive_doctor_fee'] ?? false,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'waiver_reason' => $data['waiver_reason'] ?? null,
            ],
        );

        activity_log('created', $record, [
            'procedure_id' => $record->procedure_id,
            'price_at_time' => $record->price_at_time,
            'adjustment_type' => $record->adjustment_type?->value,
        ], 'Added procedure record', 'procedure_records');

        return $record;
    }

    public function procedureAddedMessage(ProcedureRecord $record): string
    {
        if ($record->waiver_status?->value === 'pending') {
            return 'Tindakan ditambahkan. Pembebasan jasa dokter menunggu persetujuan. Tarif pasien masih normal.';
        }

        return 'Tindakan ditambahkan. Tarif pasien Rp '.number_format((float) $record->price_at_time, 0, ',', '.').'.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProcedure(Visit $visit, ProcedureRecord $procedureRecord, array $data, User $user, bool $hasAdjustmentForm): ProcedureRecord
    {
        $before = activity_snapshot($procedureRecord);
        $procedureRecord->update([
            'icd9_code' => $data['icd9_code'] ?? $procedureRecord->icd9_code,
        ]);

        if ($procedureRecord->isUnbilled() && $hasAdjustmentForm) {
            app(DoctorFeeWaiverService::class)->updateUnbilled($visit, $procedureRecord, [
                'doctor_fee' => $data['doctor_fee'] ?? null,
                'waive_doctor_fee' => $data['waive_doctor_fee'] ?? false,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'waiver_reason' => $data['waiver_reason'] ?? null,
            ], $user);
        }

        $procedureRecord = $procedureRecord->fresh() ?? $procedureRecord;
        activity_audit('updated', $procedureRecord, $before, 'Updated procedure record', 'procedure_records');

        return $procedureRecord;
    }

    public function destroyProcedure(Visit $visit, ProcedureRecord $procedureRecord): void
    {
        abort_unless((int) $procedureRecord->visit_id === (int) $visit->id, 404);
        abort_unless($procedureRecord->isUnbilled(), 403);

        activity_log('deleted', $procedureRecord, ['procedure_id' => $procedureRecord->procedure_id], 'Deleted procedure record', 'procedure_records');
        $procedureRecord->delete();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function quoteProcedureTariff(Visit $visit, User $user, array $input): array
    {
        $procedureId = (int) ($input['procedure_id'] ?? 0);
        abort_if($procedureId < 1, 422, 'Tindakan belum dipilih.');

        $filled = fn (string $key): bool => array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '';

        $quote = app(DoctorFeeWaiverService::class)->quote($visit, $procedureId, [
            'doctor_fee' => $filled('doctor_fee') ? $input['doctor_fee'] : null,
            'waive_doctor_fee' => filter_var($input['waive_doctor_fee'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'discount_type' => $input['discount_type'] ?? null,
            'discount_value' => $input['discount_value'] ?? null,
            'waiver_reason' => $input['waiver_reason'] ?? null,
        ], $user);

        $quantity = max(1, (int) ($input['quantity'] ?? 0) ?: 1);
        $quote['quantity'] = $quantity;
        $quote['charged_line_total'] = bcmul($quote['charged_unit_price'], (string) $quantity, 2);
        $quote['tariff_line_total'] = bcmul($quote['tariff_price'], (string) $quantity, 2);

        return $quote;
    }

    public function approveProcedureWaiver(Visit $visit, ProcedureRecord $procedureRecord, User $user): ProcedureRecord
    {
        abort_unless((int) $procedureRecord->visit_id === (int) $visit->id, 404);

        $log = $procedureRecord->waiverLogs()->where('status', 'pending')->latest('id')->first();
        abort_unless($log, 404);

        Gate::forUser($user)->authorize('approve', $log);
        app(DoctorFeeWaiverService::class)->approve($log, $user);

        return $procedureRecord->fresh() ?? $procedureRecord;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storePrescriptionItem(Visit $visit, User $user, array $data): PrescriptionItem
    {
        $prescription = $this->draftPrescription($visit, $user);
        $item = app(PrescriptionItemWriter::class)->create($prescription, $data);

        activity_log('created', $item, $data, 'Added prescription item', 'prescriptions');

        return $item;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePrescriptionItem(PrescriptionItem $prescriptionItem, array $data): PrescriptionItem
    {
        $logged = $data;
        $ingredients = $data['ingredients'] ?? null;
        unset($data['ingredients'], $data['composition_confirmed']);
        $prescriptionItem->update($data);

        if ($prescriptionItem->isCompound() && is_array($ingredients)) {
            app(PrescriptionItemWriter::class)->replaceIngredients($prescriptionItem, $ingredients);
        }

        activity_log('updated', $prescriptionItem, $logged, 'Updated prescription item', 'prescriptions');

        return $prescriptionItem;
    }

    public function destroyPrescriptionItem(Visit $visit, PrescriptionItem $prescriptionItem): void
    {
        $prescription = $prescriptionItem->prescription;
        abort_unless($prescription && (int) $prescription->visit_id === (int) $visit->id, 404);
        abort_unless($prescription->isDraft(), 403);

        activity_log('deleted', $prescriptionItem, ['medicine_id' => $prescriptionItem->medicine_id], 'Deleted prescription item', 'prescriptions');
        $prescriptionItem->delete();

        if (! $prescription->items()->exists()) {
            $prescription->delete();
        }
    }

    public function sendPrescription(Visit $visit, Prescription $prescription): string
    {
        abort_unless((int) $prescription->visit_id === (int) $visit->id, 404);
        abort_unless($prescription->isDraft(), 403);
        abort_unless($prescription->items()->exists(), 422);

        $issues = PrescriptionSatuSehatReadiness::forPrescription($prescription);
        $blocking = PrescriptionSatuSehatReadiness::blockingMessages($issues);
        if ($blocking !== []) {
            throw ValidationException::withMessages(['prescription' => $blocking]);
        }

        $prescription->update(['status' => PrescriptionStatus::Sent]);

        activity_log('updated', $prescription, ['status' => PrescriptionStatus::Sent->value], 'Sent prescription to pharmacy', 'prescriptions');

        $message = 'Resep dikirim ke farmasi.';
        $warnings = PrescriptionSatuSehatReadiness::warningMessages($issues);
        if ($warnings !== []) {
            $message .= ' Catatan SATUSEHAT: '.implode(' ', $warnings);
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeReferral(Visit $visit, array $data): Referral
    {
        $referral = $visit->referrals()->create($data);

        activity_log('created', $referral, $data, 'Added referral', 'referrals');

        return $referral;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateReferral(Visit $visit, Referral $referral, array $data): Referral
    {
        abort_unless((int) $referral->visit_id === (int) $visit->id, 404);

        $referral->update($data);

        activity_log('updated', $referral, $data, 'Updated referral', 'referrals');

        return $referral;
    }

    public function destroyReferral(Visit $visit, Referral $referral): void
    {
        abort_unless((int) $referral->visit_id === (int) $visit->id, 404);

        activity_log('deleted', $referral, ['referred_to' => $referral->referred_to], 'Deleted referral', 'referrals');
        $referral->delete();
    }

    public function finalizeRecord(Visit $visit, User $user): MedicalRecord
    {
        $record = $this->ensureRecord($visit);
        app(MedicalRecordLock::class)->finalize($record, $user);

        return $record;
    }

    /**
     * @return array{record: MedicalRecord, already: bool}
     */
    public function completeAssessment(Visit $visit, User $user): array
    {
        $record = $this->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);

        if ($record->assessment_kind === AssessmentKind::FollowUp) {
            app(CareEpisodeService::class)->assertFollowUpAllowed($visit);
        }

        if ($record->assessment_status === AssessmentStatus::Completed) {
            return ['record' => $record, 'already' => true];
        }

        $before = activity_snapshot($record);
        $record->update([
            'assessment_status' => AssessmentStatus::Completed,
            'assessment_completed_at' => now(),
            'assessment_completed_by' => $user->id,
        ]);
        activity_audit('updated', $record, $before, 'Completed clinical assessment', 'medical_records', [], $user);

        return ['record' => $record, 'already' => false];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function storeAmendment(Visit $visit, User $user, array $input): MedicalRecordAmendment
    {
        $record = $visit->medicalRecord;
        abort_unless($record, 404);

        $data = validator($input, ['body' => ['required', 'string', 'max:5000']])->validate();

        return app(MedicalRecordLock::class)->amend($record, $user, $data['body']);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function storeSickLetter(Visit $visit, User $user, array $input): ClinicalDocument
    {
        $data = validator($input, [
            'rest_from' => ['required', 'date'],
            'rest_days' => ['required', 'integer', 'min:1', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $visit->loadMissing(['patient', 'doctor.doctorProfile', 'branch']);
        $number = $this->letterNumber('SKT', (int) $visit->id, $data['rest_from']);

        return app(ClinicalDocumentService::class)->issue(
            $visit,
            $user,
            ClinicalDocumentType::SickLetter,
            $number.'-'.now()->format('His'),
            $data,
        );
    }

    /**
     * @return array{visit: Visit, invoice: ?\App\Models\Invoice, message: string}
     */
    public function complete(Visit $visit, User $actor): array
    {
        abort_unless($visit->status->isOpen(), 403);
        abort_unless($actor->isDentist() && $actor->can('queue.manage'), 403);

        if ($actor->can('medical_record.update')) {
            $pending = $this->ensureRecord($visit);
            if ($pending->assessment_status !== AssessmentStatus::Completed) {
                app(CareEpisodeService::class)->assertFollowUpAllowed($visit);
            }
        }

        $draftPrescriptions = $visit->prescriptions()
            ->where('status', PrescriptionStatus::Draft)
            ->with(['items.medicine', 'items.ingredients.medicine'])
            ->get()
            ->filter(fn (Prescription $prescription) => $prescription->items->isNotEmpty())
            ->values();

        $ssIssues = [];
        foreach ($draftPrescriptions as $prescription) {
            foreach (PrescriptionSatuSehatReadiness::forPrescription($prescription) as $issue) {
                $ssIssues[] = $issue;
            }
        }
        $blocking = PrescriptionSatuSehatReadiness::blockingMessages($ssIssues);
        if ($blocking !== []) {
            throw ValidationException::withMessages(['prescription' => $blocking]);
        }
        $ssWarnings = PrescriptionSatuSehatReadiness::warningMessages($ssIssues);

        $queue = $visit->queue;

        if ($queue) {
            Gate::forUser($actor)->authorize('complete', $queue);
            $queue->update(['status' => QueueStatus::Done]);
        } else {
            abort_unless($actor->can('queue.manage'), 403);
        }

        $sentPrescriptions = 0;
        foreach ($draftPrescriptions as $prescription) {
            $prescription->update(['status' => PrescriptionStatus::Sent]);
            activity_log('updated', $prescription, ['status' => PrescriptionStatus::Sent->value], 'Sent prescription to pharmacy', 'prescriptions');
            $sentPrescriptions++;
        }

        $before = activity_snapshot($visit);
        $visit->update(['status' => VisitStatus::Done]);

        $invoice = app(BillingService::class)->generateForVisit($visit->fresh(), $actor);

        activity_audit('updated', $visit, $before, 'Completed clinical visit', 'visits', [
            'status' => VisitStatus::Done->value,
        ]);

        $finalizedNow = false;
        $assessmentNow = false;
        if ($actor->can('medical_record.update')) {
            $record = $this->ensureRecord($visit);
            $lock = app(MedicalRecordLock::class);
            if ($record->assessment_status !== AssessmentStatus::Completed) {
                $beforeAssessment = activity_snapshot($record);
                $record->update([
                    'assessment_status' => AssessmentStatus::Completed,
                    'assessment_completed_at' => now(),
                    'assessment_completed_by' => $actor->id,
                ]);
                activity_audit('updated', $record, $beforeAssessment, 'Completed clinical assessment', 'medical_records', [], $actor);
                $assessmentNow = true;
            }
            if (! $lock->isFinal($record)) {
                $lock->finalize($record, $actor);
                $finalizedNow = true;
            }
        }

        $message = 'Pelayanan kunjungan diselesaikan.';
        if ($assessmentNow && $finalizedNow) {
            $message .= ' Asesmen dan rekam medis difinalisasi. Koreksi isi dibuka 2×24 jam.';
        } elseif ($finalizedNow) {
            $message .= ' Rekam medis difinalisasi. Koreksi isi dibuka 2×24 jam.';
        } elseif ($assessmentNow) {
            $message .= ' Asesmen difinalisasi.';
        }
        if ($invoice) {
            $message .= ' Tagihan '.$invoice->number.' dibuat.';
        }
        if ($sentPrescriptions > 0) {
            $message .= ' '.$sentPrescriptions.' resep dikirim ke farmasi. Stok belum dipotong.';
            if ($ssWarnings !== []) {
                $message .= ' Catatan SATUSEHAT: '.implode(' ', array_slice($ssWarnings, 0, 3));
                if (count($ssWarnings) > 3) {
                    $message .= ' (+'.(count($ssWarnings) - 3).' lainnya)';
                }
            }
        }

        return [
            'visit' => $visit->fresh() ?? $visit,
            'invoice' => $invoice,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function searchDiagnoses(string $term, int $page): array
    {
        return IcdCatalog::search($term, 30, max(1, $page));
    }

    /**
     * @return array<string, mixed>
     */
    public function searchIcd9(string $term, int $page): array
    {
        return Icd9Catalog::search($term, 30, max(1, $page));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function storeLabOrder(Visit $visit, User $user, array $input): DentalLabOrder
    {
        $data = validator($input, $this->labOrderRules())->validate();

        $order = DentalLabOrder::query()->create([
            ...$data,
            'tenant_id' => $visit->tenant_id,
            'visit_id' => $visit->id,
            'status' => LabOrderStatus::Ordered,
            'created_by' => $user->id,
        ]);

        activity_log('created', $order, $data, 'Added dental lab order', 'dental_lab');

        return $order;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateLabOrder(Visit $visit, DentalLabOrder $order, array $input): DentalLabOrder
    {
        abort_unless((int) $order->visit_id === (int) $visit->id, 404);

        $data = validator($input, [
            ...$this->labOrderRules(),
            'status' => ['required', Rule::enum(LabOrderStatus::class)],
        ])->validate();

        $order->update($data);

        activity_log('updated', $order, $data, 'Updated dental lab order', 'dental_lab');

        return $order->refresh();
    }

    public function destroyLabOrder(Visit $visit, DentalLabOrder $order): int
    {
        abort_unless((int) $order->visit_id === (int) $visit->id, 404);

        activity_log('deleted', $order, ['work_type' => $order->work_type], 'Deleted dental lab order', 'dental_lab');
        $orderId = (int) $order->id;
        $order->delete();

        return $orderId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeSystemicHistory(array $data): array
    {
        $data['none'] = filter_var($data['none'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $conditions = array_values(array_filter(
            (array) ($data['conditions'] ?? []),
            fn ($value) => is_string($value) && $value !== '',
        ));
        $allergyStatus = (string) ($data['allergy_status'] ?? 'not_asked');
        $medicationStatus = (string) ($data['medication_status'] ?? 'not_asked');

        if ($allergyStatus !== 'present') {
            $conditions = array_values(array_filter($conditions, fn ($item) => $item !== 'allergy'));
        } elseif (! in_array('allergy', $conditions, true)) {
            $conditions[] = 'allergy';
        }

        if ($medicationStatus !== 'present' && ! filled($data['medication_detail'] ?? null)) {
            $conditions = array_values(array_filter($conditions, fn ($item) => $item !== 'medication'));
        } elseif ($medicationStatus === 'present' && ! in_array('medication', $conditions, true)) {
            $conditions[] = 'medication';
        }

        if ($allergyStatus === 'present' || $medicationStatus === 'present' || $conditions !== []) {
            $data['none'] = false;
            $data['conditions'] = $conditions;
        } elseif ($data['none']) {
            $data['conditions'] = [];
            $data['other_detail'] = null;
            $data['allergy_detail'] = null;
            $data['medication_detail'] = null;
        } else {
            $data['conditions'] = $conditions;
        }

        $data['allergy_status'] = $allergyStatus !== '' ? $allergyStatus : 'not_asked';
        $data['medication_status'] = $medicationStatus !== '' ? $medicationStatus : 'not_asked';

        foreach (['family_history', 'dental_history', 'drug_allergy', 'material_allergy'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            $data[$key] = $value !== '' ? $value : null;
        }

        return $data;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function bloodPressureParts(mixed $sys, mixed $dia, mixed $combined): array
    {
        $sys = trim((string) ($sys ?? ''));
        $dia = trim((string) ($dia ?? ''));

        if ($sys === '' && $dia === '' && is_string($combined) && str_contains($combined, '/')) {
            [$sys, $dia] = array_pad(explode('/', $combined, 2), 2, '');
            $sys = trim($sys);
            $dia = trim($dia);
        }

        return [$sys, $dia];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeDentalExam(array $data): array
    {
        if (($data['occlusion'] ?? null) === 'deep') {
            $data['occlusion'] = 'steep';
        }

        if (($data['extraoral'] ?? null) !== 'abnormal') {
            $data['extraoral_note'] = null;
        }

        if (($data['intraoral'] ?? null) !== 'abnormal') {
            $data['intraoral_note'] = null;
        }

        if (($data['diastema'] ?? null) !== 'present') {
            $data['diastema_elements'] = null;
        }

        if (($data['supernumerary'] ?? null) !== 'present') {
            $data['supernumerary_teeth'] = null;
        }

        if (($data['anomaly'] ?? null) !== 'present') {
            $data['anomaly_description'] = null;
        }

        foreach (['face', 'swelling', 'lymph', 'tmj', 'tongue', 'mouth_floor', 'saliva', 'periodontal'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            $data[$key] = $value !== '' ? $value : null;
        }

        $ohis = $data['ohis'] ?? null;

        if (is_array($ohis) && is_numeric($ohis['debris_index'] ?? null) && is_numeric($ohis['calculus_index'] ?? null)) {
            $di = round((float) $ohis['debris_index'], 2);
            $ci = round((float) $ohis['calculus_index'], 2);
            $total = round($di + $ci, 2);
            $data['ohis'] = [
                'debris_index' => $di,
                'calculus_index' => $ci,
                'total' => $total,
                'interpretation' => match (true) {
                    $total <= 1.2 => 'baik',
                    $total <= 3.0 => 'sedang',
                    default => 'buruk',
                },
            ];
        } else {
            unset($data['ohis']);
        }

        return $data;
    }

    protected function letterNumber(string $code, int $number, mixed $date): string
    {
        $carbon = Carbon::parse($date);
        $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];

        return sprintf('%03d/%s/%s/%s', $number, $code, $roman[(int) $carbon->format('n')], $carbon->format('Y'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function labOrderRules(): array
    {
        return [
            'work_type' => ['required', 'string', 'max:120'],
            'tooth_numbers' => ['nullable', 'string', 'max:80'],
            'shade' => ['nullable', 'string', 'max:40'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
