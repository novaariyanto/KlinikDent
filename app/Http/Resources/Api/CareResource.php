<?php

namespace App\Http\Resources\Api;

use App\Models\ClinicalDocument;
use App\Models\DentalLabOrder;
use App\Models\Diagnosis;
use App\Models\MedicalLetter;
use App\Models\MedicalRecord;
use App\Models\OdontogramTooth;
use App\Models\Prescription;
use App\Models\PrescriptionCompoundItem;
use App\Models\PrescriptionItem;
use App\Models\ProcedureRecord;
use App\Models\Referral;
use App\Models\Visit;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareResource extends JsonResource
{
    /**
     * Kunci yang tidak boleh ikut ke klien (token monitor, rahasia integrasi, artefak tanda tangan).
     *
     * @var list<string>
     */
    private const HIDDEN_KEYS = [
        'queue_monitor_token',
        'client_secret',
        'secret_key',
        'user_key',
        'access_token',
        'refresh_token',
        'password',
        'remember_token',
        'verification_code',
        'signature_path',
        'signed_payload',
        'certificate_sn',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Visit $visit */
        $visit = $this->resource;

        $visit->load([
            'patient',
            'queue',
            'doctor.doctorProfile',
            'room',
            'payer',
            'branch',
            'diagnoses',
            'procedureRecords.procedure',
            'prescriptions.items.medicine',
            'prescriptions.items.ingredients.medicine',
            'prescriptions.doctor',
            'referrals',
            'invoices',
            'medicalRecord',
        ]);

        $record = $visit->medicalRecord;
        $teeth = $visit->patient
            ? $visit->patient->odontogramTeeth()->orderBy('tooth_number')->get()
            : collect();
        $letters = MedicalLetter::query()
            ->where('visit_id', $visit->id)
            ->latest('issued_at')
            ->latest('id')
            ->get();
        $documents = ClinicalDocument::query()
            ->where('visit_id', $visit->id)
            ->latest('id')
            ->get();
        $labOrders = DentalLabOrder::query()
            ->where('visit_id', $visit->id)
            ->latest('id')
            ->get();

        return [
            'patient' => $this->patient($visit),
            'visit' => $this->visit($visit),
            'medical_record' => $this->record($record),
            'examination' => [
                'initial_examination' => $record?->initial_examination,
                'dental_exam' => $this->publicData($record?->dental_exam ?? []),
                'systemic_history' => $this->publicData($record?->systemic_history ?? []),
            ],
            'vital_signs' => $this->publicData($record?->vital_signs ?? []),
            'anamnesis' => $record?->anamnesis,
            'teeth' => $teeth->map(fn (OdontogramTooth $tooth) => [
                'id' => (int) $tooth->id,
                'tooth_number' => $tooth->tooth_number,
                'status' => $this->enumValue($tooth->status),
                'surfaces' => $tooth->surfaces ?? [],
                'conditions' => $tooth->conditions ?? [],
                'notes' => $tooth->notes,
            ])->values()->all(),
            'diagnoses' => $visit->diagnoses->map(fn (Diagnosis $diagnosis) => [
                'id' => (int) $diagnosis->id,
                'kind' => $this->enumValue($diagnosis->kind),
                'rank' => $this->enumValue($diagnosis->rank),
                'tooth_number' => $diagnosis->tooth_number,
                'code' => $diagnosis->code,
                'description' => $diagnosis->description,
                'clinical_note' => $diagnosis->clinical_note,
            ])->values()->all(),
            'procedures' => $visit->procedureRecords->map(fn (ProcedureRecord $item) => [
                'id' => (int) $item->id,
                'procedure_id' => $item->procedure_id ? (int) $item->procedure_id : null,
                'name' => $item->procedure?->name,
                'tooth_number' => $item->tooth_number,
                'icd9_code' => $item->icd9_code,
                'quantity' => (int) $item->quantity,
                'price_at_time' => $item->price_at_time,
                'adjustment_type' => $this->enumValue($item->adjustment_type),
                'billing_status' => $this->enumValue($item->billing_status),
            ])->values()->all(),
            'prescriptions' => $visit->prescriptions->map(fn (Prescription $prescription) => $this->prescription($prescription))->values()->all(),
            'referrals' => $visit->referrals->map(fn (Referral $referral) => [
                'id' => (int) $referral->id,
                'referred_to' => $referral->referred_to,
                'reason' => $referral->reason,
                'notes' => $referral->notes,
            ])->values()->all(),
            'letters' => [
                'medical_letters' => $letters->map(fn (MedicalLetter $letter) => [
                    'id' => (int) $letter->id,
                    'type' => $this->enumValue($letter->type),
                    'number' => $letter->number,
                    'status' => $this->enumValue($letter->status),
                    'purpose' => $letter->purpose,
                    'body' => $letter->body,
                    'payload' => $this->publicData($letter->payload ?? []),
                    'issued_at' => $this->dateTime($letter->issued_at),
                    'cancelled_at' => $this->dateTime($letter->cancelled_at),
                ])->values()->all(),
                'documents' => $documents->map(fn (ClinicalDocument $document) => [
                    'id' => (int) $document->id,
                    'type' => $this->enumValue($document->type),
                    'number' => $document->number,
                    'status' => $this->enumValue($document->status),
                    'patient_initials' => $document->patient_initials,
                    'payload' => $this->publicData($document->payload ?? []),
                    'signed_at' => $this->dateTime($document->signed_at),
                ])->values()->all(),
            ],
            'lab_orders' => $labOrders->map(fn (DentalLabOrder $order) => [
                'id' => (int) $order->id,
                'work_type' => $order->work_type,
                'tooth_numbers' => $order->tooth_numbers,
                'shade' => $order->shade,
                'vendor' => $order->vendor,
                'due_date' => $this->date($order->due_date),
                'status' => $this->enumValue($order->status),
                'notes' => $order->notes,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function patient(Visit $visit): ?array
    {
        $patient = $visit->patient;

        if (! $patient) {
            return null;
        }

        return [
            'id' => (int) $patient->id,
            'medical_record_number' => $patient->medical_record_number,
            'name' => $patient->name,
            'nik' => $patient->nik,
            'gender' => $this->enumValue($patient->gender),
            'dob' => $this->date($patient->dob),
            'age_label' => $patient->ageLabel(),
            'phone' => $patient->phone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function visit(Visit $visit): array
    {
        return [
            'id' => (int) $visit->id,
            'visit_date' => $this->date($visit->visit_date),
            'status' => $this->enumValue($visit->status),
            'patient_id' => $visit->patient_id ? (int) $visit->patient_id : null,
            'doctor_id' => $visit->doctor_id ? (int) $visit->doctor_id : null,
            'room_id' => $visit->room_id ? (int) $visit->room_id : null,
            'payer_id' => $visit->payer_id ? (int) $visit->payer_id : null,
            'branch_id' => $visit->branch_id ? (int) $visit->branch_id : null,
            'queue_number' => $visit->queue?->displayNumber(),
            'called_at' => $this->dateTime($visit->queue?->called_at),
            'queue_status' => $this->enumValue($visit->queue?->status),
            'doctor' => $visit->doctor ? [
                'id' => (int) $visit->doctor->id,
                'name' => $visit->doctor->name,
            ] : null,
            'room' => $visit->room ? [
                'id' => (int) $visit->room->id,
                'name' => $visit->room->name,
            ] : null,
            'payer' => $visit->payer ? [
                'id' => (int) $visit->payer->id,
                'name' => $visit->payer->name,
            ] : null,
            'branch' => $visit->branch ? [
                'id' => (int) $visit->branch->id,
                'name' => $visit->branch->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function record(?MedicalRecord $record): ?array
    {
        if (! $record) {
            return null;
        }

        return [
            'id' => (int) $record->id,
            'assessment_kind' => $this->enumValue($record->assessment_kind),
            'assessment_status' => $this->enumValue($record->assessment_status),
            'chief_complaint' => $record->chief_complaint,
            'anamnesis' => $record->anamnesis,
            'clinical_notes' => $record->clinical_notes,
            'initial_examination' => $record->initial_examination,
            'vital_signs' => $this->publicData($record->vital_signs ?? []),
            'care_notes' => $record->care_notes,
            'dental_exam' => $this->publicData($record->dental_exam ?? []),
            'systemic_history' => $this->publicData($record->systemic_history ?? []),
            'plan_instructions' => $this->publicData($record->plan_instructions ?? []),
            'follow_up_review' => $this->publicData($record->follow_up_review ?? []),
            'control_date' => $this->date($record->control_date),
            'followup_date' => $this->date($record->followup_date),
            'finalized_at' => $this->dateTime($record->finalized_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function prescription(Prescription $prescription): array
    {
        return [
            'id' => (int) $prescription->id,
            'status' => $this->enumValue($prescription->status),
            'doctor' => $prescription->doctor ? [
                'id' => (int) $prescription->doctor->id,
                'name' => $prescription->doctor->name,
            ] : null,
            'items' => $prescription->items->map(fn (PrescriptionItem $item) => [
                'id' => (int) $item->id,
                'kind' => $this->enumValue($item->kind),
                'medication_type' => $this->enumValue($item->medication_type),
                'medicine_id' => $item->medicine_id ? (int) $item->medicine_id : null,
                'medicine' => $item->medicine?->name,
                'dosage' => $item->dosage,
                'frequency' => $item->frequency,
                'duration' => $item->duration,
                'course' => $item->course,
                'quantity' => (int) $item->quantity,
                'ingredients' => $item->ingredients->map(fn (PrescriptionCompoundItem $ingredient) => [
                    'id' => (int) $ingredient->id,
                    'medicine_id' => $ingredient->medicine_id ? (int) $ingredient->medicine_id : null,
                    'name' => $ingredient->name ?: $ingredient->medicine?->name,
                    'unit' => $ingredient->unit,
                    'role' => $ingredient->role,
                    'quantity' => $ingredient->quantity,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    protected function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    protected function date(?DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d');
    }

    protected function dateTime(?DateTimeInterface $value): ?string
    {
        return $value?->format('c');
    }

    protected function publicData(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $clean = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, self::HIDDEN_KEYS, true)) {
                continue;
            }

            $clean[$key] = is_array($item) ? $this->publicData($item) : $item;
        }

        return $clean;
    }
}
