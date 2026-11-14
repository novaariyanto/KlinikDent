<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClinicalDocumentType;
use App\Enums\TenantStatus;
use App\Enums\ToothStatus;
use App\Enums\VisitStatus;
use App\Exceptions\MobileApiException;
use App\Http\Controllers\Clinical\CareController as WebCareController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\SavePrescriptionAsTemplateRequest;
use App\Http\Requests\Clinical\StoreDiagnosisRequest;
use App\Http\Requests\Clinical\StorePrescriptionItemRequest;
use App\Http\Requests\Clinical\StorePrescriptionTemplateRequest;
use App\Http\Requests\Clinical\StoreProcedureRecordRequest;
use App\Http\Requests\Clinical\StoreReferralRequest;
use App\Http\Requests\Clinical\UpdateAnamnesisRequest;
use App\Http\Requests\Clinical\UpdateCareNotesRequest;
use App\Http\Requests\Clinical\UpdateDentalExamRequest;
use App\Http\Requests\Clinical\UpdateExaminationRequest;
use App\Http\Requests\Clinical\UpdateFollowUpReviewRequest;
use App\Http\Requests\Clinical\UpdateMedicalRecordRequest;
use App\Http\Requests\Clinical\UpdatePrescriptionItemRequest;
use App\Http\Requests\Clinical\UpdatePrescriptionTemplateRequest;
use App\Http\Requests\Clinical\UpdateProcedureRecordRequest;
use App\Http\Requests\Clinical\UpdateSystemicHistoryRequest;
use App\Http\Requests\Clinical\UpdateToothRequest;
use App\Http\Requests\Clinical\UpdateVitalsRequest;
use App\Http\Resources\Api\CareResource;
use App\Models\ClinicalDocument;
use App\Models\DentalLabOrder;
use App\Models\Diagnosis;
use App\Models\MedicalRecordAmendment;
use App\Models\OdontogramTooth;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\PrescriptionTemplate;
use App\Models\ProcedureRecord;
use App\Models\Referral;
use App\Models\User;
use App\Models\Visit;
use App\Support\Clinical\ClinicalCareService;
use App\Support\Clinical\ClinicalDocumentService;
use App\Support\Clinical\MedicalRecordReadAudit;
use App\Support\Clinical\PrescriptionTemplateService;
use BackedEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class CareController extends Controller
{
    public function __construct(
        protected ClinicalCareService $care,
        protected PrescriptionTemplateService $templates,
    ) {}

    public function show(Request $request, Visit $visit): CareResource
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('examination.view');

        $user = $request->user();
        $record = $visit->medicalRecord;

        if ($record && $user instanceof User) {
            app(MedicalRecordReadAudit::class)->record(
                $user,
                $record,
                'Membaca rekam medis kunjungan '.$visit->id,
            );
        }

        return new CareResource($visit);
    }

    public function pdfResume(Request $request, Visit $visit): Response
    {
        $this->authorizePdf($visit, 'medical_record.view');

        return $this->pdfOrExisting(
            $request,
            $visit,
            ClinicalDocumentType::Resume,
            null,
            $this->documentNumber('RSM', (int) $visit->id, $visit->visit_date ?? now()),
            fn () => app(WebCareController::class)->printResume($request, $visit),
        );
    }

    public function pdfReferral(Request $request, Visit $visit, Referral $referral): Response
    {
        $this->authorizePdf($visit, 'referral.view');
        $letterDate = $referral->created_at ?? $visit->visit_date ?? now();

        return $this->pdfOrExisting(
            $request,
            $visit,
            ClinicalDocumentType::Referral,
            $referral,
            $this->documentNumber('RJU', (int) $referral->id, $letterDate),
            fn () => app(WebCareController::class)->printReferral($visit, $referral),
        );
    }

    public function pdfDocument(Visit $visit, ClinicalDocument $document): Response
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorizeAny('medical_record.view', 'referral.view');

        return app(WebCareController::class)->printDocument($visit, $document);
    }

    public function pdfPrescription(Visit $visit, Prescription $prescription): Response
    {
        $this->authorizePdf($visit, 'prescription.view');

        return app(WebCareController::class)->printPrescription($visit, $prescription);
    }

    public function pdfPrescriptions(Visit $visit): Response
    {
        $this->authorizePdf($visit, 'prescription.view');

        return app(WebCareController::class)->printPrescriptions($visit);
    }

    public function pdfInstructions(Visit $visit): Response
    {
        $this->authorizePdf($visit, 'medical_record.view');

        return app(WebCareController::class)->printInstructions($visit);
    }

    public function pdfDischarge(Visit $visit): Response
    {
        $this->authorizePdf($visit, 'medical_record.view');

        return app(WebCareController::class)->printDischarge($visit);
    }

    public function updateRecord(UpdateMedicalRecordRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $record = $this->care->updateRecord($visit, $request->validated());

        return $this->saved($visit, [
            'medical_record' => [
                'id' => (int) $record->id,
                'chief_complaint' => $record->chief_complaint,
                'clinical_notes' => $record->clinical_notes,
                'plan_instructions' => $record->plan_instructions,
                'control_date' => $this->date($record->control_date),
            ],
        ]);
    }

    public function updateExamination(UpdateExaminationRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $record = $this->care->updateExamination($visit, $request->validated());

        return $this->saved($visit, [
            'initial_examination' => $record->initial_examination,
        ]);
    }

    public function updateVitals(UpdateVitalsRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $record = $this->care->updateVitals($visit, $request->validated());

        return $this->saved($visit, [
            'vital_signs' => $record->vital_signs,
        ]);
    }

    public function updateAnamnesis(UpdateAnamnesisRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $record = $this->care->updateAnamnesis($visit, $request->validated());

        return $this->saved($visit, [
            'anamnesis' => $record->anamnesis,
        ]);
    }

    public function updateFollowUp(UpdateFollowUpReviewRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $record = $this->care->updateFollowUp($visit, $request->validated(), $this->actor($request));

        return $this->saved($visit, [
            'follow_up_review' => $record->follow_up_review,
            'control_date' => $this->date($record->control_date),
        ]);
    }

    public function updateNotes(UpdateCareNotesRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $record = $this->care->updateNotes($visit, $request->validated());

        return $this->saved($visit, [
            'care_notes' => $record->care_notes,
        ]);
    }

    public function updateTooth(UpdateToothRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $data = $request->validated();
        $user = $this->actor($request);
        $status = $data['status'] instanceof ToothStatus
            ? $data['status']
            : ToothStatus::from($data['status']);

        $tooth = array_key_exists('conditions', $data)
            ? $this->care->syncToothConditions(
                $visit,
                $user,
                $data['tooth_number'],
                $data['conditions'],
                $data['notes'] ?? null,
            )
            : $this->care->updateTooth(
                $visit,
                $user,
                $data['tooth_number'],
                $status,
                $data['notes'] ?? null,
                $data['surfaces'] ?? [],
            );

        activity_log('updated', $visit, $data, 'Updated odontogram tooth '.$data['tooth_number'], 'odontogram');

        return $this->saved($visit, ['tooth' => $this->tooth($tooth)]);
    }

    public function destroyTooth(Request $request, Visit $visit, string $toothNumber): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('odontogram.manage');
        abort_unless($visit->status !== VisitStatus::Cancelled, 403);

        $tooth = $this->care->destroyTooth($visit, $toothNumber, $this->actor($request));

        return $this->saved($visit, ['tooth' => $this->tooth($tooth)]);
    }

    public function updateDentalExam(UpdateDentalExamRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $record = $this->care->updateDentalExam($visit, $request->validated());

        return $this->saved($visit, [
            'dental_exam' => $record->dental_exam,
        ]);
    }

    public function updateSystemicHistory(UpdateSystemicHistoryRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $record = $this->care->updateSystemicHistory(
            $visit,
            $request->validated(),
            $request->exists('blood_pressure_sys') || $request->exists('blood_pressure_dia'),
        );

        return $this->saved($visit, [
            'systemic_history' => $record->systemic_history,
            'vital_signs' => $record->vital_signs,
        ]);
    }

    public function searchDiagnoses(Request $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('diagnosis.view');

        return response()->json([
            'data' => app(WebCareController::class)->searchDiagnoses($request, $visit)->getData(true),
        ]);
    }

    public function searchIcd9(Request $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorizeAny('procedure.view', 'procedure.manage');

        return response()->json([
            'data' => app(WebCareController::class)->searchIcd9($request, $visit)->getData(true),
        ]);
    }

    public function storeDiagnosis(StoreDiagnosisRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $diagnosis = $this->care->storeDiagnosis($visit, $request->validated(), $request->boolean('single'));

        return $this->saved($visit, ['diagnosis' => $this->diagnosis($diagnosis)]);
    }

    public function updateDiagnosis(StoreDiagnosisRequest $request, Visit $visit, Diagnosis $diagnosis): JsonResponse
    {
        $this->guard($visit);
        $diagnosis = $this->care->updateDiagnosis($visit, $diagnosis, $request->validated(), $request->boolean('single'));

        return $this->saved($visit, ['diagnosis' => $this->diagnosis($diagnosis)]);
    }

    public function destroyDiagnosis(Visit $visit, Diagnosis $diagnosis): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('diagnosis.manage');
        $this->care->destroyDiagnosis($visit, $diagnosis);

        return $this->saved($visit, ['deleted_id' => (int) $diagnosis->id]);
    }

    public function storeProcedure(StoreProcedureRecordRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $record = $this->care->storeProcedure($visit, $request->validated(), $this->actor($request));

        return $this->saved($visit, [
            'procedure' => $this->procedure($record),
            'message' => $this->care->procedureAddedMessage($record),
        ]);
    }

    public function updateProcedure(UpdateProcedureRecordRequest $request, Visit $visit, ProcedureRecord $procedureRecord): JsonResponse
    {
        $this->guard($visit);
        $record = $this->care->updateProcedure(
            $visit,
            $procedureRecord,
            $request->validated(),
            $this->actor($request),
            $request->boolean('has_adjustment_form'),
        );

        return $this->saved($visit, ['procedure' => $this->procedure($record)]);
    }

    public function destroyProcedure(Visit $visit, ProcedureRecord $procedureRecord): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('procedure.manage');
        $id = (int) $procedureRecord->id;
        $this->care->destroyProcedure($visit, $procedureRecord);

        return $this->saved($visit, ['deleted_id' => $id]);
    }

    public function quoteProcedureTariff(Request $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);

        return response()->json([
            'data' => app(WebCareController::class)->quoteProcedureTariff($request, $visit)->getData(true),
        ]);
    }

    public function approveProcedureWaiver(Request $request, Visit $visit, ProcedureRecord $procedureRecord): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $record = $this->care->approveProcedureWaiver($visit, $procedureRecord, $this->actor($request));

        return $this->saved($visit, ['procedure' => $this->procedure($record)]);
    }

    public function storePrescriptionItem(StorePrescriptionItemRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $item = $this->care->storePrescriptionItem($visit, $this->actor($request), $request->validated());

        return $this->saved($visit, ['prescription_item' => $this->prescriptionItem($item)]);
    }

    public function updatePrescriptionItem(UpdatePrescriptionItemRequest $request, Visit $visit, PrescriptionItem $prescriptionItem): JsonResponse
    {
        $this->guard($visit);
        $item = $this->care->updatePrescriptionItem($prescriptionItem, $request->validated());

        return $this->saved($visit, ['prescription_item' => $this->prescriptionItem($item)]);
    }

    public function destroyPrescriptionItem(Visit $visit, PrescriptionItem $prescriptionItem): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('prescription.update');
        abort_unless($visit->status !== VisitStatus::Cancelled, 403);
        $id = (int) $prescriptionItem->id;
        $this->care->destroyPrescriptionItem($visit, $prescriptionItem);

        return $this->saved($visit, ['deleted_id' => $id]);
    }

    public function sendPrescription(Visit $visit, Prescription $prescription): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('prescription.update');
        $message = $this->care->sendPrescription($visit, $prescription);

        return $this->saved($visit, [
            'prescription' => [
                'id' => (int) $prescription->id,
                'status' => $this->enumValue($prescription->status),
            ],
            'message' => $message,
        ]);
    }

    public function storePrescriptionTemplate(StorePrescriptionTemplateRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $template = $this->templates->create(
            $this->actor($request),
            $request->validated('name'),
            $request->validated('items'),
        );
        activity_log('created', $template, ['name' => $template->name], 'Template resep '.$template->name.' disimpan.', 'prescriptions');

        return $this->saved($visit, ['template' => $this->template($template)]);
    }

    public function updatePrescriptionTemplate(UpdatePrescriptionTemplateRequest $request, Visit $visit, PrescriptionTemplate $prescriptionTemplate): JsonResponse
    {
        $this->guard($visit);
        $template = $this->templates->update(
            $prescriptionTemplate,
            $request->validated('name'),
            $request->validated('items'),
        );
        activity_log('updated', $template, ['name' => $template->name], 'Template resep '.$template->name.' diubah.', 'prescriptions');

        return $this->saved($visit, ['template' => $this->template($template)]);
    }

    public function destroyPrescriptionTemplate(Visit $visit, PrescriptionTemplate $prescriptionTemplate): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $user = $this->actor(request());
        $this->authorize('prescription.create');
        abort_unless($prescriptionTemplate->canBeManagedBy($user), 403);
        $id = (int) $prescriptionTemplate->id;
        $this->templates->delete($prescriptionTemplate);

        return $this->saved($visit, ['deleted_id' => $id]);
    }

    public function applyPrescriptionTemplate(Visit $visit, PrescriptionTemplate $prescriptionTemplate): JsonResponse
    {
        $this->guard($visit);
        $user = $this->actor(request());
        $this->authorize('view', $visit);
        $this->authorize('prescription.create');
        abort_unless($visit->status !== VisitStatus::Cancelled, 403);

        $result = $this->templates->apply($visit, $user, $prescriptionTemplate);
        $message = 'Template diterapkan ke resep draft. Periksa dan ubah item sebelum dikirim.';
        if ($result['skipped'] !== []) {
            $message .= ' Obat tidak aktif dilewati: '.implode(', ', $result['skipped']).'.';
        }

        return $this->saved($visit, [
            'prescription_id' => (int) $result['prescription']->id,
            'skipped' => $result['skipped'],
            'message' => $message,
        ]);
    }

    public function storeTemplateFromPrescription(SavePrescriptionAsTemplateRequest $request, Visit $visit, Prescription $prescription): JsonResponse
    {
        $this->guard($visit);
        $template = $this->templates->createFromPrescription(
            $this->actor($request),
            $prescription,
            $request->validated('name'),
        );
        activity_log('created', $template, ['name' => $template->name, 'prescription_id' => $prescription->id], 'Resep disimpan sebagai template '.$template->name.'.', 'prescriptions');

        return $this->saved($visit, ['template' => $this->template($template)]);
    }

    public function storeReferral(StoreReferralRequest $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $referral = $this->care->storeReferral($visit, $request->validated());

        return $this->saved($visit, ['referral' => $this->referral($referral)]);
    }

    public function updateReferral(StoreReferralRequest $request, Visit $visit, Referral $referral): JsonResponse
    {
        $this->guard($visit);
        $referral = $this->care->updateReferral($visit, $referral, $request->validated());

        return $this->saved($visit, ['referral' => $this->referral($referral)]);
    }

    public function destroyReferral(Visit $visit, Referral $referral): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('referral.manage');
        $id = (int) $referral->id;
        $this->care->destroyReferral($visit, $referral);

        return $this->saved($visit, ['deleted_id' => $id]);
    }

    public function storeLab(Request $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('procedure.manage');
        $order = $this->care->storeLabOrder($visit, $this->actor($request), $request->all());

        return $this->saved($visit, ['lab_order' => $this->labOrder($order)]);
    }

    public function updateLab(Request $request, Visit $visit, DentalLabOrder $order): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('procedure.manage');
        $order = $this->care->updateLabOrder($visit, $order, $request->all());

        return $this->saved($visit, ['lab_order' => $this->labOrder($order)]);
    }

    public function destroyLab(Visit $visit, DentalLabOrder $order): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('procedure.manage');
        $id = $this->care->destroyLabOrder($visit, $order);

        return $this->saved($visit, ['deleted_id' => $id]);
    }

    public function finalizeRecord(Request $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('medical_record.update');
        $record = $this->care->finalizeRecord($visit, $this->actor($request));

        return $this->saved($visit, [
            'medical_record' => [
                'id' => (int) $record->id,
                'finalized_at' => $record->finalized_at?->format('c'),
                'finalized_by' => $record->finalized_by,
            ],
        ]);
    }

    public function completeAssessment(Request $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('medical_record.update');
        $result = $this->care->completeAssessment($visit, $this->actor($request));
        $record = $result['record'];

        return $this->saved($visit, [
            'assessment_status' => $this->enumValue($record->assessment_status),
            'assessment_completed_at' => $record->assessment_completed_at?->format('c'),
            'already_completed' => $result['already'],
        ]);
    }

    public function storeAmendment(Request $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('medical_record.update');
        $amendment = $this->care->storeAmendment($visit, $this->actor($request), $request->all());

        return $this->saved($visit, ['amendment' => $this->amendment($amendment)]);
    }

    public function storeSickLetter(Request $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize('medical_record.view');
        $document = $this->care->storeSickLetter($visit, $this->actor($request), $request->all());

        return $this->saved($visit, ['document' => $this->document($document)]);
    }

    public function complete(Request $request, Visit $visit): JsonResponse
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $result = $this->care->complete($visit, $this->actor($request));

        return $this->saved($visit, [
            'status' => $this->enumValue($result['visit']->status),
            'invoice_id' => $result['invoice']?->id,
            'message' => $result['message'],
        ]);
    }

    protected function authorizePdf(Visit $visit, string $ability): void
    {
        $this->guard($visit);
        $this->authorize('view', $visit);
        $this->authorize($ability);
    }

    protected function authorizeAny(string ...$abilities): void
    {
        $user = request()->user();

        foreach ($abilities as $ability) {
            if ($user?->can($ability)) {
                return;
            }
        }

        throw new AuthorizationException('This action is unauthorized.');
    }

    protected function pdfOrExisting(
        Request $request,
        Visit $visit,
        ClinicalDocumentType $type,
        ?Model $reference,
        string $number,
        callable $issue,
    ): Response {
        if ($this->tenantSuspended($request)) {
            $existing = app(ClinicalDocumentService::class)->findValid($visit, $type, $reference, $number);

            if (! $existing) {
                throw new MobileApiException(
                    'Langganan klinik habis atau ditangguhkan. Hubungi pengelola platform.',
                    'tenant_suspended',
                    403,
                );
            }

            return app(WebCareController::class)->printDocument($visit, $existing);
        }

        return $issue();
    }

    protected function tenantSuspended(Request $request): bool
    {
        return $request->user()?->tenant?->status === TenantStatus::Suspended;
    }

    protected function documentNumber(string $code, int $number, mixed $date): string
    {
        $carbon = Carbon::parse($date);
        $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];

        return sprintf('%03d/%s/%s/%s', $number, $code, $roman[(int) $carbon->format('n')], $carbon->format('Y'));
    }

    protected function guard(Visit $visit): void
    {
        $user = request()->user();

        if ($user && ! $user->belongsToTenantId($visit->tenant_id)) {
            abort(404);
        }

        $this->authorize('examination.view');
    }

    protected function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $section
     */
    protected function saved(Visit $visit, array $section): JsonResponse
    {
        return response()->json([
            'data' => ['visit_id' => $visit->id] + $section,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function tooth(OdontogramTooth $tooth): array
    {
        return [
            'id' => (int) $tooth->id,
            'tooth_number' => $tooth->tooth_number,
            'status' => $this->enumValue($tooth->status),
            'surfaces' => $tooth->surfaces ?? [],
            'conditions' => $tooth->conditions ?? [],
            'notes' => $tooth->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function diagnosis(Diagnosis $diagnosis): array
    {
        return [
            'id' => (int) $diagnosis->id,
            'kind' => $this->enumValue($diagnosis->kind),
            'rank' => $this->enumValue($diagnosis->rank),
            'tooth_number' => $diagnosis->tooth_number,
            'code' => $diagnosis->code,
            'description' => $diagnosis->description,
            'clinical_note' => $diagnosis->clinical_note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function procedure(ProcedureRecord $item): array
    {
        $item->loadMissing('procedure');

        return [
            'id' => (int) $item->id,
            'procedure_id' => $item->procedure_id ? (int) $item->procedure_id : null,
            'name' => $item->procedure?->name,
            'tooth_number' => $item->tooth_number,
            'icd9_code' => $item->icd9_code,
            'quantity' => (int) $item->quantity,
            'price_at_time' => $item->price_at_time,
            'adjustment_type' => $this->enumValue($item->adjustment_type),
            'waiver_status' => $this->enumValue($item->waiver_status),
            'billing_status' => $this->enumValue($item->billing_status),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function prescriptionItem(PrescriptionItem $item): array
    {
        $item->loadMissing(['medicine', 'ingredients']);

        return [
            'id' => (int) $item->id,
            'prescription_id' => (int) $item->prescription_id,
            'kind' => $this->enumValue($item->kind),
            'medication_type' => $this->enumValue($item->medication_type),
            'medicine_id' => $item->medicine_id ? (int) $item->medicine_id : null,
            'medicine' => $item->medicine?->name,
            'dosage' => $item->dosage,
            'frequency' => $item->frequency,
            'duration' => $item->duration,
            'course' => $item->course,
            'quantity' => (int) $item->quantity,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function referral(Referral $referral): array
    {
        return [
            'id' => (int) $referral->id,
            'referred_to' => $referral->referred_to,
            'reason' => $referral->reason,
            'notes' => $referral->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function template(PrescriptionTemplate $template): array
    {
        return [
            'id' => (int) $template->id,
            'name' => $template->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function labOrder(DentalLabOrder $order): array
    {
        return [
            'id' => (int) $order->id,
            'work_type' => $order->work_type,
            'tooth_numbers' => $order->tooth_numbers,
            'shade' => $order->shade,
            'vendor' => $order->vendor,
            'due_date' => $this->date($order->due_date),
            'status' => $this->enumValue($order->status),
            'notes' => $order->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function amendment(MedicalRecordAmendment $amendment): array
    {
        return [
            'id' => (int) $amendment->id,
            'body' => $amendment->body,
            'user_id' => $amendment->user_id ? (int) $amendment->user_id : null,
            'created_at' => $amendment->created_at?->format('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function document(ClinicalDocument $document): array
    {
        return [
            'id' => (int) $document->id,
            'type' => $this->enumValue($document->type),
            'number' => $document->number,
            'status' => $this->enumValue($document->status),
            'patient_initials' => $document->patient_initials,
            'payload' => $document->payload,
            'signed_at' => $document->signed_at?->format('c'),
        ];
    }

    protected function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    protected function date(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
