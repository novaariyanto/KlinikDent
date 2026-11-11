<?php

namespace App\Http\Controllers\Clinical;

use App\Enums\AssessmentKind;
use App\Enums\AssessmentStatus;
use App\Enums\DiagnosisKind;
use App\Enums\DiagnosisRank;
use App\Enums\PrescriptionStatus;
use App\Enums\QueueStatus;
use App\Enums\ToothStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\StoreDiagnosisRequest;
use App\Http\Requests\Clinical\StorePrescriptionItemRequest;
use App\Http\Requests\Clinical\StoreProcedureRecordRequest;
use App\Http\Requests\Clinical\StoreReferralRequest;
use App\Http\Requests\Clinical\UpdateAnamnesisRequest;
use App\Http\Requests\Clinical\UpdateCareNotesRequest;
use App\Http\Requests\Clinical\UpdateDentalExamRequest;
use App\Http\Requests\Clinical\UpdateExaminationRequest;
use App\Http\Requests\Clinical\UpdateFollowUpReviewRequest;
use App\Http\Requests\Clinical\UpdateMedicalRecordRequest;
use App\Http\Requests\Clinical\UpdatePrescriptionItemRequest;
use App\Http\Requests\Clinical\UpdateProcedureRecordRequest;
use App\Http\Requests\Clinical\UpdateSystemicHistoryRequest;
use App\Http\Requests\Clinical\UpdateToothRequest;
use App\Http\Requests\Clinical\UpdateVitalsRequest;
use App\Models\Diagnosis;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\OdontogramTooth;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Procedure;
use App\Models\ProcedureRecord;
use App\Models\Referral;
use App\Models\User;
use App\Models\Visit;
use App\Support\Billing\BillingService;
use App\Support\Billing\DoctorFeeWaiverService;
use Illuminate\Support\Facades\DB;
use App\Support\Clinical\CareEpisodeService;
use App\Support\Clinical\CareExamOptions;
use App\Support\Clinical\CareProgress;
use App\Support\Clinical\CareTabs;
use App\Support\Clinical\ClinicalCareService;
use App\Support\Clinical\ExaminationQueueRow;
use App\Support\Clinical\PrescriptionItemWriter;
use App\Support\Clinical\PrescriptionSatuSehatReadiness;
use App\Support\Clinical\PrintChrome;
use App\Support\Clinical\PrintSheet;
use App\Support\Clinical\FdiTeeth;
use App\Support\Clinical\IcdCatalog;
use App\Support\Clinical\Icd9Catalog;
use App\Support\Clinical\MedicalRecordLock;
use App\Support\Clinical\ToothConditions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CareController extends Controller
{
    public function __construct(
        protected ClinicalCareService $care,
        protected BillingService $billing,
    ) {}

    public function show(Request $request, Visit $visit): View
    {
        $this->authorizeCare($request, $visit);

        $user = $request->user();
        $sections = CareTabs::flow($user);
        abort_if($sections === [], 403);

        $section = CareTabs::resolveSection($user, (string) $request->query('tab', $request->query('section', '')));

        $writable = $this->canWriteAny($user) && $visit->status !== VisitStatus::Cancelled;
        $record = $writable
            ? $this->care->ensureRecord($visit)
            : $visit->medicalRecord;
        $recordLock = app(\App\Support\Clinical\MedicalRecordLock::class);
        $recordLocked = $record ? $recordLock->isLocked($record) : false;

        if ($recordLocked) {
            $writable = false;
        }

        if ($record && $user) {
            app(\App\Support\Clinical\MedicalRecordReadAudit::class)->record(
                $user,
                $record,
                'Membaca rekam medis kunjungan '.$visit->id,
            );
        }

        if ($writable && $user?->can('diagnosis.manage')) {
            $this->seedFollowUpDiagnoses($visit);
        }

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
        ]);

        $teeth = $visit->patient
            ? $visit->patient->odontogramTeeth()->get()->keyBy('tooth_number')
            : collect();

        $history = Visit::query()
            ->where('patient_id', $visit->patient_id)
            ->whereKeyNot($visit->id)
            ->with([
                'diagnoses',
                'procedureRecords.procedure',
                'prescriptions.items.medicine',
                'referrals',
                'doctor',
                'queue',
                'medicalRecord',
            ])
            ->latest('visit_date')
            ->latest('id')
            ->limit(20)
            ->get();

        $careQueues = Visit::query()
            ->visibleTo($user)
            ->today()
            ->with(['patient', 'queue'])
            ->where(function ($query) use ($visit) {
                $query->where('status', '!=', VisitStatus::Cancelled)
                    ->orWhere('visits.id', $visit->id);
            })
            ->get()
            ->sortBy(fn (Visit $item) => $item->queue?->queue_number ?? 9999)
            ->values();

        return view('clinical.care.show', [
            'visit' => $visit,
            'record' => $record,
            'sections' => $sections,
            'section' => $section,
            'progress' => CareProgress::for($visit, $record, $teeth),
            'writable' => $writable,
            'recordLocked' => $recordLocked,
            'recordFinal' => $record ? $recordLock->isFinal($record) : false,
            'correctUntil' => $record?->finalized_at?->copy()->addHours(\App\Support\Clinical\MedicalRecordLock::HOURS),
            'amendments' => $record?->amendments()->with('author')->latest('id')->get() ?? collect(),
            'labOrders' => \App\Models\DentalLabOrder::query()->where('visit_id', $visit->id)->latest('id')->get(),
            'clinicalDocuments' => \App\Models\ClinicalDocument::query()->where('visit_id', $visit->id)->latest('id')->get(),
            'medicalLetters' => \App\Models\MedicalLetter::query()->where('visit_id', $visit->id)->latest('issued_at')->latest('id')->get(),
            'teeth' => $teeth,
            'toothStatuses' => ToothStatus::options(),
            'toothStatusMeta' => ToothConditions::meta(),
            'chart' => FdiTeeth::chart(),
            'examOptions' => [
                'occlusion' => CareExamOptions::occlusion(),
                'torus' => CareExamOptions::torus(),
                'torus_mandibularis' => CareExamOptions::torusMandibularis(),
                'palate' => CareExamOptions::palate(),
                'presence' => CareExamOptions::presence(),
                'finding' => CareExamOptions::finding(),
                'gingiva' => CareExamOptions::gingiva(),
                'mucosa' => CareExamOptions::mucosa(),
                'systemic' => CareExamOptions::systemic(),
                'instructions' => CareExamOptions::instructions(),
                'allergy_statuses' => CareExamOptions::allergyStatuses(),
                'allergy_categories' => CareExamOptions::allergyCategories(),
                'allergy_types' => CareExamOptions::allergyTypes(),
                'allergy_clinical_statuses' => CareExamOptions::allergyClinicalStatuses(),
                'allergy_severities' => CareExamOptions::allergySeverities(),
                'care_plan_statuses' => CareExamOptions::carePlanStatuses(),
            ],
            'dentalExam' => $record?->dental_exam ?? [],
            'systemicHistory' => $record?->systemic_history ?? [],
            'procedures' => Procedure::query()->active()->orderBy('name')->get(),
            'procedureTariffQuoteUrl' => route('care.procedure.tariff', $visit),
            'canWaiveDoctorFee' => $user ? app(DoctorFeeWaiverService::class)->canWaive($user, $visit) : false,
            'canDiscountProcedure' => $user ? app(DoctorFeeWaiverService::class)->canDiscount($user) : false,
            'canApproveDoctorFeeWaiver' => $user ? app(DoctorFeeWaiverService::class)->canApprove($user) : false,
            'medicines' => Medicine::query()->active()->orderBy('name')->get(),
            'prescriptionTemplates' => \App\Models\PrescriptionTemplate::query()
                ->with(['author:id,name', 'items.medicine:id,name,unit', 'items.ingredients'])
                ->orderBy('name')
                ->get(),
            'history' => $history,
            'careQueues' => $careQueues,
            'assessmentContext' => app(CareEpisodeService::class)->context($visit),
        ]);
    }

    public function updateRecord(UpdateMedicalRecordRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);
        $data = $request->validated();

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

        return $this->respondCare($visit, 'soap', 'Rekam medis disimpan.');
    }

    public function updateExamination(UpdateExaminationRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        $before = activity_snapshot($record);
        $record->update($request->validated());

        activity_audit('updated', $record, $before, 'Updated initial examination', 'medical_records');

        return $this->respondCare($visit, 'pemeriksaan', 'Pemeriksaan awal disimpan.');
    }

    public function updateVitals(UpdateVitalsRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);

        $validated = $request->validated();
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

        return $this->respondCare($visit, 'soap', 'Tanda vital disimpan.');
    }

    public function updateAnamnesis(UpdateAnamnesisRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        $before = activity_snapshot($record);
        $record->update($request->validated());

        activity_audit('updated', $record, $before, 'Updated anamnesis', 'medical_records');

        return $this->respondCare($visit, 'soap', 'Anamnesis disimpan.');
    }

    public function updateFollowUp(UpdateFollowUpReviewRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);
        abort_unless($record->assessment_kind === AssessmentKind::FollowUp, 422);
        app(CareEpisodeService::class)->assertFollowUpAllowed($visit);

        $data = $request->validated();
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
        activity_audit('updated', $record, $before, 'Updated follow-up assessment', 'medical_records', [], $request->user());

        if (($review['plan_decision'] ?? null) === 'end' && $request->user()) {
            $episode = $visit->episode()->first();
            if ($episode) {
                app(CareEpisodeService::class)->close($episode, $request->user());
            }
        }

        return $this->respondCare($visit, 'soap', 'Asesmen ulang disimpan.');
    }

    public function completeAssessment(Request $request, Visit $visit): RedirectResponse
    {
        $this->authorize('view', $visit);
        abort_unless($request->user()?->can('medical_record.update'), 403);

        $record = $this->care->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);

        if ($record->assessment_kind === AssessmentKind::FollowUp) {
            app(CareEpisodeService::class)->assertFollowUpAllowed($visit);
        }

        if ($record->assessment_status === AssessmentStatus::Completed) {
            return back()->with('success', 'Asesmen sudah selesai.');
        }

        $before = activity_snapshot($record);
        $record->update([
            'assessment_status' => AssessmentStatus::Completed,
            'assessment_completed_at' => now(),
            'assessment_completed_by' => $request->user()->id,
        ]);
        activity_audit('updated', $record, $before, 'Completed clinical assessment', 'medical_records', [], $request->user());

        return back()->with('success', 'Asesmen difinalisasi. Koreksi setelah rekam medis terkunci hanya lewat adendum.');
    }

    public function updateNotes(UpdateCareNotesRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);
        $before = activity_snapshot($record);
        $record->update($request->validated());

        activity_audit('updated', $record, $before, 'Updated care notes', 'medical_records');

        return $this->respondCare($visit, 'soap', 'Catatan pelayanan disimpan.');
    }

    public function updateTooth(UpdateToothRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $status = $data['status'] instanceof ToothStatus
            ? $data['status']
            : ToothStatus::from($data['status']);

        $tooth = array_key_exists('conditions', $data)
            ? $this->care->syncToothConditions(
                $visit,
                $request->user(),
                $data['tooth_number'],
                $data['conditions'],
                $data['notes'] ?? null,
            )
            : $this->care->updateTooth(
                $visit,
                $request->user(),
                $data['tooth_number'],
                $status,
                $data['notes'] ?? null,
                $data['surfaces'] ?? [],
            );

        activity_log('updated', $visit, $data, 'Updated odontogram tooth '.$data['tooth_number'], 'odontogram');

        return $this->respondTooth($visit, $tooth, 'Status gigi '.$tooth->tooth_number.' diperbarui.');
    }

    public function destroyTooth(Request $request, Visit $visit, string $toothNumber): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless($request->user()?->can('odontogram.manage'), 403);
        abort_unless($visit->status !== VisitStatus::Cancelled, 403);
        abort_unless(FdiTeeth::isValid($toothNumber), 404);

        $tooth = $this->care->updateTooth(
            $visit,
            $request->user(),
            $toothNumber,
            ToothStatus::Healthy,
            null,
            [],
        );

        activity_log('updated', $visit, ['tooth_number' => $toothNumber, 'status' => ToothStatus::Healthy->value], 'Removed odontogram finding '.$toothNumber, 'odontogram');

        return $this->respondTooth($visit, $tooth, 'Temuan gigi '.$toothNumber.' dihapus.');
    }

    public function updateDentalExam(UpdateDentalExamRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);

        $data = $this->normalizeDentalExam($request->validated());
        $record->update(['dental_exam' => $data]);

        activity_log('updated', $record, $data, 'Updated dental examination', 'medical_records');

        return $this->respondCare($visit, 'pemeriksaan', 'Pemeriksaan gigi & mulut disimpan.', $this->examDone($record));
    }

    public function updateSystemicHistory(UpdateSystemicHistoryRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        app(MedicalRecordLock::class)->assertMutable($record);

        $data = $this->normalizeSystemicHistory($request->validated());
        [$sys, $dia] = $this->bloodPressureParts($data['blood_pressure_sys'] ?? null, $data['blood_pressure_dia'] ?? null, null);
        $data['blood_pressure_sys'] = $sys !== '' ? $sys : null;
        $data['blood_pressure_dia'] = $dia !== '' ? $dia : null;

        $payload = ['systemic_history' => $data];

        if ($request->exists('blood_pressure_sys') || $request->exists('blood_pressure_dia')) {
            $vitals = $record->vital_signs ?? [];
            $vitals['blood_pressure_sys'] = $data['blood_pressure_sys'];
            $vitals['blood_pressure_dia'] = $data['blood_pressure_dia'];
            $vitals['blood_pressure'] = ($sys !== '' || $dia !== '') ? trim($sys.'/'.$dia, '/') : null;
            $payload['vital_signs'] = $vitals;
        }

        $record->update($payload);

        activity_log('updated', $record, $data, 'Updated systemic history', 'medical_records');

        return $this->respondCare($visit, 'soap', 'Riwayat penyakit sistemik disimpan.');
    }

    public function searchDiagnoses(Request $request, Visit $visit): JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless($request->user()?->can('diagnosis.view'), 403);

        return response()->json(IcdCatalog::search(
            (string) $request->query('q', ''),
            30,
            max(1, (int) $request->query('page', 1)),
        ));
    }

    public function searchIcd9(Request $request, Visit $visit): JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless($request->user()?->can('procedure.view') || $request->user()?->can('procedure.manage'), 403);

        return response()->json(Icd9Catalog::search(
            (string) $request->query('q', ''),
            30,
            max(1, (int) $request->query('page', 1)),
        ));
    }

    public function storeDiagnosis(StoreDiagnosisRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $diagnosis = $visit->diagnoses()->create($request->validated());
        if ($request->boolean('single')) {
            $visit->diagnoses()->where('id', '!=', $diagnosis->id)->delete();
        }

        activity_log('created', $diagnosis, $request->validated(), 'Added diagnosis', 'diagnoses');

        return $this->respondCare($visit, 'soap', 'Diagnosis ditambahkan.', null, [
            'diagnosis_id' => $diagnosis->id,
            'update_url' => route('care.diagnosis.update', [$visit, $diagnosis]),
            'destroy_url' => route('care.diagnosis.destroy', [$visit, $diagnosis]),
        ]);
    }

    public function updateDiagnosis(StoreDiagnosisRequest $request, Visit $visit, Diagnosis $diagnosis): RedirectResponse|JsonResponse
    {
        abort_unless((int) $diagnosis->visit_id === (int) $visit->id, 404);

        $before = activity_snapshot($diagnosis);
        $diagnosis->update($request->validated());
        if ($request->boolean('single')) {
            $visit->diagnoses()->where('id', '!=', $diagnosis->id)->delete();
        }

        activity_audit('updated', $diagnosis, $before, 'Updated diagnosis', 'diagnoses');

        return $this->respondCare($visit, 'soap', 'Diagnosis diperbarui.', null, [
            'diagnosis_id' => $diagnosis->id,
            'update_url' => route('care.diagnosis.update', [$visit, $diagnosis]),
            'destroy_url' => route('care.diagnosis.destroy', [$visit, $diagnosis]),
        ]);
    }

    public function destroyDiagnosis(Visit $visit, Diagnosis $diagnosis): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('diagnosis.manage'), 403);
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

        return $this->respondCare($visit, 'soap', 'Diagnosis dihapus.');
    }

    public function storeProcedure(StoreProcedureRecordRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $record = $this->care->addProcedure(
            $visit,
            (int) $data['procedure_id'],
            $data['tooth_number'] ?? null,
            (int) $data['quantity'],
            $data['icd9_code'] ?? null,
            $request->user(),
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

        $note = 'Tindakan ditambahkan. Tarif pasien Rp '.number_format((float) $record->price_at_time, 0, ',', '.').'.';

        if ($record->waiver_status?->value === 'pending') {
            $note = 'Tindakan ditambahkan. Pembebasan jasa dokter menunggu persetujuan. Tarif pasien masih normal.';
        }

        return $this->respondCare($visit, 'tindakan', $note);
    }

    public function updateProcedure(UpdateProcedureRecordRequest $request, Visit $visit, ProcedureRecord $procedureRecord): RedirectResponse|JsonResponse
    {
        $before = activity_snapshot($procedureRecord);
        $data = $request->validated();
        $procedureRecord->update([
            'icd9_code' => $data['icd9_code'] ?? $procedureRecord->icd9_code,
        ]);

        if ($procedureRecord->isUnbilled() && $request->boolean('has_adjustment_form')) {
            app(DoctorFeeWaiverService::class)->updateUnbilled($visit, $procedureRecord, [
                'doctor_fee' => $data['doctor_fee'] ?? null,
                'waive_doctor_fee' => $data['waive_doctor_fee'] ?? false,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'waiver_reason' => $data['waiver_reason'] ?? null,
            ], $request->user());
        }

        activity_audit('updated', $procedureRecord->fresh() ?? $procedureRecord, $before, 'Updated procedure record', 'procedure_records');

        return $this->respondCare($visit, 'tindakan', 'Tindakan diperbarui.');
    }

    public function destroyProcedure(Visit $visit, ProcedureRecord $procedureRecord): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('procedure.manage'), 403);
        abort_unless((int) $procedureRecord->visit_id === (int) $visit->id, 404);
        abort_unless($procedureRecord->isUnbilled(), 403);

        activity_log('deleted', $procedureRecord, ['procedure_id' => $procedureRecord->procedure_id], 'Deleted procedure record', 'procedure_records');
        $procedureRecord->delete();

        return $this->respondCare($visit, 'tindakan', 'Tindakan dihapus.');
    }

    public function quoteProcedureTariff(Request $request, Visit $visit): JsonResponse
    {
        $this->authorize('view', $visit);

        $procedureId = $request->integer('procedure_id');
        abort_if($procedureId < 1, 422, 'Tindakan belum dipilih.');

        $user = $request->user();
        abort_unless($user, 403);

        $quote = app(DoctorFeeWaiverService::class)->quote($visit, $procedureId, [
            'doctor_fee' => $request->filled('doctor_fee') ? $request->input('doctor_fee') : null,
            'waive_doctor_fee' => $request->boolean('waive_doctor_fee'),
            'discount_type' => $request->input('discount_type'),
            'discount_value' => $request->input('discount_value'),
            'waiver_reason' => $request->input('waiver_reason'),
        ], $user);

        $quantity = max(1, $request->integer('quantity') ?: 1);
        $quote['quantity'] = $quantity;
        $quote['charged_line_total'] = bcmul($quote['charged_unit_price'], (string) $quantity, 2);
        $quote['tariff_line_total'] = bcmul($quote['tariff_price'], (string) $quantity, 2);

        return response()->json($quote);
    }

    public function approveProcedureWaiver(Request $request, Visit $visit, ProcedureRecord $procedureRecord): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless((int) $procedureRecord->visit_id === (int) $visit->id, 404);

        $log = $procedureRecord->waiverLogs()->where('status', 'pending')->latest('id')->first();
        abort_unless($log, 404);

        $this->authorize('approve', $log);
        app(DoctorFeeWaiverService::class)->approve($log, $request->user());

        return $this->respondCare($visit, 'tindakan', 'Pembebasan jasa dokter disetujui. Tagihan pasien diperbarui.');
    }

    public function storePrescriptionItem(StorePrescriptionItemRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $prescription = $this->care->draftPrescription($visit, $request->user());
        $item = app(PrescriptionItemWriter::class)->create($prescription, $request->validated());

        activity_log('created', $item, $request->validated(), 'Added prescription item', 'prescriptions');

        return $this->respondCare($visit, 'resep', 'Obat ditambahkan ke resep draft.', null, $this->prescriptionAjaxPayload($visit));
    }

    public function updatePrescriptionItem(UpdatePrescriptionItemRequest $request, Visit $visit, PrescriptionItem $prescriptionItem): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $ingredients = $data['ingredients'] ?? null;
        unset($data['ingredients'], $data['composition_confirmed']);
        $prescriptionItem->update($data);

        if ($prescriptionItem->isCompound() && is_array($ingredients)) {
            app(PrescriptionItemWriter::class)->replaceIngredients($prescriptionItem, $ingredients);
        }

        activity_log('updated', $prescriptionItem, $request->validated(), 'Updated prescription item', 'prescriptions');

        return $this->respondCare($visit, 'resep', 'Item resep diperbarui.', null, $this->prescriptionAjaxPayload($visit));
    }

    public function destroyPrescriptionItem(Visit $visit, PrescriptionItem $prescriptionItem): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('prescription.update'), 403);
        abort_unless($visit->status !== VisitStatus::Cancelled, 403);

        $prescription = $prescriptionItem->prescription;
        abort_unless($prescription && (int) $prescription->visit_id === (int) $visit->id, 404);
        abort_unless($prescription->isDraft(), 403);

        activity_log('deleted', $prescriptionItem, ['medicine_id' => $prescriptionItem->medicine_id], 'Deleted prescription item', 'prescriptions');
        $prescriptionItem->delete();

        if (! $prescription->items()->exists()) {
            $prescription->delete();
        }

        return $this->respondCare($visit, 'resep', 'Item resep dihapus.', null, $this->prescriptionAjaxPayload($visit));
    }

    public function sendPrescription(Visit $visit, Prescription $prescription): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('prescription.update'), 403);
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

        return $this->respondCare($visit, 'resep', $message);
    }

    public function storeReferral(StoreReferralRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $referral = $visit->referrals()->create($request->validated());

        activity_log('created', $referral, $request->validated(), 'Added referral', 'referrals');

        return $this->respondCare($visit, 'rujukan', 'Rujukan ditambahkan.');
    }

    public function updateReferral(StoreReferralRequest $request, Visit $visit, Referral $referral): RedirectResponse|JsonResponse
    {
        abort_unless((int) $referral->visit_id === (int) $visit->id, 404);

        $referral->update($request->validated());

        activity_log('updated', $referral, $request->validated(), 'Updated referral', 'referrals');

        return $this->respondCare($visit, 'rujukan', 'Rujukan diperbarui.');
    }

    public function destroyReferral(Visit $visit, Referral $referral): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('referral.manage'), 403);
        abort_unless((int) $referral->visit_id === (int) $visit->id, 404);

        activity_log('deleted', $referral, ['referred_to' => $referral->referred_to], 'Deleted referral', 'referrals');
        $referral->delete();

        return $this->respondCare($visit, 'rujukan', 'Rujukan dihapus.');
    }

    public function printReferral(Visit $visit, Referral $referral): Response
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('referral.view'), 403);
        abort_unless((int) $referral->visit_id === (int) $visit->id, 404);

        $visit->loadMissing(['patient', 'doctor.doctorProfile', 'branch', 'room', 'diagnoses']);

        $meta = $this->printMeta($visit);
        $letterDate = $referral->created_at ?? $visit->visit_date ?? now();
        $letterNumber = $this->letterNumber('RJU', (int) $referral->id, $letterDate);
        $document = app(\App\Support\Clinical\ClinicalDocumentService::class)->issue(
            $visit,
            request()->user(),
            \App\Enums\ClinicalDocumentType::Referral,
            $letterNumber,
            ['referred_to' => $referral->referred_to, 'reason' => $referral->reason],
            $referral,
        );

        return Pdf::loadView('clinical.care.print.referral', [
            ...$meta,
            'title' => 'Surat Rujukan',
            'referral' => $referral,
            'diagnoses' => $visit->diagnoses,
            'letterNumber' => $letterNumber,
            'verificationCode' => $document->verification_code,
            'signatureSrc' => app(\App\Support\Clinical\ClinicalDocumentService::class)->signatureSrc($document->signature_path),
        ])->setPaper('a4')->stream('surat-rujukan-'.$visit->id.'.pdf');
    }

    public function printDischarge(Visit $visit): Response
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('medical_record.view'), 403);

        $summary = \App\Support\Clinical\DischargeSummary::for($visit);

        return Pdf::loadView('clinical.care.print.discharge', [
            ...$this->printMeta($visit),
            'title' => 'Resume Pulang',
            ...$summary,
        ])->setPaper('a4')->stream('resume-pulang-'.$visit->id.'.pdf');
    }

    public function printPrescription(Visit $visit, Prescription $prescription): Response
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('prescription.view'), 403);
        abort_unless((int) $prescription->visit_id === (int) $visit->id, 404);
        abort_unless($prescription->items()->exists(), 422, 'Resep belum memiliki item obat.');

        $prescription->load(['doctor:id,name', 'items.medicine:id,name', 'items.ingredients']);
        $meta = $this->printMeta($visit);

        activity_log('printed', $prescription, ['visit_id' => $visit->id], 'Cetak resep dari pelayanan', 'prescriptions');

        return Pdf::loadView('clinical.history.print.prescription', [
            ...$meta,
            'patient' => $visit->patient,
            'prescription' => $prescription,
            'title' => 'Resep Obat',
            'letterNumber' => 'RX-'.(new ExaminationQueueRow($visit))->registrationNumber().'-'.$prescription->id,
        ])->setPaper('a5')->stream('resep-'.$prescription->id.'.pdf');
    }

    public function printPrescriptions(Visit $visit): Response
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('prescription.view'), 403);

        $visit->loadMissing([
            'patient',
            'doctor.doctorProfile',
            'branch',
            'prescriptions.items.medicine',
            'prescriptions.items.ingredients',
            'prescriptions.doctor:id,name',
        ]);

        $prescriptions = $visit->prescriptions
            ->filter(fn (Prescription $prescription) => $prescription->items->isNotEmpty())
            ->values();
        abort_unless($prescriptions->isNotEmpty(), 422, 'Belum ada resep yang dapat dicetak.');

        if ($prescriptions->count() === 1) {
            return $this->printPrescription($visit, $prescriptions->first());
        }

        $meta = $this->printMeta($visit);
        activity_log('printed', $visit, ['prescription_ids' => $prescriptions->pluck('id')->all()], 'Cetak seluruh resep kunjungan', 'prescriptions');

        return Pdf::loadView('clinical.care.print.prescriptions', [
            ...$meta,
            'patient' => $visit->patient,
            'prescriptions' => $prescriptions,
            'title' => 'Resep Obat',
            'letterNumber' => 'RX-'.(new ExaminationQueueRow($visit))->registrationNumber(),
        ])->setPaper('a5')->stream('resep-kunjungan-'.$visit->id.'.pdf');
    }

    public function printInstructions(Visit $visit): Response
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('medical_record.view'), 403);

        $visit->loadMissing(['patient', 'doctor', 'branch', 'room', 'medicalRecord']);
        $record = $visit->medicalRecord;
        $instructions = $record?->plan_instructions ?? [];
        $labels = CareExamOptions::instructions();
        $items = collect($instructions['items'] ?? [])
            ->map(fn ($key) => $labels[$key] ?? $key)
            ->filter()
            ->values();
        $extra = trim((string) ($instructions['extra'] ?? ''));

        $meta = $this->printMeta($visit);
        $letterDate = $visit->visit_date ?? now();

        return Pdf::loadView('clinical.care.print.instructions', [
            ...$meta,
            'title' => 'Instruksi Perawatan Pasien',
            'items' => $items,
            'extra' => $extra,
            'clinicalNotes' => $record?->clinical_notes,
            'letterNumber' => $this->letterNumber('INS', (int) $visit->id, $letterDate),
        ])->setPaper('a4')->stream('instruksi-pasien-'.$visit->id.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    protected function printMeta(Visit $visit): array
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
            'prescriptions.items.ingredients',
            'medicalRecord.amendments',
        ]);

        return [
            ...PrintChrome::variables((int) $visit->tenant_id, $visit->branch),
            'branch' => $visit->branch,
            'visit' => $visit,
            'patient' => $visit->patient,
            'visitDateLabel' => $this->indonesianDate($visit->visit_date ?? now()),
            'signatureSrc' => app(\App\Support\Clinical\ClinicalDocumentService::class)->signatureSrc($visit->doctor?->doctorProfile?->signature_path),
            'sheet' => PrintSheet::for($visit),
        ];
    }

    protected function letterNumber(string $code, int $number, mixed $date): string
    {
        $carbon = Carbon::parse($date);
        $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];

        return sprintf('%03d/%s/%s/%s', $number, $code, $roman[(int) $carbon->format('n')], $carbon->format('Y'));
    }

    protected function indonesianDate(mixed $date): string
    {
        $carbon = Carbon::parse($date);
        $months = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

        return $carbon->format('j').' '.$months[(int) $carbon->format('n')].' '.$carbon->format('Y');
    }

    public function finalizeRecord(Request $request, Visit $visit): RedirectResponse
    {
        $this->authorize('view', $visit);
        abort_unless($request->user()?->can('medical_record.update'), 403);

        $record = $this->care->ensureRecord($visit);
        app(\App\Support\Clinical\MedicalRecordLock::class)->finalize($record, $request->user());

        return back()->with('success', 'Rekam medis difinalisasi. Koreksi isi dibuka 2×24 jam.');
    }

    public function storeAmendment(Request $request, Visit $visit): RedirectResponse
    {
        $this->authorize('view', $visit);
        abort_unless($request->user()?->can('medical_record.update'), 403);

        $record = $visit->medicalRecord;
        abort_unless($record, 404);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        app(\App\Support\Clinical\MedicalRecordLock::class)->amend($record, $request->user(), $data['body']);

        return back()->with('success', 'Adendum tersimpan.');
    }

    public function storeSickLetter(Request $request, Visit $visit): RedirectResponse
    {
        $this->authorize('view', $visit);
        abort_unless($request->user()?->can('medical_record.view'), 403);

        $data = $request->validate([
            'rest_from' => ['required', 'date'],
            'rest_days' => ['required', 'integer', 'min:1', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $visit->loadMissing(['patient', 'doctor.doctorProfile', 'branch']);
        $number = $this->letterNumber('SKT', (int) $visit->id, $data['rest_from']);
        $document = app(\App\Support\Clinical\ClinicalDocumentService::class)->issue(
            $visit,
            $request->user(),
            \App\Enums\ClinicalDocumentType::SickLetter,
            $number.'-'.now()->format('His'),
            $data,
        );

        return redirect()->route('care.documents.pdf', [$visit, $document]);
    }

    public function printResume(Request $request, Visit $visit): Response
    {
        $this->authorize('view', $visit);
        abort_unless($request->user()?->can('medical_record.view'), 403);

        $visit->loadMissing(['patient', 'doctor.doctorProfile', 'branch', 'diagnoses', 'procedureRecords.procedure', 'medicalRecord.amendments']);
        $number = $this->letterNumber('RSM', (int) $visit->id, $visit->visit_date ?? now());
        $document = app(\App\Support\Clinical\ClinicalDocumentService::class)->issue(
            $visit,
            $request->user(),
            \App\Enums\ClinicalDocumentType::Resume,
            $number,
            ['visit_id' => $visit->id],
        );

        return $this->streamDocument($visit, $document);
    }

    public function printDocument(Visit $visit, \App\Models\ClinicalDocument $document): Response
    {
        $this->authorize('view', $visit);
        abort_unless((int) $document->visit_id === (int) $visit->id, 404);
        abort_unless(request()->user()?->can('medical_record.view') || request()->user()?->can('referral.view'), 403);

        return $this->streamDocument($visit, $document);
    }

    public function cancelDocument(Visit $visit, \App\Models\ClinicalDocument $document): RedirectResponse
    {
        $this->authorize('view', $visit);
        abort_unless((int) $document->visit_id === (int) $visit->id, 404);
        abort_unless(request()->user()?->can('medical_record.update'), 403);

        $document->update(['status' => \App\Enums\ClinicalDocumentStatus::Cancelled]);

        return back()->with('success', 'Dokumen dibatalkan. Kode cek menampilkan status dibatalkan.');
    }

    protected function streamDocument(Visit $visit, \App\Models\ClinicalDocument $document): Response
    {
        if (data_get($document->payload, 'scope') === 'patient') {
            return app(\App\Support\Clinical\PatientResumeService::class)->pdf($document);
        }

        $visit->loadMissing(['patient', 'doctor.doctorProfile', 'branch', 'room', 'diagnoses', 'procedureRecords.procedure', 'medicalRecord.amendments']);
        $meta = $this->printMeta($visit);
        $service = app(\App\Support\Clinical\ClinicalDocumentService::class);
        $shared = [
            ...$meta,
            'letterNumber' => $document->number,
            'verificationCode' => $document->verification_code,
            'signatureSrc' => $service->signatureSrc($document->signature_path),
            'document' => $document,
        ];

        $view = match ($document->type) {
            \App\Enums\ClinicalDocumentType::SickLetter => 'clinical.care.print.sick',
            \App\Enums\ClinicalDocumentType::Resume => 'clinical.care.print.resume',
            default => 'clinical.care.print.referral',
        };

        return Pdf::loadView($view, [
            ...$shared,
            'title' => $document->type === \App\Enums\ClinicalDocumentType::Resume
                ? 'Resume Medis Rawat Jalan'
                : $document->type->label(),
            'referral' => $document->reference_type === \App\Models\Referral::class ? $document->reference : $visit->referrals()->first(),
            'diagnoses' => $visit->diagnoses,
        ])->setPaper('a4')->stream($document->verification_code.'.pdf');
    }

    public function complete(Visit $visit): RedirectResponse
    {
        $this->authorize('view', $visit);
        abort_unless($visit->status->isOpen(), 403);

        $actor = request()->user();
        abort_unless($actor?->isDentist() && $actor->can('queue.manage'), 403);

        if ($actor?->can('medical_record.update')) {
            $pending = $this->care->ensureRecord($visit);
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
            $this->authorize('complete', $queue);
            $queue->update(['status' => QueueStatus::Done]);
        } else {
            abort_unless(request()->user()?->can('queue.manage'), 403);
        }

        $sentPrescriptions = 0;
        foreach ($draftPrescriptions as $prescription) {
            $prescription->update(['status' => PrescriptionStatus::Sent]);
            activity_log('updated', $prescription, ['status' => PrescriptionStatus::Sent->value], 'Sent prescription to pharmacy', 'prescriptions');
            $sentPrescriptions++;
        }

        $before = activity_snapshot($visit);
        $visit->update(['status' => VisitStatus::Done]);

        $invoice = $this->billing->generateForVisit($visit->fresh(), request()->user());

        activity_audit('updated', $visit, $before, 'Completed clinical visit', 'visits', [
            'status' => VisitStatus::Done->value,
        ]);

        $finalizedNow = false;
        $assessmentNow = false;
        if ($actor?->can('medical_record.update')) {
            $record = $this->care->ensureRecord($visit);
            $lock = app(\App\Support\Clinical\MedicalRecordLock::class);
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

        if ($invoice && $actor?->can('view', $invoice)) {
            return redirect()
                ->route('billing.invoices.show', ['invoice' => $invoice, 'from' => 'pelayanan'])
                ->with('success', $message);
        }

        return redirect()
            ->route('care.show', ['visit' => $visit, 'from' => 'pelayanan'])
            ->with('success', $message);
    }

    protected function seedFollowUpDiagnoses(Visit $visit): void
    {
        $record = $visit->medicalRecord()->first();
        if (! $record || $record->assessment_kind !== AssessmentKind::FollowUp) {
            return;
        }
        if (app(MedicalRecordLock::class)->isLocked($record)) {
            return;
        }

        $review = is_array($record->follow_up_review) ? $record->follow_up_review : [];
        $keepId = $visit->diagnoses()->orderByDesc('id')->value('id');
        if ($keepId) {
            $visit->diagnoses()->where('id', '!=', $keepId)->delete();
            if (empty($review['diagnoses_seeded'])) {
                $review['diagnoses_seeded'] = true;
                $record->follow_up_review = $review;
                $record->save();
            }

            return;
        }
        if (! empty($review['diagnoses_seeded'])) {
            return;
        }

        $context = app(CareEpisodeService::class)->context($visit);
        $previous = collect($context['history'] ?? [])
            ->filter(fn (Visit $item) => (int) $item->id !== (int) $visit->id)
            ->sortByDesc(fn (Visit $item) => ($item->visit_date?->format('Y-m-d') ?? '').sprintf('%010d', $item->id))
            ->flatMap(fn (Visit $item) => $item->diagnoses)
            ->first();
        if (! $previous) {
            return;
        }

        DB::transaction(function () use ($visit, $record, $review, $previous) {
            $created = $visit->diagnoses()->create([
                'kind' => DiagnosisKind::Clinical,
                'rank' => $previous->rank ?? DiagnosisRank::Secondary,
                'tooth_number' => $previous->tooth_number,
                'code' => $previous->code,
                'description' => $previous->description,
            ]);
            activity_log('created', $created, ['code' => $created->code], 'Added diagnosis', 'diagnoses');
            $visit->diagnoses()->where('id', '!=', $created->id)->delete();
            $review['diagnoses_seeded'] = true;
            $record->follow_up_review = $review;
            $record->save();
        });
    }

    protected function respondTooth(Visit $visit, OdontogramTooth $tooth, string $message): RedirectResponse|JsonResponse
    {
        if (request()->wantsJson() || request()->ajax()) {
            $conditions = ToothConditions::fromRecord($tooth);

            return response()->json([
                'ok' => true,
                'message' => $message,
                'section' => 'odontogram',
                'tooth' => [
                    'number' => $tooth->tooth_number,
                    'status' => $tooth->status->value,
                    'status_label' => ToothConditions::summary($conditions),
                    'color' => $tooth->status->color(),
                    'surfaces' => $tooth->surfaces ?? [],
                    'surface_labels' => '',
                    'conditions' => $conditions,
                    'notes' => $tooth->notes,
                    'html' => Blade::render('<x-odontogram.tooth :number="$number" :record="$record" />', [
                        'number' => $tooth->tooth_number,
                        'record' => $tooth,
                    ]),
                    'row_html' => $conditions === [] ? null : view('clinical.care.partials.odontogram-finding', [
                        'tooth' => $tooth,
                        'canManageOdo' => true,
                    ])->render(),
                ],
            ]);
        }

        return $this->respondCare($visit, 'odontogram', $message);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function respondCare(Visit $visit, string $section, string $message, ?bool $done = null, array $extra = []): RedirectResponse|JsonResponse
    {
        $section = match ($section) {
            'keluhan', 'screening', 'diagnosis', 'instruksi', 'lab', 'pemeriksaan', 'exam' => 'soap',
            'surat', 'adendum' => 'ringkasan',
            default => $section,
        };

        if (in_array($section, ['soap', 'rujukan', 'ringkasan'], true)) {
            $progress = CareProgress::for($visit, $visit->medicalRecord()->first(), collect());
            $done = (bool) ($progress[$section] ?? false);
        }

        if (request()->wantsJson() || request()->ajax()) {
            $payload = [
                'ok' => true,
                'message' => $message,
                'section' => $section,
            ];

            if ($done !== null) {
                $payload['done'] = $done;
            }

            return response()->json(array_merge($payload, $extra));
        }

        return redirect()
            ->route('care.show', ['visit' => $visit, 'tab' => $section])
            ->with('success', $message);
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

    protected function screeningDone(MedicalRecord $record): bool
    {
        return CareProgress::screeningComplete(
            $record->vital_signs ?? [],
            $record->systemic_history ?? [],
        );
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

    protected function examDone(MedicalRecord $record): bool
    {
        return CareProgress::examComplete($record->dental_exam ?? [], $record->initial_examination);
    }

    protected function instructionDone(MedicalRecord $record): bool
    {
        return CareProgress::instructionComplete($record);
    }

    protected function authorizeCare(Request $request, Visit $visit): void
    {
        $this->authorize('view', $visit);
        abort_unless(CareTabs::canAccessCare($request->user()), 403);
    }

    /**
     * @return array{html: string, finish_html: string}
     */
    protected function prescriptionAjaxPayload(Visit $visit): array
    {
        $this->loadPrescriptionRelations($visit);

        return [
            'html' => $this->prescriptionListHtml($visit),
            'finish_html' => $this->prescriptionFinishHtml($visit),
        ];
    }

    protected function loadPrescriptionRelations(Visit $visit): void
    {
        $visit->load([
            'patient',
            'prescriptions.items.medicine',
            'prescriptions.items.ingredients.medicine',
            'prescriptions.doctor',
        ]);
    }

    protected function prescriptionListHtml(Visit $visit): string
    {
        $user = request()->user();
        $this->loadPrescriptionRelations($visit);

        $record = $visit->medicalRecord;
        $writable = (bool) $user
            && $this->canWriteAny($user)
            && $visit->status !== VisitStatus::Cancelled;

        if ($writable && $record && app(MedicalRecordLock::class)->isLocked($record)) {
            $writable = false;
        }

        return view('clinical.care.partials.plan-prescriptions-list', [
            'visit' => $visit,
            'record' => $record,
            'writable' => $writable,
            'medicines' => Medicine::query()->active()->orderBy('name')->get(),
        ])->render();
    }

    protected function prescriptionFinishHtml(Visit $visit): string
    {
        $this->loadPrescriptionRelations($visit);

        return view('clinical.care.partials.care-finish-rx', [
            'visit' => $visit,
        ])->render();
    }

    protected function canWriteAny(User $user): bool
    {
        foreach ([
            'medical_record.update',
            'examination.manage',
            'vital_sign.manage',
            'anamnesis.manage',
            'care_note.manage',
            'odontogram.manage',
            'diagnosis.manage',
            'procedure.manage',
            'prescription.create',
            'referral.manage',
        ] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
