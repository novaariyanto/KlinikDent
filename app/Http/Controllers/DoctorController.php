<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Enums\Weekday;
use App\Http\Requests\Doctor\StoreDoctorRequest;
use App\Http\Requests\Doctor\UpdateDoctorRequest;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Room;
use App\Models\User;
use App\Support\Doctors\DoctorService;
use App\Support\Integrations\IntegrationException;
use App\Support\Integrations\IntegrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorController extends Controller
{
    public function __construct(protected DoctorService $doctors) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Doctor::class);

        $base = $this->visibleDoctors();
        $filters = $this->indexFilters($request);

        $stats = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'dentists' => (clone $base)->whereHas('user', fn (Builder $user) => $user->role(RoleName::Dentist->value))->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
        ];

        $query = $this->visibleDoctors();
        $this->applyIndexFilters($query, $filters);

        $doctors = $query
            ->with([
                'user.roles',
                'user.branch',
                'user.branches',
                'schedules' => fn ($schedules) => $schedules->today()->active()->with(['branch', 'room']),
            ])
            ->orderBy(
                User::query()->select('name')->whereColumn('users.id', 'doctors.user_id')->limit(1)
            )
            ->paginate(12)
            ->withQueryString();

        $branches = Branch::query()->orderBy('name');
        $request->user()?->applyBranchLimit($branches, 'id');

        $roleOptions = collect(RoleName::cases())
            ->filter(fn (RoleName $role) => $role->isMedicalStaff())
            ->mapWithKeys(fn (RoleName $role) => [$role->value => $role->label()])
            ->all();

        return view('doctors.index', [
            'doctors' => $doctors,
            'stats' => $stats,
            'filters' => $filters,
            'branches' => $branches->get(),
            'roleOptions' => $roleOptions,
            'today' => Weekday::today(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Doctor::class);

        return view('doctors.create', $this->formData());
    }

    public function store(StoreDoctorRequest $request): RedirectResponse
    {
        $this->doctors->create($request->user(), $request->validated());

        return redirect()
            ->route('doctors.index')
            ->with('success', 'Tenaga medis berhasil ditambahkan.');
    }

    public function edit(Doctor $doctor): View
    {
        $this->authorize('update', $doctor);
        $doctor->load(['user.roles', 'user.branch', 'user.branches']);

        return view('doctors.edit', $this->formData($doctor));
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor): RedirectResponse
    {
        if ($request->hasFile('signature')) {
            $path = $request->file('signature')->store('signatures/'.$doctor->tenant_id, 'public');
            $doctor->update(['signature_path' => $path]);
        }

        $this->doctors->update($doctor, $request->safe()->except('signature'));

        return redirect()
            ->route('doctors.index')
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

    public function fetchIhs(Doctor $doctor, IntegrationService $integrations): RedirectResponse
    {
        $this->authorize('update', $doctor);

        try {
            $ihs = $integrations->lookupDoctorIhs($doctor);
        } catch (IntegrationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'IHS '.$doctor->displayName().' tersimpan: '.$ihs);
    }

    /**
     * @return Builder<Doctor>
     */
    protected function visibleDoctors(): Builder
    {
        $query = Doctor::query();
        $actor = auth()->user();

        if ($actor) {
            $ids = $actor->restrictedBranchIds();
            if ($ids !== null) {
                $query->whereHas('user', function (Builder $userQuery) use ($ids) {
                    $userQuery->whereIn('branch_id', $ids)
                        ->orWhereHas('branches', fn (Builder $branches) => $branches->whereIn('branches.id', $ids));
                });
            }
        }

        return $query;
    }

    /**
     * @return array{q: string, role: string, status: string, branch: int|string}
     */
    protected function indexFilters(Request $request): array
    {
        $role = (string) $request->query('role', '');
        if (! in_array($role, RoleName::medicalStaffValues(), true)) {
            $role = '';
        }

        $status = (string) $request->query('status', '');
        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = '';
        }

        $branchId = $request->integer('branch');
        $allowed = $request->user()?->restrictedBranchIds();
        if ($branchId <= 0 || ($allowed !== null && ! in_array($branchId, $allowed, true))) {
            $branchId = 0;
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'role' => $role,
            'status' => $status,
            'branch' => $branchId > 0 ? $branchId : '',
        ];
    }

    /**
     * @param  Builder<Doctor>  $query
     * @param  array{q: string, role: string, status: string, branch: int|string}  $filters
     */
    protected function applyIndexFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function (Builder $inner) use ($like) {
                $inner->where('specialization', 'like', $like)
                    ->orWhere('sip', 'like', $like)
                    ->orWhere('str', 'like', $like)
                    ->orWhere('nik', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhereHas('user', function (Builder $user) use ($like) {
                        $user->where('name', 'like', $like)->orWhere('email', 'like', $like);
                    });
            });
        }

        if ($filters['role'] !== '') {
            $query->whereHas('user', fn (Builder $user) => $user->role($filters['role']));
        }

        if ($filters['status'] !== '') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if ($filters['branch'] !== '') {
            $branchId = (int) $filters['branch'];
            $query->whereHas('user', function (Builder $user) use ($branchId) {
                $user->where('branch_id', $branchId)
                    ->orWhereHas('branches', fn (Builder $branches) => $branches->where('branches.id', $branchId));
            });
        }
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
