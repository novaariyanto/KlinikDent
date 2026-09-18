<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Http\Requests\Doctor\StoreDoctorRequest;
use App\Http\Requests\Doctor\UpdateDoctorRequest;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Room;
use App\Models\User;
use App\Support\Doctors\DoctorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class DoctorController extends Controller
{
    public function __construct(protected DoctorService $doctors) {}

    public function index(): View
    {
        $this->authorize('viewAny', Doctor::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'name', 'name' => 'user.name', 'title' => 'Nama'],
            ['data' => 'role', 'name' => 'role', 'title' => 'Peran', 'orderable' => false],
            ['data' => 'specialization', 'name' => 'specialization', 'title' => 'Spesialisasi'],
            ['data' => 'sip', 'name' => 'sip', 'title' => 'SIP'],
            ['data' => 'branch', 'name' => 'user.branch.name', 'title' => 'Cabang'],
            ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('doctors.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Doctor::class);

        $query = Doctor::query()->with(['user.roles', 'user.branch', 'user.branches']);

        $actor = auth()->user();
        if ($actor) {
            $ids = $actor->restrictedBranchIds();
            if ($ids !== null) {
                $query->whereHas('user', function ($userQuery) use ($ids) {
                    $userQuery->whereIn('branch_id', $ids)
                        ->orWhereHas('branches', fn ($branches) => $branches->whereIn('branches.id', $ids));
                });
            }
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('name', fn (Doctor $doctor) => e($doctor->displayName()))
            ->addColumn('role', fn (Doctor $doctor) => '<span class="badge badge-soft-primary">'.e($doctor->roleLabel()).'</span>')
            ->editColumn('specialization', fn (Doctor $doctor) => e($doctor->specialization ?: '-'))
            ->editColumn('sip', fn (Doctor $doctor) => e($doctor->sip ?: '-'))
            ->addColumn('branch', function (Doctor $doctor) {
                $names = $doctor->user?->branches->pluck('name') ?? collect();
                if ($names->isEmpty() && $doctor->user?->branch) {
                    $names = collect([$doctor->user->branch->name]);
                }

                return e($names->implode(', ') ?: '-');
            })
            ->editColumn('is_active', function (Doctor $doctor) {
                return $doctor->is_active
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Nonaktif</span>';
            })
            ->addColumn('action', fn (Doctor $doctor) => view('doctors.partials.actions', compact('doctor'))->render())
            ->rawColumns(['role', 'is_active', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Doctor::class);

        return view('doctors.create', $this->formData());
    }

    public function store(StoreDoctorRequest $request): RedirectResponse
    {
        $doctor = $this->doctors->create($request->user(), $request->validated());

        return redirect()
            ->route('doctors.show', $doctor)
            ->with('success', 'Tenaga medis berhasil ditambahkan.');
    }

    public function show(Doctor $doctor): View
    {
        $this->authorize('view', $doctor);
        $doctor->load(['user.roles', 'user.branch', 'user.branches', 'schedules.branch', 'schedules.room']);

        return view('doctors.show', array_merge($this->formData($doctor), [
            'doctor' => $doctor,
        ]));
    }

    public function edit(Doctor $doctor): View
    {
        $this->authorize('update', $doctor);
        $doctor->load(['user.roles', 'user.branch', 'user.branches']);

        return view('doctors.edit', $this->formData($doctor));
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor): RedirectResponse
    {
        $this->doctors->update($doctor, $request->validated());

        return redirect()
            ->route('doctors.show', $doctor)
            ->with('success', 'Profil tenaga medis diperbarui.');
    }

    public function destroy(Doctor $doctor): RedirectResponse
    {
        $this->authorize('delete', $doctor);
        $this->doctors->delete($doctor);

        return redirect()
            ->route('doctors.index')
            ->with('success', 'Profil tenaga medis dihapus. Akun login tetap ada.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Doctor $doctor = null): array
    {
        $user = auth()->user();
        $branches = Branch::query()->orderBy('name');
        $rooms = Room::query()->active()->with('branch')->orderBy('name');

        if ($user) {
            $user->applyBranchLimit($branches, 'id');
            $user->applyBranchLimit($rooms);
        }

        $availableUsers = User::query()
            ->medicalStaff()
            ->whereDoesntHave('doctorProfile')
            ->orderBy('name')
            ->get();

        $roleOptions = collect(RoleName::cases())
            ->filter(fn (RoleName $role) => $role->isMedicalStaff())
            ->mapWithKeys(fn (RoleName $role) => [$role->value => $role->label()])
            ->all();

        return [
            'doctor' => $doctor,
            'branches' => $branches->get(),
            'rooms' => $rooms->get(),
            'availableUsers' => $availableUsers,
            'roleOptions' => $roleOptions,
        ];
    }
}
