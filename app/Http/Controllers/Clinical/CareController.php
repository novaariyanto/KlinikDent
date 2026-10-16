<?php

namespace App\Http\Controllers\Clinical;

use App\Enums\PrescriptionStatus;
use App\Enums\QueueStatus;
use App\Enums\ToothStatus;
use App\Enums\ToothSurface;
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
use App\Http\Requests\Clinical\UpdateMedicalRecordRequest;
use App\Http\Requests\Clinical\UpdatePrescriptionItemRequest;
use App\Http\Requests\Clinical\UpdateProcedureRecordRequest;
use App\Http\Requests\Clinical\UpdateSystemicHistoryRequest;
use App\Http\Requests\Clinical\UpdateToothRequest;
use App\Http\Requests\Clinical\UpdateVitalsRequest;
use App\Models\Diagnosis;
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
use App\Support\Clinical\CareExamOptions;
use App\Support\Clinical\CareProgress;
use App\Support\Clinical\CareTabs;
use App\Support\Clinical\ClinicalCareService;
use App\Support\Clinical\FdiTeeth;
use App\Support\Clinical\IcdCatalog;
use App\Support\ClinicSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
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
            'teeth' => $teeth,
            'toothStatuses' => ToothStatus::options(),
            'toothStatusMeta' => collect(ToothStatus::cases())->mapWithKeys(fn (ToothStatus $status) => [
                $status->value => [
                    'label' => $status->label(),
                    'color' => $status->color(),
                    'symbol' => $status->symbol(),
                    'whole' => $status->isWholeTooth(),
                    'needsSurface' => $status->needsSurface(),
                ],
            ])->all(),
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
            ],
            'dentalExam' => $record?->dental_exam ?? [],
            'systemicHistory' => $record?->systemic_history ?? [],
            'procedures' => Procedure::query()->active()->orderBy('name')->get(),
            'medicines' => Medicine::query()->active()->orderBy('name')->get(),
            'history' => $history,
            'careQueues' => $careQueues,
        ]);
    }

    public function updateRecord(UpdateMedicalRecordRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        $data = $request->validated();

        if (isset($data['plan_instructions'])) {
            $items = $data['plan_instructions']['items'] ?? [];
            $extra = trim((string) ($data['plan_instructions']['extra'] ?? ''));
            $prognosis = trim((string) ($data['plan_instructions']['prognosis'] ?? ''));
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
                'items' => array_values($items),
                'extra' => $extra,
                'prognosis' => $prognosis,
            ];
        }

        $before = activity_snapshot($record);
        $record->update($data);

        activity_audit('updated', $record, $before, 'Updated medical record', 'medical_records');

        $tab = array_key_exists('plan_instructions', $request->validated()) || array_key_exists('clinical_notes', $request->validated())
            ? 'instruksi'
            : 'keluhan';

        return $this->respondCare($visit, $tab, 'Rekam medis disimpan.');
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
        $vitals = collect($request->validated())->only([
            'blood_pressure',
            'pulse',
            'temperature',
            'respiration',
            'weight',
            'height',
        ])->all();
        $before = activity_snapshot($record);
        $record->update(['vital_signs' => $vitals]);

        activity_audit('updated', $record, $before, 'Updated vital signs', 'medical_records');

        return $this->respondCare($visit, 'screening', 'Tanda vital disimpan.');
    }

    public function updateAnamnesis(UpdateAnamnesisRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        $before = activity_snapshot($record);
        $record->update($request->validated());

        activity_audit('updated', $record, $before, 'Updated anamnesis', 'medical_records');

        return $this->respondCare($visit, 'keluhan', 'Anamnesis disimpan.');
    }

    public function updateNotes(UpdateCareNotesRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        $before = activity_snapshot($record);
        $record->update($request->validated());

        activity_audit('updated', $record, $before, 'Updated care notes', 'medical_records');

        return $this->respondCare($visit, 'instruksi', 'Catatan pelayanan disimpan.');
    }

    public function updateTooth(UpdateToothRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $status = $data['status'] instanceof ToothStatus
            ? $data['status']
            : ToothStatus::from($data['status']);

        $tooth = $this->care->updateTooth(
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
        $data = $request->validated();
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
        } elseif (is_array($ohis) && ! is_numeric($ohis['debris_index'] ?? null) && ! is_numeric($ohis['calculus_index'] ?? null)) {
            unset($data['ohis']);
        }

        $record = $this->care->ensureRecord($visit);
        $record->update(['dental_exam' => $data]);

        activity_log('updated', $record, $data, 'Updated dental examination', 'medical_records');

        return $this->respondCare($visit, 'pemeriksaan', 'Pemeriksaan gigi & mulut disimpan.');
    }

    public function updateSystemicHistory(UpdateSystemicHistoryRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['none'])) {
            $data['conditions'] = [];
        }

        $record = $this->care->ensureRecord($visit);
        $record->update(['systemic_history' => $data]);

        activity_log('updated', $record, $data, 'Updated systemic history', 'medical_records');

        return $this->respondCare($visit, 'screening', 'Riwayat penyakit sistemik disimpan.');
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

    public function storeDiagnosis(StoreDiagnosisRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $diagnosis = $visit->diagnoses()->create($request->validated());

        activity_log('created', $diagnosis, $request->validated(), 'Added diagnosis', 'diagnoses');

        return $this->respondCare($visit, 'diagnosis', 'Diagnosis ditambahkan.');
    }

    public function updateDiagnosis(StoreDiagnosisRequest $request, Visit $visit, Diagnosis $diagnosis): RedirectResponse|JsonResponse
    {
        abort_unless((int) $diagnosis->visit_id === (int) $visit->id, 404);

        $before = activity_snapshot($diagnosis);
        $diagnosis->update($request->validated());

        activity_audit('updated', $diagnosis, $before, 'Updated diagnosis', 'diagnoses');

        return $this->respondCare($visit, 'diagnosis', 'Diagnosis diperbarui.');
    }

    public function destroyDiagnosis(Visit $visit, Diagnosis $diagnosis): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('diagnosis.manage'), 403);
        abort_unless((int) $diagnosis->visit_id === (int) $visit->id, 404);
        abort_unless($visit->status !== VisitStatus::Cancelled, 403);

        activity_log('deleted', $diagnosis, ['code' => $diagnosis->code], 'Deleted diagnosis', 'diagnoses');
        $diagnosis->delete();

        return $this->respondCare($visit, 'diagnosis', 'Diagnosis dihapus.');
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
        );

        activity_log('created', $record, [
            'procedure_id' => $record->procedure_id,
            'price_at_time' => $record->price_at_time,
        ], 'Added procedure record', 'procedure_records');

        return $this->respondCare(
            $visit,
            'tindakan',
            'Tindakan ditambahkan. Tarif tersimpan Rp '.number_format((float) $record->price_at_time, 0, ',', '.').'.',
        );
    }

    public function updateProcedure(UpdateProcedureRecordRequest $request, Visit $visit, ProcedureRecord $procedureRecord): RedirectResponse|JsonResponse
    {
        $before = activity_snapshot($procedureRecord);
        $procedureRecord->update([
            'icd9_code' => $request->validated('icd9_code'),
        ]);

        activity_audit('updated', $procedureRecord, $before, 'Updated procedure ICD-9-CM', 'procedure_records');

        return $this->respondCare($visit, 'tindakan', 'Kode ICD-9-CM tindakan disimpan.');
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

    public function storePrescriptionItem(StorePrescriptionItemRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $prescription = $this->care->draftPrescription($visit, $request->user());
        $data = $request->validated();
        $ingredients = $data['ingredients'] ?? [];
        unset($data['ingredients']);
        $data['kind'] = $data['kind'] ?? \App\Enums\PrescriptionItemKind::Ready->value;
        $item = $prescription->items()->create($data);

        foreach ($ingredients as $ingredient) {
            if (blank($ingredient['name'] ?? null)) {
                continue;
            }

            $stockMedicine = filled($ingredient['medicine_id'] ?? null)
                ? \App\Models\Medicine::query()->find($ingredient['medicine_id'])
                : null;

            $item->ingredients()->create([
                'medicine_id' => $ingredient['medicine_id'] ?: null,
                'name' => $ingredient['name'],
                'unit' => $ingredient['unit'] ?? null,
                'quantity' => $ingredient['quantity'],
                'strength_value' => $ingredient['strength_value']
                    ?? $stockMedicine?->strength_value,
                'strength_unit' => $ingredient['strength_unit']
                    ?? $stockMedicine?->strength_unit,
            ]);
        }

        activity_log('created', $item, $request->validated(), 'Added prescription item', 'prescriptions');

        return $this->respondCare($visit, 'resep', 'Obat ditambahkan ke resep draft.');
    }

    public function updatePrescriptionItem(UpdatePrescriptionItemRequest $request, Visit $visit, PrescriptionItem $prescriptionItem): RedirectResponse|JsonResponse
    {
        $prescriptionItem->update($request->validated());

        activity_log('updated', $prescriptionItem, $request->validated(), 'Updated prescription item', 'prescriptions');

        return $this->respondCare($visit, 'resep', 'Item resep diperbarui.');
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

        return $this->respondCare($visit, 'resep', 'Item resep dihapus.');
    }

    public function sendPrescription(Visit $visit, Prescription $prescription): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('prescription.update'), 403);
        abort_unless((int) $prescription->visit_id === (int) $visit->id, 404);
        abort_unless($prescription->isDraft(), 403);
        abort_unless($prescription->items()->exists(), 422);

        $prescription->update(['status' => PrescriptionStatus::Sent]);

        activity_log('updated', $prescription, ['status' => PrescriptionStatus::Sent->value], 'Sent prescription to pharmacy', 'prescriptions');

        return $this->respondCare($visit, 'resep', 'Resep dikirim ke farmasi.');
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
        $tenantId = (int) $visit->tenant_id;
        $settings = ClinicSettings::current($tenantId);
        $printedAt = now();

        return [
            'clinicName' => ClinicSettings::name($tenantId),
            'clinicTagline' => $settings['tagline'] ?: null,
            'clinicLegalName' => $settings['legal_name'] ?: null,
            'clinicAddressLines' => ClinicSettings::addressLines($tenantId) ?: array_values(array_filter([
                $visit->branch?->address,
            ])),
            'clinicPhone' => $settings['phone'] ?: $visit->branch?->phone,
            'clinicEmail' => $settings['email'] ?: null,
            'clinicWebsite' => $settings['website'] ?: null,
            'clinicLicense' => $settings['license_number'] ?: null,
            'clinicNpwp' => $settings['npwp'] ?: null,
            'picName' => $settings['pic_name'] ?: null,
            'picSip' => $settings['pic_sip'] ?: null,
            'printCity' => $settings['print_city'] ?: ($settings['city'] ?: $visit->branch?->name),
            'printFooter' => $settings['print_footer'] ?: null,
            'branch' => $visit->branch,
            'visit' => $visit,
            'patient' => $visit->patient,
            'logoSrc' => ClinicSettings::printLogoSrc($tenantId),
            'printedAt' => $printedAt,
            'printedDateLabel' => $this->indonesianDate($printedAt),
            'visitDateLabel' => $this->indonesianDate($visit->visit_date ?? $printedAt),
            'signatureSrc' => app(\App\Support\Clinical\ClinicalDocumentService::class)->signatureSrc($visit->doctor?->doctorProfile?->signature_path),
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
            'title' => $document->type->label(),
            'referral' => $document->reference_type === \App\Models\Referral::class ? $document->reference : $visit->referrals()->first(),
            'diagnoses' => $visit->diagnoses,
        ])->setPaper('a4')->stream($document->verification_code.'.pdf');
    }

    public function complete(Visit $visit): RedirectResponse
    {
        $this->authorize('view', $visit);
        abort_unless($visit->status->isOpen(), 403);

        $queue = $visit->queue;

        if ($queue) {
            $this->authorize('complete', $queue);
            $queue->update(['status' => QueueStatus::Done]);
        } else {
            abort_unless(request()->user()?->can('queue.manage'), 403);
        }

        $before = activity_snapshot($visit);
        $visit->update(['status' => VisitStatus::Done]);

        $invoice = $this->billing->generateForVisit($visit->fresh(), request()->user());

        activity_audit('updated', $visit, $before, 'Completed clinical visit', 'visits', [
            'status' => VisitStatus::Done->value,
        ]);

        $message = 'Pelayanan kunjungan diselesaikan.';
        if ($invoice) {
            $message .= ' Tagihan '.$invoice->number.' dibuat.';
        }

        return redirect()
            ->route('care.show', $visit)
            ->with('success', $message);
    }

    protected function respondTooth(Visit $visit, OdontogramTooth $tooth, string $message): RedirectResponse|JsonResponse
    {
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'section' => 'odontogram',
                'tooth' => [
                    'number' => $tooth->tooth_number,
                    'status' => $tooth->status->value,
                    'status_label' => $tooth->status->label(),
                    'color' => $tooth->status->color(),
                    'surfaces' => $tooth->surfaces ?? [],
                    'surface_labels' => ToothSurface::shortList($tooth->surfaces ?? []),
                    'notes' => $tooth->notes,
                ],
            ]);
        }

        return $this->respondCare($visit, 'odontogram', $message);
    }

    protected function respondCare(Visit $visit, string $section, string $message): RedirectResponse|JsonResponse
    {
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'section' => $section,
            ]);
        }

        return redirect()
            ->route('care.show', ['visit' => $visit, 'tab' => $section])
            ->with('success', $message);
    }

    protected function authorizeCare(Request $request, Visit $visit): void
    {
        $this->authorize('view', $visit);
        abort_unless(CareTabs::canAccessCare($request->user()), 403);
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
