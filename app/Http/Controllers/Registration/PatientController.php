<?php

namespace App\Http\Controllers\Registration;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Payer;
use App\Support\Registration\DocumentSequenceGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PatientController extends Controller
{
    public function __construct(protected DocumentSequenceGenerator $sequences) {}

    public function index(): View
    {
        $this->authorize('viewAny', Patient::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'medical_record_number', 'name' => 'medical_record_number', 'title' => 'No. RM'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Nama'],
            ['data' => 'nik', 'name' => 'nik', 'title' => 'NIK'],
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
            ->get(['id', 'medical_record_number', 'name', 'nik', 'phone', 'default_payer_id']);

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

            return Patient::query()->create($data);
        });

        activity_audit('created', $patient, [], 'Created patient '.$patient->name, 'patients');

        return redirect()->route('registration.patients.show', $patient)
            ->with('success', 'Pasien created successfully.');
    }

    public function show(Patient $patient): View
    {
        $this->authorize('view', $patient);
        $patient->load(['defaultPayer', 'visits' => fn ($query) => $query->with(['queue', 'doctor', 'room', 'payer', 'branch'])->latest('visit_date')->latest('id')]);

        return view('registration.patients.show', compact('patient'));
    }

    public function edit(Patient $patient): View
    {
        $this->authorize('update', $patient);

        return view('registration.patients.edit', $this->formData($patient));
    }

    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        $data = $request->validated();
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

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Patient $patient = null): array
    {
        return [
            'patient' => $patient,
            'payers' => Payer::query()->orderBy('name')->get(),
            'branches' => Branch::query()->orderBy('name')->get(),
            'genders' => Gender::options(),
        ];
    }
}
