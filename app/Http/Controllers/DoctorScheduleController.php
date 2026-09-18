<?php

namespace App\Http\Controllers;

use App\Enums\Weekday;
use App\Http\Requests\Doctor\StoreDoctorScheduleRequest;
use App\Http\Requests\Doctor\UpdateDoctorScheduleRequest;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Room;
use App\Models\User;
use App\Support\Doctors\DoctorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorScheduleController extends Controller
{
    public function __construct(protected DoctorService $doctors) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DoctorSchedule::class);

        $query = DoctorSchedule::query()
            ->with(['doctor.user', 'branch', 'room'])
            ->orderBy('weekday')
            ->orderBy('start_time');

        $actor = $request->user();
        if ($actor) {
            $actor->applyBranchLimit($query);
        }

        $schedules = $query->get()->groupBy(fn (DoctorSchedule $schedule) => $schedule->weekday->value);

        return view('doctors.schedule.index', [
            'grouped' => $schedules,
            'weekdays' => Weekday::cases(),
        ]);
    }

    public function today(Request $request): View
    {
        $this->authorize('viewAny', DoctorSchedule::class);

        $query = DoctorSchedule::query()
            ->active()
            ->today()
            ->whereHas('doctor', fn ($doctor) => $doctor->active())
            ->with(['doctor.user', 'branch', 'room'])
            ->orderBy('start_time');

        $actor = $request->user();
        if ($actor) {
            $actor->applyBranchLimit($query);
        }

        return view('doctors.schedule.today', [
            'schedules' => $query->get(),
            'weekday' => Weekday::today(),
        ]);
    }

    public function mine(Request $request): View
    {
        $this->authorize('viewAny', DoctorSchedule::class);

        $user = $request->user();
        $doctor = $user?->doctorProfile;

        if ($user && ! $user->can('schedule.manage') && $user->isDentist() && ! $doctor) {
            return view('doctors.schedule.mine', [
                'doctor' => null,
                'schedules' => collect(),
                'weekdays' => Weekday::cases(),
            ]);
        }

        if ($user && ! $user->can('doctor.view') && $doctor) {
            abort_unless((int) $doctor->user_id === (int) $user->id, 403);
        }

        $query = DoctorSchedule::query()
            ->with(['branch', 'room', 'doctor.user'])
            ->orderBy('weekday')
            ->orderBy('start_time');

        if ($doctor) {
            $query->where('doctor_id', $doctor->id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return view('doctors.schedule.mine', [
            'doctor' => $doctor,
            'schedules' => $query->get()->groupBy(fn (DoctorSchedule $schedule) => $schedule->weekday->value),
            'weekdays' => Weekday::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', DoctorSchedule::class);

        return view('doctors.schedule.create', array_merge($this->formOptions($request->user()), [
            'selectedDoctorId' => $request->integer('doctor_id') ?: null,
            'selectedWeekday' => $request->integer('weekday') ?: null,
        ]));
    }

    public function store(StoreDoctorScheduleRequest $request): RedirectResponse
    {
        $doctor = $request->doctor();
        abort_unless($doctor, 404);

        $this->doctors->addSchedule($doctor, $request->validated());

        if ($this->returnsToBoard($request)) {
            return redirect()->route('doctors.schedule')->with('success', 'Jadwal praktik ditambahkan.');
        }

        return back()->with('success', 'Jadwal praktik ditambahkan.');
    }

    public function edit(Request $request, DoctorSchedule $doctorSchedule): View
    {
        $this->authorize('update', $doctorSchedule);
        $doctorSchedule->load(['doctor.user', 'branch', 'room']);

        return view('doctors.schedule.edit', array_merge($this->formOptions($request->user()), [
            'schedule' => $doctorSchedule,
            'from' => $request->query('from', 'doctor'),
        ]));
    }

    public function update(UpdateDoctorScheduleRequest $request, DoctorSchedule $doctorSchedule): RedirectResponse
    {
        $this->doctors->updateSchedule($doctorSchedule, $request->validated());

        if ($this->returnsToBoard($request)) {
            return redirect()->route('doctors.schedule')->with('success', 'Jadwal praktik diperbarui.');
        }

        return redirect()
            ->route('doctors.show', $doctorSchedule->doctor_id)
            ->with('success', 'Jadwal praktik diperbarui.');
    }

    public function destroy(Request $request, DoctorSchedule $doctorSchedule): RedirectResponse
    {
        $this->authorize('delete', $doctorSchedule);
        $doctorId = $doctorSchedule->doctor_id;
        $this->doctors->deleteSchedule($doctorSchedule);

        if ($this->returnsToBoard($request)) {
            return redirect()->route('doctors.schedule')->with('success', 'Jadwal praktik dihapus.');
        }

        return redirect()
            ->route('doctors.show', $doctorId)
            ->with('success', 'Jadwal praktik dihapus.');
    }

    protected function returnsToBoard(Request $request): bool
    {
        return $request->input('from', $request->query('from')) === 'schedule'
            || $request->routeIs('doctors.schedule.store');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(?User $user): array
    {
        $doctors = Doctor::query()->active()->with(['user.roles']);
        $branches = Branch::query()->orderBy('name');
        $rooms = Room::query()->active()->with('branch')->orderBy('name');

        if ($user) {
            $user->applyBranchLimit($branches, 'id');
            $user->applyBranchLimit($rooms);

            $ids = $user->restrictedBranchIds();
            if ($ids !== null) {
                $doctors->whereHas('user', function ($userQuery) use ($ids) {
                    $userQuery->whereIn('branch_id', $ids)
                        ->orWhereHas('branches', fn ($assigned) => $assigned->whereIn('branches.id', $ids));
                });
            }
        }

        return [
            'doctors' => $doctors->get()->sortBy(fn (Doctor $doctor) => $doctor->displayName(), SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'branches' => $branches->get(),
            'rooms' => $rooms->get(),
        ];
    }
}
