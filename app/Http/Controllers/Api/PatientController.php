<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Registration\PatientController as RegistrationPatientController;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\Branch;
use App\Models\Patient;
use App\Support\Integrations\IntegrationService;
use App\Support\Registration\DocumentSequenceGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    use RespondsWithPage;

    public function __construct(
        protected DocumentSequenceGenerator $sequences,
        protected IntegrationService $integrations,
        protected RegistrationPatientController $registrationPatients,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Patient::class);

        return $this->paged(
            $request,
            Patient::query()->orderBy('id'),
            fn (Patient $patient) => [
                'id' => $patient->id,
                'medical_record_number' => $patient->medical_record_number,
                'name' => $patient->name,
                'phone' => $patient->phone,
                'dob' => $patient->dob?->format('Y-m-d'),
            ],
        );
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Patient::class);

        $term = trim((string) $request->query('q', ''));

        if (strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $patients = Patient::query()
            ->search($term)
            ->orderBy('name')
            ->limit(15)
            ->get([
                'id',
                'medical_record_number',
                'name',
                'nik',
                'phone',
                'dob',
                'gender',
                'address',
                'address_city',
                'address_postal_code',
                'province_code',
                'city_code',
                'district_code',
                'village_code',
                'rt',
                'rw',
                'default_payer_id',
                'satusehat_id',
            ])
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'medical_record_number' => $patient->medical_record_number,
                'name' => $patient->name,
                'nik' => $patient->nik,
                'phone' => $patient->phone,
                'dob' => $patient->dob?->format('Y-m-d'),
                'gender' => $patient->gender?->value,
                'address' => $patient->address,
                'address_city' => $patient->address_city,
                'address_postal_code' => $patient->address_postal_code,
                'province_code' => $patient->province_code,
                'city_code' => $patient->city_code,
                'district_code' => $patient->district_code,
                'village_code' => $patient->village_code,
                'rt' => $patient->rt,
                'rw' => $patient->rw,
                'default_payer_id' => $patient->default_payer_id,
                'satusehat_id' => $patient->satusehat_id,
            ]);

        return response()->json([
            'data' => $patients,
        ]);
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $activeBranch = $request->attributes->get('active_branch');
        $branch = $activeBranch instanceof Branch
            ? $activeBranch
            : Branch::query()->find($data['branch_id'] ?? $user?->branch_id);

        abort_unless($branch && $user?->belongsToTenantId($branch->tenant_id) && $user->canAccessBranch((int) $branch->id), 403);

        $patient = DB::transaction(function () use ($data, $user, $branch) {
            unset($data['branch_id']);
            $data['tenant_id'] = $user->tenant_id;
            $data['medical_record_number'] = $this->sequences->nextMedicalRecord($branch->tenant, $branch);
            $data = $this->registrationPatients->enrichWilayahFields($data);

            return Patient::query()->create($data);
        });

        $ihs = $branch ? $this->integrations->lookupPatientIhs($patient, $branch) : null;

        activity_audit('created', $patient, [], 'Created patient '.$patient->name, 'patients');

        $message = 'Pasien created successfully.';
        if ($ihs) {
            $message .= ' Nomor IHS '.$ihs.'.';
        }

        return response()->json([
            'data' => $patient,
            'message' => $message,
        ]);
    }

    public function show(Patient $patient): JsonResponse
    {
        $this->authorize('view', $patient);

        return response()->json([
            'data' => $patient,
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $data = $this->registrationPatients->enrichWilayahFields($request->validated());
        $before = activity_snapshot($patient);
        $patient->update($data);

        activity_audit('updated', $patient, $before, 'Updated patient '.$patient->name, 'patients');

        return response()->json([
            'data' => $patient,
            'message' => 'Pasien updated successfully.',
        ]);
    }

    public function destroy(Patient $patient): JsonResponse
    {
        $this->authorize('delete', $patient);

        if ($patient->visits()->exists()) {
            return response()->json([
                'message' => 'Pasien tidak dapat dihapus karena sudah memiliki kunjungan.',
            ], 422);
        }

        activity_audit('deleted', $patient, activity_snapshot($patient), 'Deleted patient '.$patient->name, 'patients', [
            'name' => $patient->name,
        ]);
        $patient->delete();

        return response()->json([
            'message' => 'Pasien deleted successfully.',
        ]);
    }

    public function fetchIhs(Request $request, Patient $patient): JsonResponse
    {
        $request->headers->set('Accept', 'application/json');

        $response = $this->registrationPatients->fetchIhs($request, $patient);

        if (! $response instanceof JsonResponse) {
            return response()->json([
                'message' => 'Permintaan IHS gagal.',
            ], 500);
        }

        $payload = $response->getData(true);

        if (($payload['ok'] ?? false) !== true) {
            return $response;
        }

        return response()->json([
            'data' => [
                'satusehat_id' => $payload['ihs'] ?? $patient->fresh()?->satusehat_id,
            ],
            'message' => $payload['message'] ?? null,
        ], $response->getStatusCode());
    }
}
