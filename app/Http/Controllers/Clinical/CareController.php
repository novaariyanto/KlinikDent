<?php

namespace App\Http\Controllers\Clinical;

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
use App\Http\Requests\Clinical\UpdateMedicalRecordRequest;
use App\Http\Requests\Clinical\UpdateSystemicHistoryRequest;
use App\Http\Requests\Clinical\UpdateToothRequest;
use App\Http\Requests\Clinical\UpdateVitalsRequest;
use App\Models\Diagnosis;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\Procedure;
use App\Models\ProcedureRecord;
use App\Models\Referral;
use App\Models\Visit;
use App\Support\Clinical\CareExamOptions;
use App\Support\Clinical\CareProgress;
use App\Support\Clinical\CareTabs;
use App\Support\Clinical\ClinicalCareService;
use App\Support\Clinical\FdiTeeth;
use App\Support\Clinical\IcdCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CareController extends Controller
{
    public function __construct(protected ClinicalCareService $care) {}

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

        $visit->load([
            'patient',
            'queue',
            'doctor',
            'room',
            'payer',
            'branch',
            'diagnoses',
            'procedureRecords.procedure',
            'prescriptions.items.medicine',
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
            ];
        }

        $record->update($data);

        activity_log('updated', $record, $data, 'Updated medical record', 'medical_records');

        $tab = array_key_exists('plan_instructions', $request->validated()) || array_key_exists('clinical_notes', $request->validated())
            ? 'instruksi'
            : 'keluhan';

        return $this->respondCare($visit, $tab, 'Rekam medis disimpan.');
    }

    public function updateExamination(UpdateExaminationRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        $record->update($request->validated());

        activity_log('updated', $record, $request->validated(), 'Updated initial examination', 'medical_records');

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
        $record->update(['vital_signs' => $vitals]);

        activity_log('updated', $record, $vitals, 'Updated vital signs', 'medical_records');

        return $this->respondCare($visit, 'screening', 'Tanda vital disimpan.');
    }

    public function updateAnamnesis(UpdateAnamnesisRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        $record->update($request->validated());

        activity_log('updated', $record, $request->validated(), 'Updated anamnesis', 'medical_records');

        return $this->respondCare($visit, 'keluhan', 'Anamnesis disimpan.');
    }

    public function updateNotes(UpdateCareNotesRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        $record->update($request->validated());

        activity_log('updated', $record, $request->validated(), 'Updated care notes', 'medical_records');

        return $this->respondCare($visit, 'instruksi', 'Catatan pelayanan disimpan.');
    }

    public function updateTooth(UpdateToothRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $status = $data['status'] instanceof ToothStatus
            ? $data['status']
            : ToothStatus::from($data['status']);

        $this->care->updateTooth(
            $visit,
            $request->user(),
            $data['tooth_number'],
            $status,
            $data['notes'] ?? null,
            $data['surfaces'] ?? [],
        );

        activity_log('updated', $visit, $data, 'Updated odontogram tooth '.$data['tooth_number'], 'odontogram');

        return $this->respondCare($visit, 'odontogram', 'Status gigi '.$data['tooth_number'].' diperbarui.');
    }

    public function updateDentalExam(UpdateDentalExamRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $record = $this->care->ensureRecord($visit);
        $record->update(['dental_exam' => $request->validated()]);

        activity_log('updated', $record, $request->validated(), 'Updated dental examination', 'medical_records');

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

        return response()->json(IcdCatalog::search((string) $request->query('q', '')));
    }

    public function storeDiagnosis(StoreDiagnosisRequest $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $diagnosis = $visit->diagnoses()->create($request->validated());

        activity_log('created', $diagnosis, $request->validated(), 'Added diagnosis', 'diagnoses');

        return $this->respondCare($visit, 'diagnosis', 'Diagnosis ditambahkan.');
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
        $item = $prescription->items()->create($request->validated());

        activity_log('created', $item, $request->validated(), 'Added prescription item', 'prescriptions');

        return $this->respondCare($visit, 'resep', 'Obat ditambahkan ke resep draft.');
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

    public function destroyReferral(Visit $visit, Referral $referral): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless(request()->user()?->can('referral.manage'), 403);
        abort_unless((int) $referral->visit_id === (int) $visit->id, 404);

        activity_log('deleted', $referral, ['referred_to' => $referral->referred_to], 'Deleted referral', 'referrals');
        $referral->delete();

        return $this->respondCare($visit, 'rujukan', 'Rujukan dihapus.');
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

        $visit->update(['status' => VisitStatus::Done]);

        activity_log('updated', $visit, ['status' => VisitStatus::Done->value], 'Completed clinical visit', 'visits');

        return redirect()
            ->route('care.show', $visit)
            ->with('success', 'Pelayanan kunjungan diselesaikan.');
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

    protected function canWriteAny(\App\Models\User $user): bool
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
