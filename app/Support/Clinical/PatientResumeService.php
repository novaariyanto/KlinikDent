<?php

namespace App\Support\Clinical;

use App\Enums\ClinicalDocumentType;
use App\Enums\DiagnosisRank;
use App\Enums\VisitStatus;
use App\Models\ClinicalDocument;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Support\Registration\DocumentSequenceGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

/**
 * Resume medis lintas kunjungan. Isi dibekukan di payload dokumen saat dibuat,
 * sehingga tidak berubah bila rekam medis kunjungan diperbarui kemudian.
 */
class PatientResumeService
{
    public function __construct(
        protected PatientHistoryService $history,
        protected ClinicalDocumentService $documents,
        protected DocumentSequenceGenerator $sequences,
    ) {}

    /**
     * @return Builder<ClinicalDocument>
     */
    public function resumesQuery(Patient $patient, User $user): Builder
    {
        return ClinicalDocument::query()
            ->where('type', ClinicalDocumentType::Resume)
            ->where('payload->scope', 'patient')
            ->whereIn('visit_id', $this->history->visitsQuery($patient, $user)->select('visits.id'))
            ->latest('id');
    }

    /**
     * @param  list<int>|null  $visitIds
     * @return Collection<int, Visit>
     */
    public function eligibleVisits(Patient $patient, User $user, ?string $from = null, ?string $to = null, ?array $visitIds = null): Collection
    {
        return $this->history->visitsQuery($patient, $user)
            ->where('status', '!=', VisitStatus::Cancelled)
            ->when($from, fn ($q) => $q->whereDate('visit_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('visit_date', '<=', $to))
            ->when($visitIds !== null, fn ($q) => $q->whereIn('visits.id', $visitIds ?: [0]))
            ->with([
                'doctor:id,name',
                'branch:id,name',
                'medicalRecord',
                'diagnoses',
                'procedureRecords.procedure:id,name',
                'prescriptions.items.medicine:id,name',
                'prescriptions.items.ingredients',
                'referrals',
            ])
            ->orderBy('visit_date')
            ->orderBy('visits.id')
            ->get();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return array<string, mixed>
     */
    public function compile(Patient $patient, User $user, Collection $visits): array
    {
        $first = $visits->first();
        $last = $visits->last();
        $info = $this->history->importantInfo($patient, $user);

        $diagnoses = $visits->flatMap(fn (Visit $visit) => $visit->diagnoses->map(fn ($d) => [
            'key' => filled($d->code) ? $d->code : mb_strtolower((string) $d->description),
            'code' => $d->code,
            'description' => $d->description,
            'tooth' => $d->tooth_number,
            'primary' => $d->rank === DiagnosisRank::Primary,
            'kind' => $d->kind?->label(),
            'date' => $visit->visit_date?->toDateString(),
            'visit_id' => $visit->id,
        ]))
            ->groupBy('key')
            ->map(fn (Collection $rows) => [
                'code' => $rows->first()['code'],
                'description' => $rows->last()['description'],
                'teeth' => $rows->pluck('tooth')->filter()->unique()->sort()->values()->all(),
                'primary' => $rows->contains('primary', true),
                'kind' => $rows->last()['kind'],
                'first_date' => $rows->min('date'),
                'last_date' => $rows->max('date'),
                'visits' => $rows->pluck('visit_id')->unique()->count(),
            ])
            ->sortByDesc('last_date')
            ->values()
            ->all();

        $procedures = $visits->flatMap(fn (Visit $visit) => $visit->procedureRecords->map(fn ($p) => [
            'name' => $p->procedure?->name ?: 'Tindakan',
            'tooth' => $p->tooth_number,
            'quantity' => (int) $p->quantity,
            'date' => $visit->visit_date?->toDateString(),
        ]))
            ->groupBy('name')
            ->map(fn (Collection $rows, $name) => [
                'name' => $name,
                'teeth' => $rows->pluck('tooth')->filter()->unique()->sort()->values()->all(),
                'count' => $rows->sum('quantity') ?: $rows->count(),
                'dates' => $rows->pluck('date')->filter()->unique()->sort()->values()->all(),
            ])
            ->values()
            ->all();

        $therapy = $visits->flatMap(fn (Visit $visit) => $visit->prescriptions->flatMap(fn ($rx) => $rx->items->map(fn ($item) => [
            'label' => $item->label(),
            'rule' => collect([$item->dosage, $item->frequency, $item->duration])->filter()->implode(' · '),
            'quantity' => $item->quantity,
            'date' => $visit->visit_date?->toDateString(),
        ])))
            ->unique(fn ($row) => $row['label'].'|'.$row['rule'].'|'.$row['date'])
            ->values()
            ->all();

        $complaints = $visits
            ->filter(fn (Visit $visit) => filled($visit->medicalRecord?->chief_complaint))
            ->map(fn (Visit $visit) => ['date' => $visit->visit_date?->toDateString(), 'text' => trim((string) $visit->medicalRecord->chief_complaint)])
            ->values()
            ->all();

        $progress = $visits->map(function (Visit $visit) {
            $presenter = new VisitSoapPresenter($visit);
            $dx = $presenter->primaryDiagnosis();
            $bits = collect([
                $dx ? 'Dx: '.trim(($dx->code ? $dx->code.' ' : '').$dx->description) : null,
                $visit->procedureRecords->isNotEmpty()
                    ? 'Tindakan: '.$visit->procedureRecords->map(fn ($p) => ($p->procedure?->name ?: 'Tindakan').($p->tooth_number ? ' ('.$p->tooth_number.')' : ''))->implode(', ')
                    : null,
            ])->filter();

            return $bits->isNotEmpty() ? $visit->visit_date?->format('d/m/Y').' — '.$bits->implode('; ') : null;
        })->filter()->implode("\n");

        $timeline = OdontogramTimeline::for($patient);
        $lastState = $last ? $timeline->stateAfter($last->id) : [];
        $lastPresenter = $last ? new VisitSoapPresenter($last) : null;
        $lastDx = $lastPresenter?->primaryDiagnosis();
        $lastCondition = collect([
            $lastDx ? 'Diagnosis terakhir: '.trim(($lastDx->code ? $lastDx->code.' ' : '').$lastDx->description) : null,
            $lastState !== []
                ? 'Temuan odontogram: '.collect($lastState)->map(fn ($items, $number) => $number.' '.ToothConditions::summary($items))->implode('; ')
                : null,
        ])->filter()->implode("\n");

        $lastPlan = $lastPresenter ? collect($lastPresenter->plan())->map(fn ($row) => $row['label'].': '.str_replace("\n", ', ', $row['value']))->implode("\n") : '';

        $relevant = collect([
            'allergy' => 'Alergi',
            'systemic' => 'Penyakit sistemik',
            'medication' => 'Obat rutin',
            'special' => 'Catatan khusus',
        ])->map(fn ($label, $key) => $info[$key]['value'] ? $label.': '.$info[$key]['value'] : null)->filter()->implode("\n");

        return [
            'period' => [
                'from' => $first?->visit_date?->toDateString(),
                'to' => $last?->visit_date?->toDateString(),
            ],
            'visits' => $visits->map(fn (Visit $visit) => [
                'id' => $visit->id,
                'date' => $visit->visit_date?->toDateString(),
                'number' => (new ExaminationQueueRow($visit))->registrationNumber(),
                'doctor' => $visit->doctor?->name,
                'branch' => $visit->branch?->name,
            ])->values()->all(),
            'complaints' => $complaints,
            'diagnoses' => $diagnoses,
            'procedures' => $procedures,
            'therapy' => $therapy,
            'defaults' => [
                'relevant_history' => $relevant,
                'progress' => $progress,
                'last_condition' => $lastCondition,
                'plan' => $lastPlan,
            ],
        ];
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @param  array{relevant_history?: ?string, progress?: ?string, last_condition?: ?string, plan?: ?string}  $texts
     */
    public function issue(Patient $patient, User $author, Collection $visits, array $texts): ClinicalDocument
    {
        $compiled = $this->compile($patient, $author, $visits);
        /** @var Visit $anchor */
        $anchor = $visits->last();
        $author->loadMissing('doctorProfile');

        $payload = [
            'scope' => 'patient',
            'patient_id' => $patient->id,
            'visit_ids' => $visits->pluck('id')->values()->all(),
            'identity' => [
                'name' => $patient->name,
                'medical_record_number' => $patient->medical_record_number,
                'nik' => $patient->nik,
                'dob' => $patient->dob?->toDateString(),
                'gender' => $patient->gender?->label(),
                'phone' => $patient->phone,
                'address' => $patient->address,
            ],
            'period' => $compiled['period'],
            'visits' => $compiled['visits'],
            'complaints' => $compiled['complaints'],
            'diagnoses' => $compiled['diagnoses'],
            'procedures' => $compiled['procedures'],
            'therapy' => $compiled['therapy'],
            'relevant_history' => trim((string) ($texts['relevant_history'] ?? '')),
            'progress' => trim((string) ($texts['progress'] ?? '')),
            'last_condition' => trim((string) ($texts['last_condition'] ?? '')),
            'plan' => trim((string) ($texts['plan'] ?? '')),
            'author' => [
                'id' => $author->id,
                'name' => $author->name,
                'sip' => $author->doctorProfile?->sip,
            ],
            'compiled_at' => now()->toIso8601String(),
        ];

        return DB::transaction(function () use ($anchor, $author, $payload) {
            $year = now()->year;
            $sequence = $this->sequences->next((int) $anchor->tenant_id, (int) $anchor->branch_id, 'patient_resume', (string) $year);
            $number = sprintf('RSM-%s-%s-%06d', str_pad((string) $anchor->branch_id, 2, '0', STR_PAD_LEFT), $year, $sequence);

            $document = $this->documents->issueStandalone(
                $anchor,
                ClinicalDocumentType::Resume,
                $number,
                $payload,
                $author->doctorProfile?->signature_path,
            );

            activity_log('created', $document, ['visit_ids' => $payload['visit_ids'], 'number' => $number], 'Membuat resume medis pasien '.$number, 'medical_records');

            return $document;
        });
    }

    public function pdf(ClinicalDocument $document, bool $download = false): Response
    {
        $document->loadMissing('visit.branch');
        $payload = is_array($document->payload) ? $document->payload : [];
        $hash = hash('sha256', $document->number.'|'.$document->verification_code.'|'.json_encode($payload));

        $pdf = Pdf::loadView('clinical.history.print.resume', [
            ...PrintChrome::variables((int) $document->tenant_id, $document->visit?->branch),
            'branch' => $document->visit?->branch,
            'title' => 'Resume Medis Pasien',
            'document' => $document,
            'payload' => $payload,
            'letterNumber' => $document->number,
            'verificationCode' => $document->verification_code,
            'documentHash' => $hash,
            'signatureSrc' => $this->documents->signatureSrc($document->signature_path),
        ])->setPaper('a4');

        $name = str_replace('/', '-', $document->number).'.pdf';

        return $download ? $pdf->download($name) : $pdf->stream($name);
    }
}
