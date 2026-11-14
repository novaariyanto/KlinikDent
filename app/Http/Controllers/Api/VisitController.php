<?php

namespace App\Http\Controllers\Api;

use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Registration\VisitController as RegistrationVisitController;
use App\Http\Requests\Api\StoreVisitRequest;
use App\Models\Branch;
use App\Models\Visit;
use App\Support\Registration\VisitRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    use RespondsWithPage;

    public function __construct(protected RegistrationVisitController $registrationVisits) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Visit::class);

        $query = Visit::query()
            ->visibleTo($request->user())
            ->with(['patient', 'queue', 'doctor', 'room'])
            ->orderByDesc('id');

        $activeBranch = $request->attributes->get('active_branch');

        if ($activeBranch instanceof Branch) {
            $query->where('branch_id', $activeBranch->id);
        }

        if ($request->query('scope') === 'today') {
            $query->today();
        }

        return $this->paged($request, $query, fn (Visit $visit) => $this->summary($visit));
    }

    public function show(Visit $visit): JsonResponse
    {
        $this->authorize('view', $visit);
        $visit->load(['patient', 'queue', 'doctor', 'room', 'payer', 'branch', 'invoices', 'prescriptions']);

        return response()->json([
            'data' => $this->detail($visit),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        return $this->envelope($this->registrationVisits->availability($this->withActiveBranch($request)));
    }

    public function store(StoreVisitRequest $request, VisitRegistrar $registrar): JsonResponse
    {
        $data = $request->validated();

        $visit = $registrar->register($request->user(), $data);
        $visit->load(['patient', 'queue']);

        activity_log('created', $visit, $data, 'Registered visit for '.$visit->patient?->name, 'visits');

        $queueNumber = $visit->queue?->displayNumber();
        $ihs = $visit->patient?->satusehat_id;
        $ihsNote = $ihs ? ' IHS '.$ihs.'.' : '';
        $message = $registrar->reusedExistingPatient
            ? 'NIK sudah terdaftar pada '.$visit->patient?->name.' (RM '.$visit->patient?->medical_record_number.'). Kunjungan dicatat pada rekam medis itu. Nomor antrean '.$queueNumber.'.'.$ihsNote
            : 'Pendaftaran berhasil. Nomor antrean '.$queueNumber.'.'.$ihsNote;

        return response()->json([
            'data' => [
                'visit_id' => $visit->id,
                'queue_number' => $queueNumber,
                'patient_id' => $visit->patient_id,
            ],
            'message' => $message,
        ]);
    }

    public function patientIhs(Request $request): JsonResponse
    {
        return $this->envelope($this->registrationVisits->lookupPatientIhs($this->withActiveBranch($request)));
    }

    public function cancel(Visit $visit): JsonResponse
    {
        $this->authorize('cancel', $visit);

        $before = activity_snapshot($visit);
        $visit->update(['status' => VisitStatus::Cancelled]);
        $visit->queue?->update(['status' => QueueStatus::Skipped]);

        activity_audit('cancelled', $visit, $before, 'Cancelled visit', 'visits', [
            'status' => VisitStatus::Cancelled->value,
        ]);

        $visit->load(['patient', 'queue', 'doctor', 'room', 'payer', 'branch']);

        return response()->json([
            'data' => $this->detail($visit),
            'message' => 'Kunjungan dibatalkan.',
        ]);
    }

    protected function envelope(JsonResponse $response): JsonResponse
    {
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $payload = $response->getData(true);

        if (! is_array($payload) || array_key_exists('data', $payload)) {
            return $response;
        }

        return response()->json([
            'data' => $payload,
        ], $response->getStatusCode());
    }

    protected function withActiveBranch(Request $request): Request
    {
        if (! $request->filled('branch_id')) {
            $activeBranch = $request->attributes->get('active_branch');

            if ($activeBranch instanceof Branch) {
                $request->merge(['branch_id' => $activeBranch->id]);
            }
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(Visit $visit): array
    {
        return [
            'id' => $visit->id,
            'visit_date' => $visit->visit_date?->format('Y-m-d'),
            'queue_number' => $visit->queue?->displayNumber(),
            'patient' => $visit->patient?->name,
            'doctor' => $visit->doctor?->name,
            'room' => $visit->room?->name,
            'status' => $visit->status?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detail(Visit $visit): array
    {
        return $this->summary($visit) + [
            'patient_id' => $visit->patient_id,
            'doctor_id' => $visit->doctor_id,
            'room_id' => $visit->room_id,
            'payer_id' => $visit->payer_id,
            'branch_id' => $visit->branch_id,
        ];
    }
}
