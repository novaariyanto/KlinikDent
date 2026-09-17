<?php

namespace App\Http\Controllers\Registration;

use App\Enums\Gender;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Visit\StoreVisitRequest;
use App\Models\Branch;
use App\Models\Payer;
use App\Models\Patient;
use App\Models\Room;
use App\Models\User;
use App\Models\Visit;
use App\Support\Registration\VisitRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class VisitController extends Controller
{
    public function __construct(protected VisitRegistrar $registrar) {}

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

    public function store(StoreVisitRequest $request): RedirectResponse
    {
        $visit = $this->registrar->register($request->user(), $request->validated());

        activity_log('created', $visit, $request->validated(), 'Registered visit for '.$visit->patient?->name, 'visits');

        return redirect()
            ->route('registration.visits.show', $visit)
            ->with('success', 'Pendaftaran berhasil. Nomor antrean '.$visit->queue?->displayNumber().'.');
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

        $visit->update(['status' => VisitStatus::Cancelled]);
        $visit->queue?->update(['status' => QueueStatus::Skipped]);

        activity_log('cancelled', $visit, ['status' => VisitStatus::Cancelled->value], 'Cancelled visit', 'visits');

        return back()->with('success', 'Kunjungan dibatalkan.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Patient $patient = null): array
    {
        $user = auth()->user();

        return [
            'patient' => $patient,
            'payers' => Payer::query()->orderBy('name')->get(),
            'branches' => Branch::query()->orderBy('name')->get(),
            'rooms' => Room::query()->active()->with('branch')->orderBy('name')->get(),
            'doctors' => User::query()->doctors()->orderBy('name')->get(),
            'genders' => Gender::options(),
            'defaultBranchId' => $user?->branch_id,
        ];
    }
}
