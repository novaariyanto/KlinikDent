<?php

namespace App\Http\Controllers\Api;

use App\Enums\DiagnosisRank;
use App\Http\Controllers\Clinical\PatientHistoryController as WebPatientHistoryController;
use App\Http\Controllers\Controller;
use App\Models\ClinicalDocument;
use App\Models\DentalLabOrder;
use App\Models\Diagnosis;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\Models\Visit;
use App\Support\Clinical\ExaminationQueueRow;
use App\Support\Clinical\MedicalRecordReadAudit;
use App\Support\Clinical\OdontogramTimeline;
use App\Support\Clinical\PatientHistoryService;
use App\Support\Clinical\PatientResumeService;
use App\Support\Clinical\ToothConditions;
use App\Support\Clinical\VisitSoapPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class PatientHistoryController extends Controller
{
    public function __construct(
        protected PatientHistoryService $history,
        protected PatientResumeService $resumes,
    ) {}

    public function show(Request $request, Patient $patient): JsonResponse
    {
        $user = $this->guard($request, $patient);

        app(MedicalRecordReadAudit::class)->record(
            $user,
            $patient,
            'Membaca riwayat pasien '.$patient->medical_record_number,
        );

        $tabs = $this->tabs($user);
        $tab = (string) $request->query('tab', 'kunjungan');
        $tab = array_key_exists($tab, $tabs) ? $tab : 'kunjungan';

        $stats = $this->history->stats($patient, $user);
        $data = [
            'patient' => [
                'id' => (int) $patient->id,
                'name' => $patient->name,
                'medical_record_number' => $patient->medical_record_number,
            ],
            'tab' => $tab,
            'tabs' => collect($tabs)->map(fn (array $item) => $item['label'])->all(),
            'stats' => [
                'visits' => $stats['visits'],
                'diagnoses' => $stats['diagnoses'],
                'procedures' => $stats['procedures'],
                'letters' => $stats['letters'],
                'first_visit' => $stats['first_visit']?->format('Y-m-d'),
                'last_visit' => $stats['last_visit']?->format('Y-m-d'),
            ],
            'info' => collect($this->history->importantInfo($patient, $user))
                ->map(fn (array $item) => [
                    'value' => $item['value'],
                    'state' => $item['state'],
                    'date' => $item['date']?->format('Y-m-d'),
                ])->all(),
        ];

        $data += match ($tab) {
            'ringkasan' => $this->summary($patient, $user),
            'klinis' => [
                'diagnoses' => $this->presentPage(
                    $this->history->diagnoses($patient, $user, $request),
                    fn (Diagnosis $diagnosis) => [
                        'id' => (int) $diagnosis->id,
                        'visit_id' => (int) $diagnosis->visit_id,
                        'visit_date' => $diagnosis->visit?->visit_date?->format('Y-m-d'),
                        'code' => $diagnosis->code,
                        'description' => $diagnosis->description,
                        'kind' => $diagnosis->kind?->value,
                        'kind_label' => $diagnosis->kind?->label(),
                        'rank' => $diagnosis->rank?->value,
                        'rank_label' => $diagnosis->rank?->label(),
                        'tooth_number' => $diagnosis->tooth_number,
                        'doctor' => $diagnosis->visit?->doctor?->name,
                    ],
                ),
                'procedures' => $this->presentPage(
                    $this->history->procedures($patient, $user, $request),
                    fn (ProcedureRecord $procedure) => [
                        'id' => (int) $procedure->id,
                        'visit_id' => (int) $procedure->visit_id,
                        'visit_date' => $procedure->visit?->visit_date?->format('Y-m-d'),
                        'name' => $procedure->procedure?->name ?: 'Tindakan',
                        'icd9_code' => $procedure->icd9_code,
                        'tooth_number' => $procedure->tooth_number,
                        'quantity' => (int) $procedure->quantity,
                        'doctor' => $procedure->visit?->doctor?->name,
                    ],
                ),
                'diagnosis_summary' => $this->diagnosisSummary($patient, $user),
            ],
            'odontogram' => $this->odontogram($request, $patient, $user),
            'resep' => [
                'items' => $this->presentPage(
                    $this->history->prescriptionItems($patient, $user, $request),
                    fn (PrescriptionItem $item) => [
                        'id' => (int) $item->id,
                        'prescription_id' => (int) $item->prescription_id,
                        'date' => $item->prescription?->visit?->visit_date?->format('Y-m-d'),
                        'medicine' => $item->medicine?->name ?: $item->ingredients->pluck('name')->filter()->implode(', '),
                        'dosage' => $item->dosage,
                        'frequency' => $item->frequency,
                        'course' => $item->course,
                        'quantity' => (int) $item->quantity,
                        'doctor' => $item->prescription?->doctor?->name ?: $item->prescription?->visit?->doctor?->name,
                    ],
                ),
            ],
            'dokumen' => [
                'documents' => $this->presentPage(
                    $this->history->documents($patient, $user, $request),
                    fn (array $row) => $this->documentRow($row),
                ),
                'categories' => PatientHistoryService::documentCategories(),
            ],
            'resume' => $this->resumeSection($request, $patient, $user),
            default => [
                'visits' => $this->presentPage(
                    $this->history->visits($patient, $user, $request),
                    fn (Visit $visit) => $this->visitRow($visit),
                ),
                'options' => $this->options($patient, $user),
            ],
        };

        return response()->json(['data' => $data]);
    }

    public function visit(Request $request, Patient $patient, Visit $visit): JsonResponse
    {
        $user = $this->guard($request, $patient);
        abort_unless($this->history->canSeeVisit($patient, $user, $visit), 404);

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
            $user,
            $patient,
            'Membuka rekam medis kunjungan '.(new ExaminationQueueRow($visit))->registrationNumber().' dari riwayat pasien',
        );

        $soap = new VisitSoapPresenter($visit);
        $timeline = OdontogramTimeline::for($patient);
        $record = $visit->medicalRecord;

        return response()->json([
            'data' => [
                'patient_id' => (int) $patient->id,
                'visit' => [
                    'id' => (int) $visit->id,
                    'number' => (new ExaminationQueueRow($visit))->registrationNumber(),
                    'visit_date' => $visit->visit_date?->format('Y-m-d'),
                    'time' => $visit->queue?->created_at?->format('H:i') ?? $visit->created_at?->format('H:i'),
                    'status' => $visit->status?->value,
                    'status_label' => $visit->status?->label(),
                    'doctor' => $visit->doctor?->name,
                    'branch' => $visit->branch?->name,
                    'room' => $visit->room?->name,
                    'payer' => $visit->payer?->name,
                    'finalized_at' => $record?->finalized_at?->format('c'),
                    'finalizer' => $record?->finalizer?->name,
                ],
                'soap' => [
                    'subjective' => $soap->subjective(),
                    'vitals' => $soap->vitals(),
                    'objective' => $soap->objective(),
                    'assessment' => $soap->assessment(),
                    'plan' => $soap->plan(),
                    'doctor_notes' => $soap->doctorNotes(),
                ],
                'odontogram_changes' => $this->toothChanges($timeline->changesIn($visit->id)),
            ],
        ]);
    }

    public function storeResume(Request $request, Patient $patient): JsonResponse
    {
        $user = $this->guard($request, $patient);
        $this->authorize('medical_record.update');

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

        $visits = $this->resumes->eligibleVisits($patient, $user, null, null, array_map('intval', $data['visit_ids']));

        if ($visits->isEmpty()) {
            return response()->json([
                'message' => 'Kunjungan yang dipilih tidak tersedia.',
                'errors' => ['visit_ids' => ['Kunjungan yang dipilih tidak tersedia.']],
            ], 422);
        }

        $document = $this->resumes->issue($patient, $user, $visits, $data);

        return response()->json([
            'data' => [
                'patient_id' => (int) $patient->id,
                'document' => $this->resumeRow($document),
            ],
            'message' => 'Resume medis '.$document->number.' dibuat.',
        ]);
    }

    public function resumePdf(Request $request, Patient $patient, ClinicalDocument $document): Response
    {
        $this->guard($request, $patient);
        $this->authorize('medical_record.view');

        return app(WebPatientHistoryController::class)->resumePdf($request, $patient, $document);
    }

    public function printPrescription(Request $request, Patient $patient, Prescription $prescription): Response
    {
        $this->guard($request, $patient);
        $this->authorize('prescription.view');

        return app(WebPatientHistoryController::class)->printPrescription($request, $patient, $prescription);
    }

    protected function guard(Request $request, Patient $patient): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->belongsToTenantId($patient->tenant_id), 404);
        $this->authorize('view', $patient);
        $this->authorize('patient_history.view');

        return $user;
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
    protected function summary(Patient $patient, User $user): array
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
            'recent_visits' => $recent->map(function (Visit $visit) {
                $primary = $visit->diagnoses->first(fn ($diagnosis) => $diagnosis->rank === DiagnosisRank::Primary)
                    ?? $visit->diagnoses->sortBy('id')->first();

                return [
                    'id' => (int) $visit->id,
                    'visit_date' => $visit->visit_date?->format('Y-m-d'),
                    'status' => $visit->status?->value,
                    'status_label' => $visit->status?->label(),
                    'doctor' => $visit->doctor?->name,
                    'branch' => $visit->branch?->name,
                    'chief_complaint' => $visit->medicalRecord?->chief_complaint,
                    'diagnosis' => $primary ? trim(($primary->code ? $primary->code.' ' : '').$primary->description) : null,
                    'procedures' => $visit->procedureRecords->map(fn ($procedure) => $procedure->procedure?->name)->filter()->values()->all(),
                ];
            })->values()->all(),
            'diagnosis_summary' => $this->diagnosisSummary($patient, $user),
            'current_teeth' => $this->toothSummaries(OdontogramTimeline::for($patient)->current()),
            'next_control' => $nextControl ? [
                'visit_id' => (int) $nextControl->visit_id,
                'control_date' => $nextControl->control_date?->format('Y-m-d'),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function odontogram(Request $request, Patient $patient, User $user): array
    {
        $timeline = OdontogramTimeline::for($patient);
        $visibleIds = $this->history->visitsQuery($patient, $user)->pluck('visits.id')->map(fn ($id) => (int) $id)->all();
        $changed = $timeline->changedVisits($visibleIds);

        $resolve = function (?string $key) use ($timeline, $visibleIds): ?array {
            if ($key === 'current') {
                return [
                    'key' => 'current',
                    'label' => 'Kondisi terkini (odontogram aktif)',
                    'teeth' => $this->toothSummaries($timeline->current()),
                ];
            }

            $id = (int) $key;

            if (! $id || ! in_array($id, $visibleIds, true) || ! $timeline->hasVisit($id)) {
                return null;
            }

            $visit = $timeline->visits()->firstWhere('id', $id);

            return [
                'key' => (string) $id,
                'label' => $visit->visit_date?->format('d M Y').' — '.($visit->doctor?->name ?: 'Tanpa dokter'),
                'visit_id' => (int) $id,
                'teeth' => $this->toothSummaries($timeline->stateAfter($id)),
            ];
        };

        $defaultB = $changed->first()?->id;
        $defaultA = $changed->get(1)?->id;
        $a = $resolve($request->query('oa') ?? ($defaultA ? (string) $defaultA : null));
        $b = $resolve($request->query('ob') ?? ($defaultB ? (string) $defaultB : 'current'));

        return [
            'changed_visits' => $changed->map(fn (Visit $visit) => [
                'id' => (int) $visit->id,
                'visit_date' => $visit->visit_date?->format('Y-m-d'),
                'doctor' => $visit->doctor?->name,
            ])->values()->all(),
            'compare_a' => $a,
            'compare_b' => $b,
            'diff' => $a && $b
                ? $this->toothChanges(OdontogramTimeline::diff(
                    $a['key'] === 'current' ? $timeline->current() : $timeline->stateAfter((int) $a['key']),
                    $b['key'] === 'current' ? $timeline->current() : $timeline->stateAfter((int) $b['key']),
                ))
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resumeSection(Request $request, Patient $patient, User $user): array
    {
        $from = $this->dateOrNull($request->query('resume_from'));
        $to = $this->dateOrNull($request->query('resume_to'));
        $canCreate = $user->can('medical_record.update');
        $eligible = $canCreate ? $this->resumes->eligibleVisits($patient, $user, $from, $to) : collect();
        $draft = $canCreate && $eligible->isNotEmpty() ? $this->resumes->compile($patient, $user, $eligible) : null;

        return [
            'can_create' => $canCreate,
            'from' => $from,
            'to' => $to,
            'eligible_visits' => $eligible->map(fn (Visit $visit) => [
                'id' => (int) $visit->id,
                'visit_date' => $visit->visit_date?->format('Y-m-d'),
                'number' => (new ExaminationQueueRow($visit))->registrationNumber(),
                'doctor' => $visit->doctor?->name,
                'branch' => $visit->branch?->name,
            ])->values()->all(),
            'draft' => $draft,
            'resumes' => $this->presentPage(
                $this->resumes->resumesQuery($patient, $user)->paginate(10, ['*'], 'respage')->withQueryString(),
                fn (ClinicalDocument $document) => $this->resumeRow($document),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function visitRow(Visit $visit): array
    {
        $primary = $visit->diagnoses->first(fn ($diagnosis) => $diagnosis->rank === DiagnosisRank::Primary)
            ?? $visit->diagnoses->sortBy('id')->first();
        $invoice = $visit->invoices->sortByDesc('id')->first();

        return [
            'id' => (int) $visit->id,
            'number' => (new ExaminationQueueRow($visit))->registrationNumber(),
            'visit_date' => $visit->visit_date?->format('Y-m-d'),
            'time' => $visit->queue?->created_at?->format('H:i') ?? $visit->created_at?->format('H:i'),
            'status' => $visit->status?->value,
            'status_label' => $visit->status?->label(),
            'invoice_status' => $invoice?->status?->value,
            'invoice_status_label' => $invoice?->status?->label(),
            'finalized' => (bool) $visit->medicalRecord?->finalized_at,
            'doctor' => $visit->doctor?->name,
            'branch' => $visit->branch?->name,
            'room' => $visit->room?->name,
            'payer' => $visit->payer?->name,
            'chief_complaint' => $visit->medicalRecord?->chief_complaint,
            'diagnosis' => $primary ? trim(($primary->code ? $primary->code.' ' : '').$primary->description) : null,
            'diagnosis_tooth' => $primary?->tooth_number,
            'diagnosis_more' => max(0, $visit->diagnoses->count() - ($primary ? 1 : 0)),
            'procedures' => $visit->procedureRecords
                ->map(fn ($procedure) => ($procedure->procedure?->name ?: 'Tindakan').($procedure->tooth_number ? ' ('.$procedure->tooth_number.')' : ''))
                ->values()
                ->all(),
            'prescriptions_count' => (int) $visit->prescriptions_count,
            'referrals_count' => (int) $visit->referrals_count,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function diagnosisSummary(Patient $patient, User $user): array
    {
        return $this->history->diagnosisSummary($patient, $user)
            ->map(fn (object $row) => [
                'code' => $row->code,
                'description' => $row->description,
                'visit_count' => (int) $row->visit_count,
                'first_date' => $row->first_date ? Carbon::parse($row->first_date)->format('Y-m-d') : null,
                'last_date' => $row->last_date ? Carbon::parse($row->last_date)->format('Y-m-d') : null,
            ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function options(Patient $patient, User $user): array
    {
        $options = $this->history->filterOptions($patient, $user);

        return [
            'doctors' => $options['doctors']->map(fn (User $doctor) => ['id' => (int) $doctor->id, 'name' => $doctor->name])->values()->all(),
            'branches' => $options['branches']->map(fn ($branch) => ['id' => (int) $branch->id, 'name' => $branch->name])->values()->all(),
            'rooms' => $options['rooms']->map(fn ($room) => ['id' => (int) $room->id, 'name' => $room->name])->values()->all(),
            'statuses' => $options['statuses'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function documentRow(array $row): array
    {
        unset($row['preview'], $row['print'], $row['status_class']);

        foreach (['date', 'visit_date'] as $key) {
            if (($row[$key] ?? null) instanceof \DateTimeInterface) {
                $row[$key] = $key === 'visit_date' ? $row[$key]->format('Y-m-d') : $row[$key]->format('c');
            }
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resumeRow(ClinicalDocument $document): array
    {
        return [
            'id' => (int) $document->id,
            'number' => $document->number,
            'status' => $document->status?->value,
            'status_label' => $document->status?->label(),
            'period' => [
                'from' => data_get($document->payload, 'period.from'),
                'to' => data_get($document->payload, 'period.to'),
            ],
            'visit_count' => count(data_get($document->payload, 'visit_ids', [])),
            'author' => data_get($document->payload, 'author.name'),
            'created_at' => $document->created_at?->format('c'),
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $teeth
     * @return list<array{tooth_number: string, summary: string}>
     */
    protected function toothSummaries(array $teeth): array
    {
        return collect($teeth)->map(fn (array $items, string|int $number) => [
            'tooth_number' => (string) $number,
            'summary' => ToothConditions::summary($items),
        ])->values()->all();
    }

    /**
     * @param  array<string, array{before: list<array<string, mixed>>, after: list<array<string, mixed>>}>  $changes
     * @return list<array{tooth_number: string, before: string, after: string}>
     */
    protected function toothChanges(array $changes): array
    {
        return collect($changes)->map(fn (array $change, string|int $number) => [
            'tooth_number' => (string) $number,
            'before' => ToothConditions::summary($change['before'] ?? []),
            'after' => ToothConditions::summary($change['after'] ?? []),
        ])->values()->all();
    }

    /**
     * @return array{data: mixed, meta: array{current_page: int, last_page: int, total: int}}
     */
    protected function presentPage(LengthAwarePaginator $page, callable $map): array
    {
        return [
            'data' => collect($page->items())->map($map)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ];
    }

    protected function dateOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' && strtotime($value) !== false ? date('Y-m-d', strtotime($value)) : null;
    }
}
