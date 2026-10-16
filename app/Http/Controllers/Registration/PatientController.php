<?php

namespace App\Http\Controllers\Registration;

use App\Enums\Citizenship;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Payer;
use App\Support\Indonesia\WilayahService;
use App\Support\Integrations\IntegrationException;
use App\Support\Integrations\IntegrationService;
use App\Support\Registration\DocumentSequenceGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PatientController extends Controller
{
    public function __construct(
        protected DocumentSequenceGenerator $sequences,
        protected IntegrationService $integrations,
        protected WilayahService $wilayah,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Patient::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'medical_record_number', 'name' => 'medical_record_number', 'title' => 'No. RM'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Nama'],
            ['data' => 'nik', 'name' => 'nik', 'title' => 'NIK'],
            ['data' => 'satusehat_id', 'name' => 'satusehat_id', 'title' => 'IHS'],
            ['data' => 'phone', 'name' => 'phone', 'title' => 'Telepon'],
            ['data' => 'payer', 'name' => 'defaultPayer.name', 'title' => 'Penjamin'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('registration.patients.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Patient::class);

        return DataTables::eloquent(Patient::query()->with('defaultPayer'))
            ->addIndexColumn()
            ->editColumn('nik', fn (Patient $patient) => e($patient->nik ?: '-'))
            ->editColumn('satusehat_id', fn (Patient $patient) => e($patient->satusehat_id ?: '-'))
            ->editColumn('phone', fn (Patient $patient) => e($patient->phone ?: '-'))
            ->addColumn('payer', fn (Patient $patient) => e($patient->defaultPayer?->name ?: '-'))
            ->editColumn('created_at', fn (Patient $patient) => $patient->created_at?->format('d M Y H:i'))
            ->addColumn('action', fn (Patient $patient) => view('registration.patients.partials.actions', compact('patient'))->render())
            ->rawColumns(['action'])
            ->toJson();
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Patient::class);

        $term = trim((string) $request->query('q', ''));

        if (strlen($term) < 2) {
            return response()->json([]);
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

        return response()->json($patients);
    }

    public function create(): View
    {
        $this->authorize('create', Patient::class);

        return view('registration.patients.create', $this->formData());
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $branch = Branch::query()->find($data['branch_id'] ?? $user?->branch_id);

        abort_unless($branch && $user?->belongsToTenantId($branch->tenant_id) && $user->canAccessBranch((int) $branch->id), 403);

        $patient = DB::transaction(function () use ($data, $user, $branch) {
            unset($data['branch_id']);
            $data['tenant_id'] = $user->tenant_id;
            $data['medical_record_number'] = $this->sequences->nextMedicalRecord($branch->tenant, $branch);
            $data = $this->enrichWilayahFields($data);

            return Patient::query()->create($data);
        });

        $ihs = $branch ? $this->integrations->lookupPatientIhs($patient, $branch) : null;

        activity_audit('created', $patient, [], 'Created patient '.$patient->name, 'patients');

        $message = 'Pasien created successfully.';
        if ($ihs) {
            $message .= ' Nomor IHS '.$ihs.'.';
        }

        return redirect()->route('registration.patients.show', $patient)
            ->with('success', $message);
    }

    public function show(Patient $patient): View
    {
        $this->authorize('view', $patient);
        $patient->load(['defaultPayer', 'visits' => fn ($query) => $query->with(['queue', 'doctor', 'room', 'payer', 'branch'])->latest('visit_date')->latest('id')]);
        $wilayahLabels = $this->wilayah->labels(
            $patient->province_code,
            $patient->city_code,
            $patient->district_code,
            $patient->village_code,
        );

        return view('registration.patients.show', compact('patient', 'wilayahLabels'));
    }

    public function edit(Patient $patient): View
    {
        $this->authorize('update', $patient);

        return view('registration.patients.edit', $this->formData($patient));
    }

    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        $data = $this->enrichWilayahFields($request->validated());
        $before = activity_snapshot($patient);
        $patient->update($data);

        activity_audit('updated', $patient, $before, 'Updated patient '.$patient->name, 'patients');

        return redirect()->route('registration.patients.show', $patient)
            ->with('success', 'Pasien updated successfully.');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $this->authorize('delete', $patient);

        if ($patient->visits()->exists()) {
            return back()->with('error', 'Pasien tidak dapat dihapus karena sudah memiliki kunjungan.');
        }

        activity_audit('deleted', $patient, activity_snapshot($patient), 'Deleted patient '.$patient->name, 'patients', [
            'name' => $patient->name,
        ]);
        $patient->delete();

        return redirect()->route('registration.patients')->with('success', 'Pasien deleted successfully.');
    }

    public function fetchIhs(Request $request, Patient $patient): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $patient);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'address' => ['nullable', 'string', 'max:1000'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_postal_code' => ['nullable', 'string', 'max:10'],
            'province_code' => ['nullable', 'string', 'max:10'],
            'city_code' => ['nullable', 'string', 'max:10'],
            'district_code' => ['nullable', 'string', 'max:10'],
            'village_code' => ['nullable', 'string', 'max:20'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
        ]);

        $this->applyIhsProfileToPatient($patient, $this->normalizeIhsProfile($data));
        $patient = $patient->fresh();

        try {
            $branch = $this->resolveBranchForIhs($request, $patient);
            $ihs = $this->integrations->fetchPatientIhs($patient, $branch);
        } catch (IntegrationException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            if (str_contains($exception->getMessage(), 'Pendaftaran IHS membutuhkan')) {
                return redirect()
                    ->route('registration.patients.edit', $patient)
                    ->with('error', $exception->getMessage())
                    ->with('ihs_focus', true);
            }

            return back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'ihs' => $ihs,
                'message' => 'IHS pasien: '.$ihs,
            ]);
        }

        return back()->with('success', 'IHS '.$patient->name.' tersimpan: '.$ihs);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Patient $patient = null): array
    {
        $provinceCode = $this->wilayah->normalizeProvinceCode(old('province_code', $patient?->province_code));
        $cityCode = $this->wilayah->normalizeCityCode(old('city_code', $patient?->city_code));
        $districtCode = $this->wilayah->normalizeDistrictCode(old('district_code', $patient?->district_code));
        $villageCode = $this->wilayah->normalizeVillageCode(old('village_code', $patient?->village_code));

        if ($patient) {
            $patient->province_code = $provinceCode ?? $patient->province_code;
            $patient->city_code = $cityCode ?? $patient->city_code;
            $patient->district_code = $districtCode ?? $patient->district_code;
            $patient->village_code = $villageCode ?? $patient->village_code;
        }

        return [
            'patient' => $patient,
            'payers' => Payer::query()->orderBy('name')->get(),
            'branches' => Branch::query()->orderBy('name')->get(),
            'genders' => Gender::options(),
            'maritalStatuses' => MaritalStatus::options(),
            'citizenships' => Citizenship::options(),
            'provinces' => $this->wilayah->provinces(),
            'cities' => $this->wilayah->cities((string) $provinceCode),
            'districts' => $this->wilayah->districts((string) $cityCode),
            'villages' => $this->wilayah->villages((string) $districtCode),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function enrichWilayahFields(array $data): array
    {
        $cityCode = (string) ($data['city_code'] ?? '');
        $villageCode = (string) ($data['village_code'] ?? '');

        if ($cityCode !== '' && blank($data['address_city'] ?? null)) {
            $data['address_city'] = $this->wilayah->cityName($cityCode);
        }

        if ($villageCode !== '' && blank($data['address_postal_code'] ?? null)) {
            $data['address_postal_code'] = $this->wilayah->villagePostalCode($villageCode);
        }

        return $data;
    }

    protected function resolveBranchForIhs(Request $request, Patient $patient): Branch
    {
        $user = $request->user();
        $candidates = collect([
            $request->integer('branch_id') ?: null,
            $user?->branch_id,
            $patient->visits()->latest('id')->value('branch_id'),
        ])->filter()->unique()->values();

        foreach ($candidates as $branchId) {
            $branch = Branch::query()->find((int) $branchId);

            if (
                $branch
                && (int) $branch->tenant_id === (int) $patient->tenant_id
                && $user?->belongsToTenantId((int) $branch->tenant_id)
                && $user->canAccessBranch((int) $branch->id)
            ) {
                return $branch;
            }
        }

        throw new IntegrationException('Cabang aktif tidak ditemukan untuk mengambil IHS. Pastikan akun terhubung ke cabang yang sudah dikonfigurasi SATUSEHAT.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeIhsProfile(array $data): array
    {
        $profile = [
            'name' => filled($data['name'] ?? null) ? trim((string) $data['name']) : null,
            'dob' => filled($data['dob'] ?? null) ? $data['dob'] : null,
            'gender' => filled($data['gender'] ?? null) ? $data['gender'] : null,
            'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
            'address_city' => filled($data['address_city'] ?? null) ? trim((string) $data['address_city']) : null,
            'address_postal_code' => filled($data['address_postal_code'] ?? null) ? trim((string) $data['address_postal_code']) : null,
            'province_code' => $this->wilayah->normalizeProvinceCode($data['province_code'] ?? null),
            'city_code' => $this->wilayah->normalizeCityCode($data['city_code'] ?? null),
            'district_code' => $this->wilayah->normalizeDistrictCode($data['district_code'] ?? null),
            'village_code' => $this->wilayah->normalizeVillageCode($data['village_code'] ?? null),
            'rt' => filled($data['rt'] ?? null) ? trim((string) $data['rt']) : null,
            'rw' => filled($data['rw'] ?? null) ? trim((string) $data['rw']) : null,
        ];

        return array_filter($profile, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    protected function applyIhsProfileToPatient(Patient $patient, array $profile): void
    {
        if ($profile === []) {
            return;
        }

        $fill = [];
        foreach ($profile as $key => $value) {
            if (blank($patient->{$key})) {
                $fill[$key] = $value;
            } elseif (in_array($key, ['province_code', 'city_code', 'district_code', 'village_code', 'rt', 'rw', 'address'], true)) {
                $fill[$key] = $value;
            }
        }

        if ($fill !== []) {
            $patient->forceFill($this->enrichWilayahFields($fill))->save();
        }
    }
}
