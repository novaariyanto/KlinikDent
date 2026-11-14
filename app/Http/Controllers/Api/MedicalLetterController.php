<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MobileApiException;
use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Clinical\MedicalLetterController as WebMedicalLetterController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\StoreMedicalLetterRequest;
use App\Models\Branch;
use App\Models\MedicalLetter;
use App\Models\User;
use App\Models\Visit;
use App\Support\Clinical\MedicalLetterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MedicalLetterController extends Controller
{
    use RespondsWithPage;

    public function __construct(protected MedicalLetterService $letters) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('medical_record.view');

        $query = MedicalLetter::query()->with(['patient', 'doctor', 'branch']);
        $this->applyFilters($request, $query);
        $query->latest('issued_at')->latest('id');

        return $this->paged($request, $query, fn (MedicalLetter $letter) => [
            'id' => (int) $letter->id,
            'number' => $letter->number,
            'issued_at' => $letter->issued_at?->format('c'),
            'patient' => $letter->patient?->name,
            'rm' => $letter->patient?->medical_record_number,
            'type' => $letter->type?->value,
            'type_label' => $letter->type?->label(),
            'doctor' => $letter->doctor?->name ?: data_get($letter->snapshot, 'doctor.name'),
            'branch' => $letter->branch?->name,
            'status' => $letter->status?->value,
            'status_label' => $letter->status?->label(),
        ]);
    }

    public function show(Request $request, MedicalLetter $letter): JsonResponse
    {
        $this->guardView($request, $letter);

        return response()->json([
            'data' => $this->letterData($letter),
        ]);
    }

    public function store(StoreMedicalLetterRequest $request, Visit $visit): JsonResponse
    {
        $letter = $this->letters->issue($visit, $this->actor($request), $request->validated());

        return response()->json([
            'data' => [
                'visit_id' => (int) $visit->id,
                'letter' => $this->letterData($letter),
            ],
            'message' => 'Surat '.$letter->number.' diterbitkan.',
        ]);
    }

    public function preview(StoreMedicalLetterRequest $request, Visit $visit): JsonResponse
    {
        $actor = $this->actor($request);
        $this->letters->assertCanIssue($actor, $visit);
        $letter = $this->letters->draft($visit, $actor, $request->validated(), $this->revises($request, $visit, true));

        return response()->json([
            'data' => $this->letterData($letter, true),
        ]);
    }

    public function frame(Request $request, Visit $visit): JsonResponse
    {
        $this->authorize('view', $visit);
        $actor = $this->actor($request);
        $this->letters->assertCanIssue($actor, $visit);

        try {
            $letter = $this->letters->draft($visit, $actor, $request->except(['_token']), $this->revises($request, $visit, false));
        } catch (ValidationException $exception) {
            abort(422, collect($exception->errors())->flatten()->first() ?: 'Data surat belum lengkap.');
        }

        return response()->json([
            'data' => [
                'draft' => true,
                'title' => $letter->type?->label() ?: 'Surat Keterangan Medis',
                'document_hash' => $this->documentHash($letter),
                'letter' => $this->letterData($letter, true),
            ],
        ]);
    }

    public function pdf(Request $request, MedicalLetter $letter): Response
    {
        $this->guardView($request, $letter);

        return app(WebMedicalLetterController::class)->pdf($request, $letter);
    }

    public function cancel(Request $request, MedicalLetter $letter): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $actor = $this->actor($request);
        abort_unless($actor->belongsToTenantId($letter->tenant_id), 404);
        $this->letters->cancel($letter, $actor, $data['reason']);
        $letter->refresh();

        return response()->json([
            'data' => [
                'visit_id' => $letter->visit_id ? (int) $letter->visit_id : null,
                'letter' => $this->letterData($letter),
            ],
            'message' => 'Surat dibatalkan. Riwayatnya tetap tersimpan.',
        ]);
    }

    public function revise(StoreMedicalLetterRequest $request, MedicalLetter $letter): JsonResponse
    {
        abort_unless($letter->visit_id, 404);
        $visit = $letter->visit()->firstOrFail();
        abort_unless($this->actor($request)->belongsToTenantId($visit->tenant_id), 404);
        $issued = $this->letters->issue($visit, $this->actor($request), $request->validated(), $letter);

        return response()->json([
            'data' => [
                'visit_id' => (int) $visit->id,
                'letter' => $this->letterData($issued),
            ],
            'message' => 'Revisi diterbitkan sebagai '.$issued->number.'. Surat sebelumnya ditandai direvisi.',
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<MedicalLetter>  $query
     */
    protected function applyFilters(Request $request, $query): void
    {
        $user = $this->actor($request);
        $branch = $request->attributes->get('active_branch');
        $branchId = $branch instanceof Branch ? (int) $branch->id : null;
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

    protected function guardView(Request $request, MedicalLetter $letter): void
    {
        $user = $this->actor($request);
        abort_unless($user->belongsToTenantId($letter->tenant_id), 404);
        $this->authorize('medical_record.view');

        if (! $user->canAccessBranch((int) $letter->branch_id)) {
            throw new MobileApiException('Cabang tidak dapat diakses.', 'branch_forbidden', 403);
        }
    }

    protected function revises(Request $request, Visit $visit, bool $strict): ?MedicalLetter
    {
        if (! $request->filled('revises_id')) {
            return null;
        }

        $query = MedicalLetter::query()
            ->whereKey($request->integer('revises_id'))
            ->where('visit_id', $visit->id);

        if (! $strict) {
            $revises = $query->first();

            return $revises && $revises->isIssued() ? $revises : null;
        }

        $revises = $query->firstOrFail();
        abort_unless($revises->isIssued(), 422, 'Hanya surat yang masih berlaku yang dapat direvisi.');

        return $revises;
    }

    /**
     * @return array<string, mixed>
     */
    protected function letterData(MedicalLetter $letter, bool $draft = false): array
    {
        if ($letter->exists) {
            $letter->loadMissing(['patient', 'doctor', 'branch', 'replacement', 'previous']);
        }

        $snapshot = $letter->snapshot ?? [];

        return [
            'id' => $letter->exists ? (int) $letter->id : null,
            'visit_id' => $letter->visit_id ? (int) $letter->visit_id : null,
            'number' => $letter->number,
            'type' => $letter->type?->value,
            'type_label' => $letter->type?->label(),
            'status' => $letter->status?->value,
            'status_label' => $letter->status?->label(),
            'purpose' => $letter->purpose,
            'body' => $letter->body,
            'payload' => $letter->payload ?? [],
            'issued_at' => $letter->issued_at?->format('c'),
            'cancelled_at' => $letter->cancelled_at?->format('c'),
            'cancel_reason' => $letter->cancel_reason,
            'patient' => [
                'id' => $letter->patient_id ? (int) $letter->patient_id : null,
                'name' => data_get($snapshot, 'patient.name') ?: $letter->patient?->name,
                'medical_record_number' => data_get($snapshot, 'patient.medical_record_number') ?: $letter->patient?->medical_record_number,
                'nik' => data_get($snapshot, 'patient.nik'),
                'gender' => data_get($snapshot, 'patient.gender'),
                'birth_place' => data_get($snapshot, 'patient.birth_place'),
                'dob' => data_get($snapshot, 'patient.dob'),
                'address' => data_get($snapshot, 'patient.address'),
            ],
            'doctor' => [
                'id' => $letter->doctor_id ? (int) $letter->doctor_id : null,
                'name' => data_get($snapshot, 'doctor.name') ?: $letter->doctor?->name,
                'sip' => data_get($snapshot, 'doctor.sip'),
            ],
            'branch' => [
                'id' => $letter->branch_id ? (int) $letter->branch_id : null,
                'name' => data_get($snapshot, 'clinic.branch') ?: $letter->branch?->name,
            ],
            'clinic' => data_get($snapshot, 'clinic'),
            'diagnoses' => data_get($snapshot, 'diagnoses', []),
            'revises_id' => $letter->revises_id ? (int) $letter->revises_id : null,
            'previous_number' => $letter->relationLoaded('previous') ? $letter->previous?->number : null,
            'revised_by_id' => $letter->revised_by_id ? (int) $letter->revised_by_id : null,
            'replacement_number' => $letter->relationLoaded('replacement') ? $letter->replacement?->number : null,
            'draft' => $draft,
        ];
    }

    protected function documentHash(MedicalLetter $letter): string
    {
        return hash('sha256', implode('|', [
            'SKM',
            (string) $letter->number,
            (string) $letter->type?->value,
            (string) $letter->body,
            json_encode($letter->payload),
        ]));
    }

    protected function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
