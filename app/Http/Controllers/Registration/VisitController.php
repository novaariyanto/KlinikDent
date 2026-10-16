<?php

namespace App\Http\Controllers\Registration;

use App\Enums\Gender;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Visit\StoreVisitRequest;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Room;
use App\Models\User;
use App\Models\Visit;
use App\Support\Doctors\ScheduleAvailability;
use App\Support\Indonesia\WilayahService;
use App\Support\Integrations\IntegrationException;
use App\Support\Integrations\IntegrationService;
use App\Support\Registration\VisitRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class VisitController extends Controller
{
    public function __construct(
        protected VisitRegistrar $registrar,
        protected ScheduleAvailability $schedules,
        protected WilayahService $wilayah,
        protected IntegrationService $integrations,
    ) {}

    public function hub(): View
    {
        $this->authorize('viewAny', Visit::class);

        $today = Visit::query()->visibleTo(auth()->user())->today();

        $stats = [
            'visits_today' => (clone $today)->count(),
            'waiting' => (clone $today)->where('status', VisitStatus::Waiting)->count(),
            'in_service' => (clone $today)->where('status', VisitStatus::InService)->count(),
            'done' => (clone $today)->where('status', VisitStatus::Done)->count(),
        ];

        $recent = Visit::query()
            ->visibleTo(auth()->user())
            ->with(['patient', 'queue', 'doctor', 'room'])
            ->today()
            ->latest('id')
            ->limit(8)
            ->get();

        return view('registration.hub', compact('stats', 'recent'));
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Visit::class);

        $todayOnly = $request->routeIs('registration.visits.today');
        $title = $todayOnly ? 'Kunjungan Hari Ini' : 'Riwayat Kunjungan';

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'visit_date', 'name' => 'visit_date', 'title' => 'Tanggal'],
            ['data' => 'queue', 'name' => 'queue.queue_number', 'title' => 'Antrean'],
            ['data' => 'patient', 'name' => 'patient.name', 'title' => 'Pasien'],
            ['data' => 'doctor', 'name' => 'doctor.name', 'title' => 'Dokter'],
            ['data' => 'room', 'name' => 'room.name', 'title' => 'Poli'],
            ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('registration.visits.index', compact('columns', 'title', 'todayOnly'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Visit::class);

        $query = Visit::query()
            ->visibleTo($request->user())
            ->with(['patient', 'queue', 'doctor', 'room']);

        if ($request->boolean('today')) {
            $query->today();
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('visit_date', fn (Visit $visit) => $visit->visit_date?->format('d M Y'))
            ->addColumn('queue', fn (Visit $visit) => e($visit->queue?->displayNumber() ?: '-'))
            ->addColumn('patient', fn (Visit $visit) => e($visit->patient?->name ?: '-'))
            ->addColumn('doctor', fn (Visit $visit) => e($visit->doctor?->name ?: '-'))
            ->addColumn('room', fn (Visit $visit) => e($visit->room?->name ?: '-'))
            ->editColumn('status', fn (Visit $visit) => '<span class="'.$visit->status->badgeClass().'">'.$visit->status->label().'</span>')
            ->addColumn('action', fn (Visit $visit) => view('registration.visits.partials.actions', compact('visit'))->render())
            ->rawColumns(['status', 'action'])
            ->toJson();
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Visit::class);

        $patient = null;
        if ($request->filled('patient_id')) {
            $patient = Patient::query()->find($request->integer('patient_id'));
            if ($patient) {
                $this->authorize('view', $patient);
            }
        }

        return view('registration.visits.create', $this->formData($patient));
    }

    public function lookupPatientIhs(Request $request): JsonResponse
    {
        $this->authorize('create', Visit::class);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'nik' => ['nullable', 'digits:16'],
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

        $user = $request->user();
        $branch = Branch::query()->findOrFail((int) $data['branch_id']);

        abort_unless(
            $user && $user->belongsToTenantId((int) $branch->tenant_id) && $user->canAccessBranch((int) $branch->id),
            403
        );

        $profile = $this->normalizeIhsProfile($data);

        try {
            if (! empty($data['patient_id'])) {
                $patient = Patient::query()->findOrFail((int) $data['patient_id']);
                $this->authorize('view', $patient);

                if ((int) $patient->tenant_id !== (int) $branch->tenant_id) {
                    return response()->json(['message' => 'Pasien tidak ditemukan pada klinik ini.'], 422);
                }

                $this->applyIhsProfileToPatient($patient, $profile);

                $ihs = $this->integrations->fetchPatientIhs($patient->fresh(), $branch);

                return response()->json([
                    'ok' => true,
                    'ihs' => $ihs,
                    'patient_id' => $patient->id,
                    'message' => 'IHS pasien: '.$ihs,
                ]);
            }

            $nik = (string) ($data['nik'] ?? '');

            if ($nik === '') {
                return response()->json(['message' => 'Pilih pasien atau isi NIK 16 digit.'], 422);
            }

            $existing = Patient::query()->where('nik', $nik)->first();

            if ($existing) {
                if ((int) $existing->tenant_id !== (int) $branch->tenant_id) {
                    return response()->json(['message' => 'Pasien tidak ditemukan pada klinik ini.'], 422);
                }

                $this->applyIhsProfileToPatient($existing, $profile);
                $ihs = $this->integrations->fetchPatientIhs($existing->fresh(), $branch);

                return response()->json([
                    'ok' => true,
                    'ihs' => $ihs,
                    'patient_id' => $existing->id,
                    'medical_record_number' => $existing->medical_record_number,
                    'name' => $existing->name,
                    'message' => 'Pasien lokal ditemukan. IHS: '.$ihs,
                ]);
            }

            try {
                $ihs = $this->integrations->searchPatientIhsByNik((int) $branch->tenant_id, $branch, $nik);

                return response()->json([
                    'ok' => true,
                    'ihs' => $ihs,
                    'patient_id' => null,
                    'message' => 'IHS ditemukan di SATUSEHAT: '.$ihs,
                ]);
            } catch (IntegrationException $searchException) {
                // NIK belum di MPI → daftar memakai data form (wajib desa/kelurahan Kemendagri).
                $missing = $this->missingIhsRegisterFields($profile, $nik);
                if ($missing !== []) {
                    throw new IntegrationException(
                        'NIK pasien tidak ditemukan di MPI SATUSEHAT. Pendaftaran IHS membutuhkan '
                        .implode(', ', $missing)
                        .' sesuai KTP (kode wilayah = Master Wilayah SATUSEHAT / Kemendagri).'
                    );
                }

                $draft = new Patient(array_merge($profile, [
                    'tenant_id' => $branch->tenant_id,
                    'nik' => $nik,
                ]));

                $ihs = $this->integrations->registerPatientIhsDraft($draft, $branch);

                return response()->json([
                    'ok' => true,
                    'ihs' => $ihs,
                    'patient_id' => null,
                    'message' => 'NIK belum ada di MPI. IHS baru didaftarkan: '.$ihs.'. Simpan pendaftaran agar IHS tersimpan ke rekam medis.',
                ]);
            }
        } catch (IntegrationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
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
                // Izinkan update wilayah dari form saat Cari IHS (desa sering belum tersimpan).
                $fill[$key] = $value;
            }
        }

        if ($fill !== []) {
            $patient->forceFill($fill)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return list<string>
     */
    protected function missingIhsRegisterFields(array $profile, string $nik): array
    {
        if (strlen($nik) !== 16) {
            return ['NIK 16 digit'];
        }

        $labels = [
            'name' => 'nama',
            'dob' => 'tanggal lahir',
            'gender' => 'jenis kelamin',
            'address' => 'alamat',
            'province_code' => 'kode provinsi',
            'city_code' => 'kode kab/kota',
            'district_code' => 'kode kecamatan',
            'village_code' => 'kode desa/kelurahan',
            'rt' => 'RT',
            'rw' => 'RW',
        ];

        $missing = [];
        foreach ($labels as $key => $label) {
            if (blank($profile[$key] ?? null)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function availability(Request $request): JsonResponse
    {
        $this->authorize('create', Visit::class);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'visit_date' => ['required', 'date'],
        ]);

        $user = $request->user();
        $branch = Branch::query()->findOrFail((int) $data['branch_id']);

        abort_unless(
            $user && $user->belongsToTenantId((int) $branch->tenant_id) && $user->canAccessBranch((int) $branch->id),
            403
        );

        $roomId = ! empty($data['room_id']) ? (int) $data['room_id'] : null;

        if ($roomId) {
            $room = Room::query()->find($roomId);

            if (! $room || (int) $room->branch_id !== (int) $branch->id) {
                return response()->json(['doctors' => []]);
            }
        }

        return response()->json([
            'doctors' => $this->schedules->doctorsFor((int) $branch->id, $data['visit_date'], $roomId),
        ]);
    }

    public function store(StoreVisitRequest $request): RedirectResponse
    {
        $visit = $this->registrar->register($request->user(), $request->validated());

        activity_log('created', $visit, $request->validated(), 'Registered visit for '.$visit->patient?->name, 'visits');

        $queueNumber = $visit->queue?->displayNumber();
        $ihs = $visit->patient?->satusehat_id;
        $ihsNote = $ihs ? ' IHS '.$ihs.'.' : '';
        $message = $this->registrar->reusedExistingPatient
            ? 'NIK sudah terdaftar pada '.$visit->patient?->name.' (RM '.$visit->patient?->medical_record_number.'). Kunjungan dicatat pada rekam medis itu. Nomor antrean '.$queueNumber.'.'.$ihsNote
            : 'Pendaftaran berhasil. Nomor antrean '.$queueNumber.'.'.$ihsNote;

        return redirect()
            ->route('registration.visits.show', $visit)
            ->with('success', $message);
    }

    public function show(Visit $visit): View
    {
        $this->authorize('view', $visit);
        $visit->load(['patient', 'queue', 'doctor', 'room', 'payer', 'branch']);

        return view('registration.visits.show', compact('visit'));
    }

    public function cancel(Visit $visit): RedirectResponse
    {
        $this->authorize('cancel', $visit);

        $before = activity_snapshot($visit);
        $visit->update(['status' => VisitStatus::Cancelled]);
        $visit->queue?->update(['status' => QueueStatus::Skipped]);

        activity_audit('cancelled', $visit, $before, 'Cancelled visit', 'visits', [
            'status' => VisitStatus::Cancelled->value,
        ]);

        return back()->with('success', 'Kunjungan dibatalkan.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Patient $patient = null): array
    {
        $user = auth()->user();
        $branches = Branch::query()->orderBy('name');
        $rooms = Room::query()
            ->active()
            ->with('branch')
            ->orderByRaw("CASE WHEN type = 'poli' THEN 0 ELSE 1 END")
            ->orderBy('name');

        if ($user) {
            $user->applyBranchLimit($branches, 'id');
            $user->applyBranchLimit($rooms);
        }

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
            'branches' => $branches->get(),
            'rooms' => $rooms->get(),
            'doctors' => User::query()
                ->doctors()
                ->where(function ($query) {
                    $query->whereDoesntHave('doctorProfile')
                        ->orWhereHas('doctorProfile', fn ($profile) => $profile->where('is_active', true));
                })
                ->orderBy('name')
                ->get(),
            'genders' => Gender::options(),
            'defaultBranchId' => $user?->branch_id,
            'provinces' => $this->wilayah->provinces(),
            'cities' => $this->wilayah->cities((string) $provinceCode),
            'districts' => $this->wilayah->districts((string) $cityCode),
            'villages' => $this->wilayah->villages((string) $districtCode),
        ];
    }
}
