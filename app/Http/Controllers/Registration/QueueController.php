<?php

namespace App\Http\Controllers\Registration;

use App\Enums\QueueStatus;
use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Queue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class QueueController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Queue::class);

        $filter = $this->filterFromRoute($request);
        $title = match ($filter) {
            'mine' => 'Antrean Saya',
            'waiting' => 'Pasien Menunggu',
            'called' => 'Pasien Dipanggil',
            default => 'Antrean Hari Ini',
        };

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'queue_number', 'name' => 'queue_number', 'title' => 'Nomor'],
            ['data' => 'patient', 'name' => 'visit.patient.name', 'title' => 'Pasien'],
            ['data' => 'doctor', 'name' => 'visit.doctor.name', 'title' => 'Dokter'],
            ['data' => 'room', 'name' => 'visit.room.name', 'title' => 'Poli'],
            ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'called_at', 'name' => 'called_at', 'title' => 'Dipanggil'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '140px'],
        ];

        return view('registration.queues.index', compact('columns', 'title', 'filter'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Queue::class);

        $query = $this->filteredQuery($request);

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('queue_number', fn (Queue $queue) => e($queue->displayNumber()))
            ->addColumn('patient', fn (Queue $queue) => e($queue->visit?->patient?->name ?: '-'))
            ->addColumn('doctor', fn (Queue $queue) => e($queue->visit?->doctor?->name ?: '-'))
            ->addColumn('room', fn (Queue $queue) => e($queue->visit?->room?->name ?: '-'))
            ->editColumn('status', fn (Queue $queue) => '<span class="'.$queue->status->badgeClass().'">'.$queue->status->label().'</span>')
            ->editColumn('called_at', fn (Queue $queue) => $queue->called_at?->format('H:i') ?: '-')
            ->addColumn('action', fn (Queue $queue) => view('registration.queues.partials.actions', compact('queue'))->render())
            ->rawColumns(['status', 'action'])
            ->toJson();
    }

    public function call(Queue $queue): RedirectResponse
    {
        $this->authorize('call', $queue);

        $queue->update([
            'status' => QueueStatus::Called,
            'called_at' => now(),
        ]);
        $queue->visit?->update(['status' => VisitStatus::InService]);

        activity_log('updated', $queue, ['status' => QueueStatus::Called->value], 'Called queue '.$queue->displayNumber(), 'queues');

        return back()->with('success', 'Nomor '.$queue->displayNumber().' dipanggil.');
    }

    public function complete(Queue $queue): RedirectResponse
    {
        $this->authorize('complete', $queue);

        $queue->update(['status' => QueueStatus::Done]);
        $queue->visit?->update(['status' => VisitStatus::Done]);

        activity_log('updated', $queue, ['status' => QueueStatus::Done->value], 'Completed queue '.$queue->displayNumber(), 'queues');

        return back()->with('success', 'Nomor '.$queue->displayNumber().' selesai.');
    }

    public function skip(Queue $queue): RedirectResponse
    {
        $this->authorize('skip', $queue);

        $queue->update(['status' => QueueStatus::Skipped]);
        $queue->visit?->update(['status' => VisitStatus::Cancelled]);

        activity_log('updated', $queue, ['status' => QueueStatus::Skipped->value], 'Skipped queue '.$queue->displayNumber(), 'queues');

        return back()->with('success', 'Nomor '.$queue->displayNumber().' dilewati.');
    }

    protected function filterFromRoute(Request $request): string
    {
        if ($request->routeIs('queue.mine')) {
            return 'mine';
        }

        if ($request->routeIs('queue.waiting')) {
            return 'waiting';
        }

        if ($request->routeIs('queue.called')) {
            return 'called';
        }

        return 'today';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Queue>
     */
    protected function filteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $user = $request->user();
        $filter = $request->query('filter', $this->filterFromRoute($request));

        $query = Queue::query()
            ->with(['visit.patient', 'visit.doctor', 'visit.room'])
            ->whereHas('visit', function ($visit) use ($user) {
                $visit->visibleTo($user)->today();
            });

        if ($filter === 'waiting') {
            $query->where('status', QueueStatus::Waiting);
        } elseif ($filter === 'called') {
            $query->where('status', QueueStatus::Called);
        } elseif ($filter === 'mine' && $user?->hasRole(RoleName::Dentist)) {
            $query->whereHas('visit', fn ($visit) => $visit->where('doctor_id', $user->id));
        }

        return $query->orderBy('queue_number');
    }
}
