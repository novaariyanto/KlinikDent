<?php

namespace App\Http\Controllers\Clinical;

use App\Enums\MedicalLetterStatus;
use App\Enums\MedicalLetterType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\StoreMedicalLetterRequest;
use App\Models\Branch;
use App\Models\MedicalLetter;
use App\Models\User;
use App\Models\Visit;
use App\Support\Clinical\ClinicalDocumentService;
use App\Support\Clinical\MedicalLetterService;
use App\Support\Clinical\PrintChrome;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class MedicalLetterController extends Controller
{
    public function __construct(protected MedicalLetterService $letters) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('medical_record.view'), 403);

        $columns = [
            ['data' => 'number', 'name' => 'number', 'title' => 'Nomor surat'],
            ['data' => 'issued_at', 'name' => 'issued_at', 'title' => 'Tanggal'],
            ['data' => 'patient', 'name' => 'patient', 'title' => 'Pasien', 'orderable' => false],
            ['data' => 'rm', 'name' => 'rm', 'title' => 'No. RM', 'orderable' => false],
            ['data' => 'type_label', 'name' => 'type', 'title' => 'Jenis surat'],
            ['data' => 'doctor', 'name' => 'doctor', 'title' => 'Dokter', 'orderable' => false, 'searchable' => false],
            ['data' => 'branch', 'name' => 'branch', 'title' => 'Cabang', 'orderable' => false, 'searchable' => false],
            ['data' => 'status_label', 'name' => 'status', 'title' => 'Status'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Aksi', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];

        $user = $request->user();
        $branches = $user->applyBranchLimit(Branch::query()->orderBy('name'), 'id')->get();
        $doctors = User::query()->whereHas('doctorProfile')->orderBy('name')->get(['id', 'name']);

        return view('clinical.letters.index', [
            'columns' => $columns,
            'branches' => $branches,
            'doctors' => $doctors,
            'types' => MedicalLetterType::options(),
            'statuses' => MedicalLetterStatus::options(),
            'filters' => $request->only(['date_from', 'date_to', 'type', 'doctor_id', 'branch_id', 'status', 'patient_id']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('medical_record.view'), 403);

        $query = MedicalLetter::query()->with(['patient', 'doctor', 'branch']);
        $this->applyFilters($request, $query);

        return DataTables::eloquent($query)
            ->editColumn('issued_at', fn (MedicalLetter $letter) => $letter->issued_at?->format('d M Y H:i') ?: '-')
            ->addColumn('patient', fn (MedicalLetter $letter) => e($letter->patient?->name ?: '-'))
            ->addColumn('rm', fn (MedicalLetter $letter) => e($letter->patient?->medical_record_number ?: '-'))
            ->addColumn('type_label', fn (MedicalLetter $letter) => e($letter->type->label()))
            ->addColumn('doctor', fn (MedicalLetter $letter) => e($letter->doctor?->name ?: data_get($letter->snapshot, 'doctor.name', '-')))
            ->addColumn('branch', fn (MedicalLetter $letter) => e($letter->branch?->name ?: '-'))
            ->addColumn('status_label', fn (MedicalLetter $letter) => '<span class="'.$letter->status->badgeClass().'">'.e($letter->status->label()).'</span>')
            ->addColumn('action', fn (MedicalLetter $letter) => view('clinical.letters.partials.actions', compact('letter'))->render())
            ->filterColumn('patient', function ($query, $keyword) {
                $like = '%'.$keyword.'%';
                $query->whereHas('patient', function ($patient) use ($like) {
                    $patient->where('name', 'like', $like)
                        ->orWhere('medical_record_number', 'like', $like);
                });
            })
            ->filterColumn('rm', function ($query, $keyword) {
                $query->whereHas('patient', fn ($patient) => $patient->where('medical_record_number', 'like', '%'.$keyword.'%'));
            })
            ->rawColumns(['status_label', 'action'])
            ->toJson();
    }

    public function create(Visit $visit): View
    {
        $this->authorize('view', $visit);
        $this->letters->assertCanIssue(request()->user(), $visit);
        $visit->loadMissing(['patient', 'doctor.doctorProfile', 'branch', 'diagnoses', 'medicalRecord', 'procedureRecords.procedure']);

        return view('clinical.letters.form', $this->formData($visit));
    }

    public function preview(StoreMedicalLetterRequest $request, Visit $visit): View
    {
        $this->letters->assertCanIssue($request->user(), $visit);
        $revises = null;

        if ($request->filled('revises_id')) {
            $revises = MedicalLetter::query()
                ->whereKey($request->integer('revises_id'))
                ->where('visit_id', $visit->id)
                ->firstOrFail();
            abort_unless($revises->isIssued(), 422, 'Hanya surat yang masih berlaku yang dapat direvisi.');
        }

        $letter = $this->letters->draft($visit, $request->user(), $request->validated(), $revises);

        return $this->renderLetter($letter, $request->validated(), draft: true);
    }

    public function frame(Request $request, Visit $visit): View
    {
        $this->authorize('view', $visit);
        $this->letters->assertCanIssue($request->user(), $visit);

        $data = $request->except(['_token']);
        $revises = null;

        if ($request->filled('revises_id')) {
            $revises = MedicalLetter::query()
                ->whereKey($request->integer('revises_id'))
                ->where('visit_id', $visit->id)
                ->first();
            if ($revises && ! $revises->isIssued()) {
                $revises = null;
            }
        }

        try {
            $letter = $this->letters->draft($visit, $request->user(), $data, $revises);
        } catch (ValidationException $exception) {
            abort(422, collect($exception->errors())->flatten()->first() ?: 'Data surat belum lengkap.');
        }

        return view('clinical.letters.print', [
            ...$this->printData($letter),
            'draft' => true,
            'interactive' => false,
        ]);
    }

    public function store(StoreMedicalLetterRequest $request, Visit $visit): RedirectResponse
    {
        $letter = $this->letters->issue($visit, $request->user(), $request->validated());

        return redirect()
            ->route('medical-letters.show', $letter)
            ->with('success', 'Surat '.$letter->number.' diterbitkan.');
    }

    public function revise(MedicalLetter $letter): View
    {
        $letter->loadMissing('visit.patient', 'visit.doctor.doctorProfile', 'visit.branch', 'visit.diagnoses', 'visit.medicalRecord', 'visit.procedureRecords.procedure');
        abort_unless($letter->visit, 404);
        $this->letters->assertCanIssue(request()->user(), $letter->visit);
        abort_unless($letter->isIssued(), 422, 'Hanya surat yang masih berlaku yang dapat direvisi.');

        return view('clinical.letters.form', $this->formData($letter->visit, $letter));
    }

    public function reviseStore(StoreMedicalLetterRequest $request, MedicalLetter $letter): RedirectResponse
    {
        abort_unless($letter->visit_id, 404);
        $visit = $letter->visit()->firstOrFail();
        $issued = $this->letters->issue($visit, $request->user(), $request->validated(), $letter);

        return redirect()
            ->route('medical-letters.show', $issued)
            ->with('success', 'Revisi diterbitkan sebagai '.$issued->number.'. Surat sebelumnya ditandai direvisi.');
    }

    public function show(Request $request, MedicalLetter $letter): View
    {
        abort_unless($request->user()?->can('medical_record.view'), 403);
        $this->guardBranch($request->user(), $letter);

        return $this->renderLetter($letter, autoprint: $request->boolean('cetak'));
    }

    public function pdf(Request $request, MedicalLetter $letter): Response
    {
        abort_unless($request->user()?->can('medical_record.view'), 403);
        $this->guardBranch($request->user(), $letter);
        $pdf = Pdf::loadView('clinical.letters.print', $this->printData($letter))->setPaper('a4');
        $name = $letter->number.'.pdf';

        return $request->boolean('download') ? $pdf->download($name) : $pdf->stream($name);
    }

    public function cancel(Request $request, MedicalLetter $letter): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $this->letters->cancel($letter, $request->user(), $data['reason']);

        return redirect()
            ->route('medical-letters.show', $letter)
            ->with('success', 'Surat dibatalkan. Riwayatnya tetap tersimpan.');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<MedicalLetter>  $query
     */
    protected function applyFilters(Request $request, $query): void
    {
        $user = $request->user();
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $user->applyBranchLimit($query, 'branch_id', $branchId);

        if ($request->filled('date_from')) {
            $query->whereDate('issued_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('issued_at', '<=', $request->date('date_to'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->integer('doctor_id'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }
    }

    protected function guardBranch(User $user, MedicalLetter $letter): void
    {
        abort_unless($user->canAccessBranch((int) $letter->branch_id), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(Visit $visit, ?MedicalLetter $letter = null): array
    {
        $defaults = $this->letters->defaults($visit);
        $requestedType = MedicalLetterType::tryFrom((string) request()->query('type', ''));
        if ($requestedType) {
            $defaults['type'] = $requestedType->value;
        }
        $suggestions = [];

        foreach (MedicalLetterType::cases() as $type) {
            $suggestions[$type->value] = $this->letters->suggestedBody($visit, [...$defaults, 'type' => $type->value]);
        }

        if ($letter) {
            $payload = $letter->payload ?? [];
            $defaults = array_merge($defaults, $payload, [
                'type' => $letter->type->value,
                'issued_on' => $letter->issued_at?->toDateString() ?: $defaults['issued_on'],
                'purpose' => $letter->purpose,
                'body' => $letter->body,
                'address_override' => data_get($letter->snapshot, 'patient.address'),
            ]);
            $suggestions[$letter->type->value] = (string) $letter->body;
        }

        $actor = request()->user();
        $draft = $actor ? $this->letters->draft($visit, $actor, $defaults, $letter) : null;
        $snapshot = $draft?->snapshot ?? [];

        return [
            'visit' => $visit,
            'patient' => $visit->patient,
            'letter' => $letter,
            'defaults' => $defaults,
            'suggestions' => $suggestions,
            'types' => MedicalLetterType::options(),
            'kinds' => \App\Enums\DiagnosisKind::options(),
            'chrome' => PrintChrome::variables((int) $visit->tenant_id, $visit->branch),
            'snapshot' => $snapshot,
            'signatureSrc' => app(ClinicalDocumentService::class)->signatureSrc($draft?->signature_path),
            'previewHtml' => $draft
                ? view('clinical.letters.print', [
                    ...$this->printData($draft),
                    'draft' => true,
                    'interactive' => false,
                ])->render()
                : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function renderLetter(MedicalLetter $letter, array $fields = [], bool $draft = false, bool $autoprint = false): View
    {
        return view('clinical.letters.print', [
            ...$this->printData($letter),
            'interactive' => true,
            'draft' => $draft,
            'fields' => $fields,
            'autoprint' => $autoprint,
            'success' => session('success'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function printData(MedicalLetter $letter): array
    {
        $letter->loadMissing(['visit.doctor.doctorProfile', 'branch']);
        $snapshot = $letter->snapshot ?? [];
        $clinic = $snapshot['clinic'] ?? [];
        $chrome = PrintChrome::variables((int) $letter->tenant_id, $letter->branch);

        if (filled($clinic['name'] ?? null)) {
            $chrome['clinicName'] = $clinic['name'];
        }

        if (filled($clinic['address'] ?? null)) {
            $chrome['clinicAddressLines'] = [$clinic['address']];
        }

        if (filled($clinic['phone'] ?? null)) {
            $chrome['clinicPhone'] = $clinic['phone'];
        }

        $chrome['printCity'] = $clinic['city'] ?? $chrome['printCity'];

        return [
            ...$chrome,
            'title' => $letter->type?->label() ?: 'Surat Keterangan Medis',
            'letter' => $letter,
            'visit' => $letter->visit,
            'branch' => $letter->branch,
            'patient' => $letter->patient,
            'signatureSrc' => app(ClinicalDocumentService::class)->signatureSrc($letter->signature_path),
            'documentHash' => hash('sha256', implode('|', [
                'SKM',
                (string) $letter->number,
                (string) $letter->type?->value,
                (string) $letter->body,
                json_encode($letter->payload),
            ])),
            'interactive' => false,
            'draft' => false,
            'fields' => [],
            'autoprint' => false,
            'success' => null,
        ];
    }
}
