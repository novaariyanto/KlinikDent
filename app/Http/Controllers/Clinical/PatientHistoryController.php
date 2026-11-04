<?php

namespace App\Http\Controllers\Clinical;

use App\Enums\ClinicalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\ClinicalDocument;
use App\Models\DentalLabOrder;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use App\Support\Clinical\ExaminationQueueRow;
use App\Support\Clinical\FdiTeeth;
use App\Support\Clinical\MedicalRecordReadAudit;
use App\Support\Clinical\OdontogramTimeline;
use App\Support\Clinical\PatientHistoryService;
use App\Support\Clinical\PatientResumeService;
use App\Support\Clinical\PrintChrome;
use App\Support\Clinical\PrintSheet;
use App\Support\Clinical\ToothConditions;
use App\Support\Clinical\VisitSoapPresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PatientHistoryController extends Controller
{
    public function __construct(
        protected PatientHistoryService $history,
        protected PatientResumeService $resumes,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('patient_history.view'), 403);

        $term = trim((string) $request->query('q', ''));

        $patients = Patient::query()
            ->when($term !== '', fn ($query) => $query->search($term))
            ->with(['visits' => fn ($query) => $query->latest('visit_date')->latest('id')->limit(1)])
            ->whereHas('visits', fn ($query) => $query->visibleTo($request->user()))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('clinical.history.index', compact('patients', 'term'));
    }

    public function show(Request $request, Patient $patient): View
    {
        $this->guard($request, $patient);
        $user = $request->user();

        app(MedicalRecordReadAudit::class)->record(
            $user,
            $patient,
            'Membaca riwayat pasien '.$patient->medical_record_number,
        );

        $tabs = $this->tabs($user);
        $tab = (string) $request->query('tab', 'kunjungan');
        $tab = array_key_exists($tab, $tabs) ? $tab : 'kunjungan';

        $data = [
            'patient' => $patient,
            'tabs' => $tabs,
            'tab' => $tab,
            'stats' => $this->history->stats($patient, $user),
            'info' => $this->history->importantInfo($patient, $user),
            'filters' => $request->query(),
        ];

        $data += match ($tab) {
            'ringkasan' => $this->summaryData($patient, $user),
            'klinis' => [
                'diagnoses' => $this->history->diagnoses($patient, $user, $request),
                'procedures' => $this->history->procedures($patient, $user, $request),
                'diagnosisSummary' => $this->history->diagnosisSummary($patient, $user),
            ],
            'odontogram' => $this->odontogramData($request, $patient, $user),
            'resep' => ['items' => $this->history->prescriptionItems($patient, $user, $request)],
            'dokumen' => [
                'documents' => $this->history->documents($patient, $user, $request),
                'categories' => PatientHistoryService::documentCategories(),
            ],
            'resume' => $this->resumeData($request, $patient, $user),
            default => [
                'visits' => $this->history->visits($patient, $user, $request),
                'options' => $this->history->filterOptions($patient, $user),
            ],
        };

        return view('clinical.history.show', $data);
    }

    public function visit(Request $request, Patient $patient, Visit $visit): View
    {
        $this->guard($request, $patient);
        abort_unless($this->history->canSeeVisit($patient, $request->user(), $visit), 404);

        $visit->load([
            'doctor:id,name',
            'branch:id,name',
            'room:id,name,type',
            'payer:id,name',
            'queue',
            'medicalRecord.amendments',
            'medicalRecord.finalizer:id,name',
            'diagnoses',
            'procedureRecords.procedure:id,name,code',
            'prescriptions.doctor:id,name',
            'prescriptions.items.medicine:id,name',
            'prescriptions.items.ingredients',
            'referrals',
            'invoices:id,visit_id,number,status,total_amount,paid_amount',
        ]);
        $visit->setRelation('labOrders', DentalLabOrder::query()->where('visit_id', $visit->id)->orderBy('id')->get());

        app(MedicalRecordReadAudit::class)->record(
            $request->user(),
            $patient,
            'Membuka rekam medis kunjungan '.(new ExaminationQueueRow($visit))->registrationNumber().' dari riwayat pasien',
        );

        $timeline = OdontogramTimeline::for($patient);

        return view('clinical.history.partials.visit-detail', [
            'patient' => $patient,
            'visit' => $visit,
            'soap' => new VisitSoapPresenter($visit),
            'timeline' => $timeline,
            'odoState' => $timeline->stateAfter($visit->id),
            'odoChanges' => $timeline->changesIn($visit->id),
            'chart' => FdiTeeth::chart(),
        ]);
    }

    public function printPrescription(Request $request, Patient $patient, Prescription $prescription): Response
    {
        $this->guard($request, $patient);
        abort_unless($request->user()->can('prescription.view'), 403);

        $visit = Visit::query()->findOrFail($prescription->visit_id);
        abort_unless($this->history->canSeeVisit($patient, $request->user(), $visit), 404);

        $prescription->load(['doctor:id,name', 'items.medicine:id,name', 'items.ingredients']);
        $visit->load(['patient', 'doctor.doctorProfile', 'branch', 'room', 'payer', 'queue']);

        activity_log('printed', $prescription, ['visit_id' => $visit->id], 'Cetak ulang resep dari riwayat pasien', 'prescriptions');

        return Pdf::loadView('clinical.history.print.prescription', [
            ...PrintChrome::variables((int) $visit->tenant_id, $visit->branch),
            'branch' => $visit->branch,
            'visit' => $visit,
            'patient' => $patient,
            'prescription' => $prescription,
            'sheet' => PrintSheet::for($visit),
            'title' => 'Resep Obat',
            'letterNumber' => 'RX-'.(new ExaminationQueueRow($visit))->registrationNumber().'-'.$prescription->id,
            'signatureSrc' => app(\App\Support\Clinical\ClinicalDocumentService::class)->signatureSrc($visit->doctor?->doctorProfile?->signature_path),
        ])->setPaper('a5')->stream('resep-'.$prescription->id.'.pdf');
    }

    public function storeResume(Request $request, Patient $patient): RedirectResponse
    {
        $this->guard($request, $patient);
        abort_unless($request->user()->can('medical_record.update'), 403);

        $data = $request->validate([
            'visit_ids' => ['required', 'array', 'min:1'],
            'visit_ids.*' => ['integer'],
            'relevant_history' => ['nullable', 'string', 'max:5000'],
            'progress' => ['nullable', 'string', 'max:10000'],
            'last_condition' => ['nullable', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:5000'],
        ], [
            'visit_ids.required' => 'Pilih minimal satu kunjungan untuk resume.',
        ]);

        $visits = $this->resumes->eligibleVisits($patient, $request->user(), null, null, array_map('intval', $data['visit_ids']));

        if ($visits->isEmpty()) {
            return back()->withInput()->withErrors(['visit_ids' => 'Kunjungan yang dipilih tidak tersedia.']);
        }

        $document = $this->resumes->issue($patient, $request->user(), $visits, $data);

        return redirect()
            ->route('patients.history.show', ['patient' => $patient, 'tab' => 'resume'])
            ->with('success', 'Resume medis '.$document->number.' dibuat.')
            ->with('resume_created', $document->id);
    }

    public function resumePdf(Request $request, Patient $patient, ClinicalDocument $document): Response
    {
        $this->guard($request, $patient);
        abort_unless($request->user()->can('medical_record.view'), 403);
        abort_unless(
            $document->type === ClinicalDocumentType::Resume
                && data_get($document->payload, 'scope') === 'patient'
                && (int) data_get($document->payload, 'patient_id') === (int) $patient->id,
            404,
        );

        $visit = Visit::query()->findOrFail($document->visit_id);
        abort_unless($this->history->canSeeVisit($patient, $request->user(), $visit), 404);

        return $this->resumes->pdf($document, $request->boolean('download'));
    }

    protected function guard(Request $request, Patient $patient): void
    {
        $this->authorize('view', $patient);
        abort_unless($request->user()?->can('patient_history.view'), 403);
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    protected function tabs(User $user): array
    {
        $tabs = [
            'ringkasan' => ['label' => 'Ringkasan', 'icon' => 'bx bx-grid-alt'],
            'kunjungan' => ['label' => 'Riwayat Kunjungan', 'icon' => 'bx bx-calendar-check'],
            'klinis' => ['label' => 'Diagnosis & Tindakan', 'icon' => 'bx bx-list-check'],
            'odontogram' => ['label' => 'Riwayat Odontogram', 'icon' => 'bx bx-grid'],
            'resep' => ['label' => 'Resep & Obat', 'icon' => 'bx bx-capsule'],
            'dokumen' => ['label' => 'Dokumen Medis', 'icon' => 'bx bx-file'],
            'resume' => ['label' => 'Resume Medis', 'icon' => 'bx bx-notepad'],
        ];

        if (! $user->can('medical_record.view')) {
            unset($tabs['dokumen'], $tabs['resume']);
        }

        return $tabs;
    }

    /**
     * @return array<string, mixed>
     */
    protected function summaryData(Patient $patient, User $user): array
    {
        $recent = $this->history->visitsQuery($patient, $user)
            ->with([
                'doctor:id,name',
                'branch:id,name',
                'medicalRecord:id,visit_id,chief_complaint,control_date,followup_date',
                'diagnoses:id,visit_id,kind,rank,code,description,tooth_number',
                'procedureRecords:id,visit_id,procedure_id,tooth_number',
                'procedureRecords.procedure:id,name',
            ])
            ->latest('visit_date')
            ->latest('id')
            ->limit(3)
            ->get();

        $nextControl = \App\Models\MedicalRecord::query()
            ->whereIn('visit_id', $this->history->visitsQuery($patient, $user)->select('visits.id'))
            ->whereDate('control_date', '>=', now()->toDateString())
            ->orderBy('control_date')
            ->first(['id', 'visit_id', 'control_date']);

        return [
            'recentVisits' => $recent,
            'diagnosisSummary' => $this->history->diagnosisSummary($patient, $user),
            'currentTeeth' => OdontogramTimeline::for($patient)->current(),
            'nextControl' => $nextControl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function odontogramData(Request $request, Patient $patient, User $user): array
    {
        $timeline = OdontogramTimeline::for($patient);
        $visibleIds = $this->history->visitsQuery($patient, $user)->pluck('visits.id')->map(fn ($id) => (int) $id)->all();
        $changed = $timeline->changedVisits($visibleIds);

        $resolve = function (?string $key) use ($timeline, $visibleIds): ?array {
            if ($key === 'current') {
                return ['key' => 'current', 'label' => 'Kondisi terkini (odontogram aktif)', 'state' => $timeline->current(), 'visit' => null];
            }

            $id = (int) $key;

            if (! $id || ! in_array($id, $visibleIds, true) || ! $timeline->hasVisit($id)) {
                return null;
            }

            $visit = $timeline->visits()->firstWhere('id', $id);

            return [
                'key' => (string) $id,
                'label' => $visit->visit_date?->format('d M Y').' — '.($visit->doctor?->name ?: 'Tanpa dokter'),
                'state' => $timeline->stateAfter($id),
                'visit' => $visit,
            ];
        };

        $defaultB = $changed->first()?->id;
        $defaultA = $changed->get(1)?->id;

        $a = $resolve($request->query('oa') ?? ($defaultA ? (string) $defaultA : null));
        $b = $resolve($request->query('ob') ?? ($defaultB ? (string) $defaultB : 'current'));

        return [
            'timeline' => $timeline,
            'changedVisits' => $changed,
            'compareOptions' => $timeline->visits()
                ->filter(fn (Visit $visit) => in_array($visit->id, $visibleIds, true))
                ->reverse()
                ->values(),
            'compareA' => $a,
            'compareB' => $b,
            'compareDiff' => $a && $b ? OdontogramTimeline::diff($a['state'], $b['state']) : [],
            'chart' => FdiTeeth::chart(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resumeData(Request $request, Patient $patient, User $user): array
    {
        $from = $this->dateOrNull($request->query('resume_from'));
        $to = $this->dateOrNull($request->query('resume_to'));
        $canCreate = $user->can('medical_record.update');
        $draft = null;
        $eligible = collect();

        if ($canCreate) {
            $eligible = $this->resumes->eligibleVisits($patient, $user, $from, $to);
            $draft = $eligible->isNotEmpty() ? $this->resumes->compile($patient, $user, $eligible) : null;
        }

        return [
            'resumes' => $this->resumes->resumesQuery($patient, $user)->paginate(10, ['*'], 'respage')->withQueryString(),
            'canCreateResume' => $canCreate,
            'eligibleVisits' => $eligible,
            'resumeDraft' => $draft,
            'resumeFrom' => $from,
            'resumeTo' => $to,
        ];
    }

    protected function dateOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' && strtotime($value) !== false ? date('Y-m-d', strtotime($value)) : null;
    }
}
