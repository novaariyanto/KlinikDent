<?php

namespace App\Support\Clinical;

use App\Enums\ClinicalDocumentType;
use App\Enums\MedicalLetterType;
use App\Enums\VisitStatus;
use App\Models\Branch;
use App\Models\ClinicalDocument;
use App\Models\Diagnosis;
use App\Models\Invoice;
use App\Models\MedicalLetter;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ProcedureRecord;
use App\Models\Referral;
use App\Models\Room;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PatientHistoryService
{
    public const PER_PAGE = 10;

    /**
     * Kunjungan pasien yang boleh dilihat: tenant (global scope) + cabang pengguna.
     *
     * @return Builder<Visit>
     */
    public function visitsQuery(Patient $patient, User $user): Builder
    {
        return $user->applyBranchLimit(Visit::query()->where('visits.patient_id', $patient->id), 'visits.branch_id');
    }

    public function canSeeVisit(Patient $patient, User $user, Visit $visit): bool
    {
        return (int) $visit->patient_id === (int) $patient->id
            && $this->visitsQuery($patient, $user)->whereKey($visit->id)->exists();
    }

    /**
     * @return array{visits: int, diagnoses: int, procedures: int, letters: int, first_visit: ?Carbon, last_visit: ?Carbon}
     */
    public function stats(Patient $patient, User $user): array
    {
        $visitIds = $this->visitsQuery($patient, $user)->select('visits.id');
        $range = $this->visitsQuery($patient, $user)
            ->where('status', '!=', VisitStatus::Cancelled)
            ->selectRaw('count(*) as total, min(visit_date) as first_visit, max(visit_date) as last_visit')
            ->toBase()
            ->first();

        return [
            'visits' => (int) ($range->total ?? 0),
            'diagnoses' => Diagnosis::query()->whereIn('visit_id', $visitIds)->count(),
            'procedures' => ProcedureRecord::query()->whereIn('visit_id', $visitIds)->count(),
            'letters' => $this->lettersQuery($patient, $user)->count()
                + ClinicalDocument::query()->whereIn('visit_id', $this->visitsQuery($patient, $user)->select('visits.id'))->count(),
            'first_visit' => filled($range->first_visit ?? null) ? Carbon::parse($range->first_visit) : null,
            'last_visit' => filled($range->last_visit ?? null) ? Carbon::parse($range->last_visit) : null,
        ];
    }

    /**
     * Informasi penting dari catatan terbaru yang benar-benar mengisi tiap bagian.
     * Status "belum ditanyakan" tidak dianggap sebagai "tidak ada".
     *
     * @return array<string, array{value: ?string, state: string, date: ?Carbon}>
     */
    public function importantInfo(Patient $patient, User $user): array
    {
        $records = MedicalRecord::query()
            ->whereIn('medical_records.visit_id', $this->visitsQuery($patient, $user)->select('visits.id'))
            ->whereNotNull('systemic_history')
            ->join('visits', 'visits.id', '=', 'medical_records.visit_id')
            ->orderByDesc('visits.visit_date')
            ->orderByDesc('visits.id')
            ->limit(30)
            ->get(['medical_records.id', 'medical_records.visit_id', 'medical_records.systemic_history', 'visits.visit_date']);

        $info = [
            'allergy' => ['value' => null, 'state' => 'empty', 'date' => null],
            'systemic' => ['value' => null, 'state' => 'empty', 'date' => null],
            'medication' => ['value' => null, 'state' => 'empty', 'date' => null],
            'special' => ['value' => null, 'state' => 'empty', 'date' => null],
        ];

        $categories = CareExamOptions::allergyCategories();
        $severities = CareExamOptions::allergySeverities();
        $systemic = CareExamOptions::systemic();

        foreach ($records as $record) {
            $history = is_array($record->systemic_history) ? $record->systemic_history : [];
            $date = $record->visit_date ? Carbon::parse($record->visit_date) : null;

            if ($info['allergy']['state'] === 'empty') {
                $status = (string) ($history['allergy_status'] ?? 'not_asked');
                $value = match ($status) {
                    'present' => trim(collect([
                        $categories[$history['allergy_category'] ?? ''] ?? null,
                        $history['allergy_detail'] ?? null,
                        filled($history['allergy_reaction'] ?? null) ? 'reaksi: '.$history['allergy_reaction'] : null,
                        $severities[$history['allergy_severity'] ?? ''] ?? null,
                    ])->filter(fn ($v) => filled($v))->implode(' · ')),
                    'none_confirmed' => 'Tidak ada alergi yang diketahui',
                    'unknown' => 'Tidak diketahui (sudah ditanyakan)',
                    default => null,
                };

                if ($value !== null) {
                    $info['allergy'] = ['value' => $value ?: 'Ada alergi', 'state' => $status === 'present' ? 'alert' : 'filled', 'date' => $date];
                }
            }

            if ($info['systemic']['state'] === 'empty') {
                $conditions = collect((array) ($history['conditions'] ?? []))
                    ->filter(fn ($key) => is_string($key) && ! in_array($key, ['allergy', 'medication'], true))
                    ->map(fn ($key) => $key === 'other' && filled($history['other_detail'] ?? null)
                        ? 'Lainnya: '.$history['other_detail']
                        : ($systemic[$key] ?? $key))
                    ->values();

                if ($conditions->isNotEmpty()) {
                    $info['systemic'] = ['value' => $conditions->implode(', '), 'state' => 'alert', 'date' => $date];
                } elseif (filter_var($history['none'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $info['systemic'] = ['value' => 'Tidak ada penyakit sistemik', 'state' => 'filled', 'date' => $date];
                }
            }

            if ($info['medication']['state'] === 'empty') {
                $status = (string) ($history['medication_status'] ?? 'not_asked');

                if ($status === 'present') {
                    $info['medication'] = ['value' => (string) ($history['medication_detail'] ?? 'Ada obat rutin'), 'state' => 'alert', 'date' => $date];
                } elseif ($status === 'none_confirmed') {
                    $info['medication'] = ['value' => 'Tidak sedang mengonsumsi obat rutin', 'state' => 'filled', 'date' => $date];
                }
            }

            if ($info['special']['state'] === 'empty') {
                $bits = collect([
                    filled($history['blood_type'] ?? null)
                        ? 'Gol. darah '.$history['blood_type'].match ($history['rhesus'] ?? null) {
                            'positive' => ' Rh+',
                            'negative' => ' Rh−',
                            default => '',
                        }
                        : null,
                    match ($history['pregnancy_status'] ?? null) {
                        'pregnant' => 'Sedang hamil',
                        'not_pregnant' => 'Tidak hamil',
                        default => null,
                    },
                    filled($history['allergy_note'] ?? null) ? 'Catatan alergi: '.$history['allergy_note'] : null,
                ])->filter();

                if ($bits->isNotEmpty()) {
                    $info['special'] = ['value' => $bits->implode(' · '), 'state' => ($history['pregnancy_status'] ?? null) === 'pregnant' ? 'alert' : 'filled', 'date' => $date];
                }
            }
        }

        return $info;
    }

    /**
     * @return array{doctors: Collection<int, User>, branches: Collection<int, Branch>, rooms: Collection<int, Room>, statuses: array<string, string>}
     */
    public function filterOptions(Patient $patient, User $user): array
    {
        $base = $this->visitsQuery($patient, $user);

        return [
            'doctors' => User::query()->whereIn('id', (clone $base)->whereNotNull('doctor_id')->select('doctor_id'))->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->whereIn('id', (clone $base)->select('branch_id'))->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::query()->whereIn('id', (clone $base)->whereNotNull('room_id')->select('room_id'))->orderBy('name')->get(['id', 'name']),
            'statuses' => collect(VisitStatus::cases())->mapWithKeys(fn (VisitStatus $s) => [$s->value => $s->label()])->all(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Visit>
     */
    public function visits(Patient $patient, User $user, Request $request): LengthAwarePaginator
    {
        $query = $this->visitsQuery($patient, $user)
            ->with([
                'doctor:id,name',
                'branch:id,name',
                'room:id,name,type',
                'payer:id,name',
                'queue',
                'medicalRecord:id,visit_id,chief_complaint,finalized_at',
                'diagnoses:id,visit_id,kind,rank,code,description,tooth_number',
                'procedureRecords:id,visit_id,procedure_id,tooth_number,quantity',
                'procedureRecords.procedure:id,name',
                'invoices:id,visit_id,number,status,total_amount,paid_amount',
            ])
            ->withCount(['prescriptions', 'referrals']);

        $this->applyDateRange($query, $request, 'visits.visit_date');

        if ($request->filled('doctor_id')) {
            $query->where('visits.doctor_id', $request->integer('doctor_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('visits.branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('room_id')) {
            $query->where('visits.room_id', $request->integer('room_id'));
        }

        if (($status = VisitStatus::tryFrom((string) $request->query('status'))) !== null) {
            $query->where('visits.status', $status);
        }

        $term = trim((string) $request->query('q', ''));

        if ($term !== '') {
            $query->where(function (Builder $inner) use ($term) {
                $digits = preg_replace('/\D+/', '', $term) ?? '';

                if (strlen($digits) >= 9) {
                    $inner->orWhere(function (Builder $byNumber) use ($digits) {
                        $byNumber->whereDate('visits.visit_date', Carbon::createFromFormat('Ymd', substr($digits, 0, 8))?->toDateString() ?? '1900-01-01')
                            ->where('visits.id', (int) substr($digits, 8));
                    });
                } elseif ($digits !== '' && $digits === $term) {
                    $inner->orWhere('visits.id', (int) $digits);
                }

                $inner->orWhereHas('diagnoses', function (Builder $diagnosis) use ($term) {
                    $like = '%'.$term.'%';
                    $diagnosis->where('code', 'like', $like)->orWhere('description', 'like', $like);
                });
            });
        }

        return $query->latest('visits.visit_date')
            ->latest('visits.id')
            ->paginate(self::PER_PAGE, ['visits.*'], 'page')
            ->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, Diagnosis>
     */
    public function diagnoses(Patient $patient, User $user, Request $request): LengthAwarePaginator
    {
        $visits = $this->visitsQuery($patient, $user);
        $this->applyDateRange($visits, $request, 'visits.visit_date');

        $query = Diagnosis::query()
            ->whereIn('diagnoses.visit_id', $visits->select('visits.id'))
            ->with(['visit:id,visit_date,doctor_id,branch_id,status', 'visit.doctor:id,name'])
            ->orderByDesc(Visit::query()->select('visit_date')->whereColumn('visits.id', 'diagnoses.visit_id')->limit(1))
            ->orderByRaw("case when diagnoses.rank = 'primary' then 0 else 1 end")
            ->orderByDesc('diagnoses.id');

        if (($term = trim((string) $request->query('dq', ''))) !== '') {
            $like = '%'.$term.'%';
            $query->where(fn (Builder $q) => $q->where('code', 'like', $like)->orWhere('description', 'like', $like));
        }

        return $query->paginate(15, ['diagnoses.*'], 'dpage')->withQueryString();
    }

    /**
     * Ringkasan diagnosis per kode: jumlah kunjungan berbeda, bukan jumlah baris.
     *
     * @return Collection<int, object>
     */
    public function diagnosisSummary(Patient $patient, User $user): Collection
    {
        return Diagnosis::query()
            ->whereIn('diagnoses.visit_id', $this->visitsQuery($patient, $user)->select('visits.id'))
            ->join('visits', 'visits.id', '=', 'diagnoses.visit_id')
            ->selectRaw("coalesce(nullif(diagnoses.code, ''), diagnoses.description) as grouping_key, max(diagnoses.code) as code, max(diagnoses.description) as description, count(distinct diagnoses.visit_id) as visit_count, max(visits.visit_date) as last_date, min(visits.visit_date) as first_date")
            ->groupBy('grouping_key')
            ->orderByDesc('last_date')
            ->limit(8)
            ->toBase()
            ->get();
    }

    /**
     * @return LengthAwarePaginator<int, ProcedureRecord>
     */
    public function procedures(Patient $patient, User $user, Request $request): LengthAwarePaginator
    {
        $visits = $this->visitsQuery($patient, $user);
        $this->applyDateRange($visits, $request, 'visits.visit_date');

        $query = ProcedureRecord::query()
            ->whereIn('procedure_records.visit_id', $visits->select('visits.id'))
            ->with(['procedure:id,name,code', 'visit:id,visit_date,doctor_id,branch_id', 'visit.doctor:id,name'])
            ->orderByDesc(Visit::query()->select('visit_date')->whereColumn('visits.id', 'procedure_records.visit_id')->limit(1))
            ->orderByDesc('procedure_records.id');

        if (($term = trim((string) $request->query('pq', ''))) !== '') {
            $like = '%'.$term.'%';
            $query->where(fn (Builder $q) => $q->whereHas('procedure', fn (Builder $p) => $p->where('name', 'like', $like)->orWhere('code', 'like', $like))
                ->orWhere('icd9_code', 'like', $like));
        }

        return $query->paginate(15, ['procedure_records.*'], 'ppage')->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, PrescriptionItem>
     */
    public function prescriptionItems(Patient $patient, User $user, Request $request): LengthAwarePaginator
    {
        $visits = $this->visitsQuery($patient, $user);
        $this->applyDateRange($visits, $request, 'visits.visit_date');

        $query = PrescriptionItem::query()
            ->whereHas('prescription', fn (Builder $p) => $p->whereIn('visit_id', $visits->select('visits.id')))
            ->with([
                'medicine:id,name',
                'ingredients',
                'prescription:id,visit_id,doctor_id,status,created_at',
                'prescription.doctor:id,name',
                'prescription.visit:id,visit_date,doctor_id',
                'prescription.visit.doctor:id,name',
            ])
            ->orderByDesc('prescription_items.prescription_id')
            ->orderBy('prescription_items.id');

        if (($term = trim((string) $request->query('mq', ''))) !== '') {
            $like = '%'.$term.'%';
            $query->where(fn (Builder $q) => $q->whereHas('medicine', fn (Builder $m) => $m->where('name', 'like', $like))
                ->orWhereHas('ingredients', fn (Builder $i) => $i->where('name', 'like', $like)));
        }

        return $query->paginate(20, ['prescription_items.*'], 'rpage')->withQueryString();
    }

    /**
     * @return Builder<MedicalLetter>
     */
    public function lettersQuery(Patient $patient, User $user): Builder
    {
        return $user->applyBranchLimit(
            MedicalLetter::query()->where('patient_id', $patient->id)->where('number', '!=', 'DRAFT'),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function documentCategories(): array
    {
        return [
            'sick' => 'Surat keterangan sakit',
            'healthy' => 'Surat keterangan sehat',
            'examination' => 'Surat keterangan pemeriksaan',
            'treatment' => 'Surat keterangan perawatan',
            'control' => 'Surat kontrol',
            'referral' => 'Surat rujukan',
            'resume' => 'Resume medis / resume pulang',
            'instructions' => 'Instruksi perawatan pasien',
            'prescription' => 'Resep obat',
            'billing' => 'Invoice / kuitansi',
            'other' => 'Dokumen lainnya',
        ];
    }

    /**
     * Semua dokumen yang dapat dicetak: surat medis, dokumen klinis, rujukan, resume kunjungan,
     * instruksi perawatan, resep, dan invoice. Yang sudah diterbitkan tidak diulang sebagai baris "belum diterbitkan".
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function documents(Patient $patient, User $user, Request $request): LengthAwarePaginator
    {
        $visitIds = $this->visitsQuery($patient, $user)->select('visits.id');
        $rows = collect();
        $can = fn (array $permissions) => collect($permissions)->every(fn ($permission) => $user->can($permission));
        $careVisitIds = $this->visitsQuery($patient, $user)->visibleTo($user)->pluck('visits.id')->map(fn ($id) => (int) $id)->flip();
        $careAllowed = fn ($visitId, array $permissions) => $can($permissions) && isset($careVisitIds[(int) $visitId]);

        foreach ($this->lettersQuery($patient, $user)->with(['doctor:id,name', 'visit:id,visit_date'])->get() as $letter) {
            $rows->push([
                'category' => match ($letter->type) {
                    MedicalLetterType::Sick => 'sick',
                    MedicalLetterType::Healthy => 'healthy',
                    MedicalLetterType::Examination, MedicalLetterType::Radiograph => 'examination',
                    MedicalLetterType::Treatment => 'treatment',
                    MedicalLetterType::Control => 'control',
                    default => 'other',
                },
                'type' => $letter->type->label(),
                'number' => $letter->number,
                'date' => $letter->issued_at ?? $letter->created_at,
                'doctor' => $letter->doctor?->name ?: data_get($letter->snapshot, 'doctor.name'),
                'status' => $letter->status->label(),
                'status_class' => $letter->status->badgeClass(),
                'visit_id' => $letter->visit_id,
                'visit_date' => $letter->visit?->visit_date,
                'preview' => route('medical-letters.show', $letter),
                'print' => route('medical-letters.pdf', $letter),
                'allowed' => $can(['medical_record.view']),
            ]);
        }

        $documents = ClinicalDocument::query()
            ->whereIn('visit_id', $visitIds)
            ->with(['visit:id,visit_date,doctor_id', 'visit.doctor:id,name'])
            ->get();

        $referencedReferrals = $documents
            ->where('reference_type', Referral::class)
            ->pluck('reference_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($documents as $document) {
            $patientResume = data_get($document->payload, 'scope') === 'patient';
            $rows->push([
                'category' => match ($document->type) {
                    ClinicalDocumentType::SickLetter => 'sick',
                    ClinicalDocumentType::Referral => 'referral',
                    ClinicalDocumentType::Resume => 'resume',
                },
                'type' => $patientResume ? 'Resume medis pasien (periode)' : $document->type->label(),
                'number' => $document->number,
                'date' => $document->signed_at ?? $document->created_at,
                'doctor' => $patientResume ? data_get($document->payload, 'author.name') : $document->visit?->doctor?->name,
                'status' => $document->status->label(),
                'status_class' => $document->isValid() ? 'badge bg-success' : 'badge bg-danger',
                'visit_id' => $document->visit_id,
                'visit_date' => $document->visit?->visit_date,
                'preview' => $patientResume
                    ? route('patients.history.resume.pdf', [$patient, $document])
                    : route('care.documents.pdf', [$document->visit_id, $document]),
                'print' => null,
                'allowed' => $patientResume
                    ? $can(['medical_record.view'])
                    : $careAllowed($document->visit_id, [$document->type === ClinicalDocumentType::Referral ? 'referral.view' : 'medical_record.view', 'examination.view']),
            ]);
        }

        $referrals = Referral::query()
            ->whereIn('visit_id', $visitIds)
            ->whereNotIn('id', $referencedReferrals ?: [0])
            ->with(['visit:id,visit_date,doctor_id', 'visit.doctor:id,name'])
            ->get();

        foreach ($referrals as $referral) {
            $rows->push([
                'category' => 'referral',
                'type' => 'Surat rujukan — '.$referral->referred_to,
                'number' => null,
                'date' => $referral->created_at,
                'doctor' => $referral->visit?->doctor?->name,
                'status' => 'Belum dicetak',
                'status_class' => 'badge bg-secondary',
                'visit_id' => $referral->visit_id,
                'visit_date' => $referral->visit?->visit_date,
                'preview' => route('care.referral.pdf', [$referral->visit_id, $referral]),
                'print' => null,
                'allowed' => $careAllowed($referral->visit_id, ['referral.view', 'examination.view']),
            ]);
        }

        $issuedResumeVisits = $documents
            ->filter(fn (ClinicalDocument $d) => $d->type === ClinicalDocumentType::Resume && data_get($d->payload, 'scope') !== 'patient')
            ->pluck('visit_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $visits = $this->visitsQuery($patient, $user)
            ->where('visits.status', '!=', VisitStatus::Cancelled)
            ->with(['doctor:id,name', 'medicalRecord:id,visit_id,plan_instructions,finalized_at'])
            ->get(['visits.id', 'visits.visit_date', 'visits.doctor_id', 'visits.status']);

        foreach ($visits as $visit) {
            $record = $visit->medicalRecord;

            if (! $record) {
                continue;
            }

            if (! isset($issuedResumeVisits[$visit->id])) {
                $rows->push([
                    'category' => 'resume',
                    'type' => 'Resume medis rawat jalan (resume pulang)',
                    'number' => null,
                    'date' => $record->finalized_at ?? $visit->visit_date,
                    'doctor' => $visit->doctor?->name,
                    'status' => 'Belum diterbitkan',
                    'status_class' => 'badge bg-secondary',
                    'visit_id' => $visit->id,
                    'visit_date' => $visit->visit_date,
                    'preview' => route('care.resume.pdf', $visit->id),
                    'print' => null,
                    'allowed' => $careAllowed($visit->id, ['medical_record.view', 'examination.view']),
                ]);
            }

            $plan = is_array($record->plan_instructions) ? $record->plan_instructions : [];

            if (collect($plan['items'] ?? [])->filter()->isNotEmpty() || filled($plan['extra'] ?? null)) {
                $rows->push([
                    'category' => 'instructions',
                    'type' => 'Instruksi perawatan pasien',
                    'number' => null,
                    'date' => $visit->visit_date,
                    'doctor' => $visit->doctor?->name,
                    'status' => 'Tersedia',
                    'status_class' => 'badge bg-info',
                    'visit_id' => $visit->id,
                    'visit_date' => $visit->visit_date,
                    'preview' => route('care.instructions.pdf', $visit->id),
                    'print' => null,
                    'allowed' => $careAllowed($visit->id, ['medical_record.view', 'examination.view']),
                ]);
            }
        }

        $prescriptions = Prescription::query()
            ->whereIn('visit_id', $visitIds)
            ->with(['doctor:id,name', 'visit:id,visit_date,doctor_id', 'visit.doctor:id,name'])
            ->withCount('items')
            ->get();

        foreach ($prescriptions as $prescription) {
            $rows->push([
                'category' => 'prescription',
                'type' => 'Resep obat ('.$prescription->items_count.' item)',
                'number' => 'RX-'.$prescription->id,
                'date' => $prescription->created_at,
                'doctor' => $prescription->doctor?->name ?: $prescription->visit?->doctor?->name,
                'status' => $prescription->status->label(),
                'status_class' => $prescription->status->badgeClass(),
                'visit_id' => $prescription->visit_id,
                'visit_date' => $prescription->visit?->visit_date,
                'preview' => route('patients.history.prescription.print', [$patient, $prescription]),
                'print' => null,
                'allowed' => $can(['prescription.view']),
            ]);
        }

        $invoices = Invoice::query()
            ->whereIn('visit_id', $visitIds)
            ->with(['visit:id,visit_date,doctor_id', 'visit.doctor:id,name'])
            ->get(['id', 'tenant_id', 'branch_id', 'visit_id', 'number', 'status', 'created_at']);

        foreach ($invoices as $invoice) {
            $rows->push([
                'category' => 'billing',
                'type' => 'Invoice / kuitansi pembayaran',
                'number' => $invoice->number,
                'date' => $invoice->created_at,
                'doctor' => $invoice->visit?->doctor?->name,
                'status' => $invoice->status->label(),
                'status_class' => $invoice->status->badgeClass(),
                'visit_id' => $invoice->visit_id,
                'visit_date' => $invoice->visit?->visit_date,
                'preview' => route('billing.invoices.print', $invoice),
                'print' => null,
                'allowed' => $user->can('view', $invoice),
            ]);
        }

        $category = (string) $request->query('doc_type', '');

        if (array_key_exists($category, self::documentCategories())) {
            $rows = $rows->where('category', $category);
        }

        if ($request->filled('date_from')) {
            $from = Carbon::parse((string) $request->query('date_from'))->startOfDay();
            $rows = $rows->filter(fn ($row) => $row['date'] && $row['date'] >= $from);
        }

        if ($request->filled('date_to')) {
            $to = Carbon::parse((string) $request->query('date_to'))->endOfDay();
            $rows = $rows->filter(fn ($row) => $row['date'] && $row['date'] <= $to);
        }

        $rows = $rows->sortByDesc(fn ($row) => $row['date']?->timestamp ?? 0)->values();
        $page = max(1, $request->integer('docpage', 1));

        return (new Paginator($rows->forPage($page, 15)->values(), $rows->count(), 15, $page, [
            'path' => $request->url(),
            'pageName' => 'docpage',
        ]))->withQueryString();
    }

    /**
     * @param  Builder<mixed>  $query
     */
    protected function applyDateRange(Builder $query, Request $request, string $column): void
    {
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            $value = (string) $request->query($key, '');

            if ($value !== '' && strtotime($value) !== false) {
                $query->whereDate($column, $operator, Carbon::parse($value)->toDateString());
            }
        }
    }
}
