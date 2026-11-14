<?php

namespace App\Http\Controllers\Api;

use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Registration\QueueController as RegistrationQueueController;
use App\Models\Branch;
use App\Models\Queue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    use RespondsWithPage;

    public function __construct(protected RegistrationQueueController $registrationQueues) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Queue::class);

        $scope = $request->query('scope', 'today');
        $query = $this->registrationQueues->filteredQuery(
            $request,
            is_string($scope) && $scope !== '' ? $scope : 'today',
        );

        $activeBranch = $request->attributes->get('active_branch');

        if ($activeBranch instanceof Branch) {
            $query->whereHas('visit', fn ($visit) => $visit->where('branch_id', $activeBranch->id));
        }

        return $this->paged($request, $query, fn (Queue $queue) => $this->payload($queue));
    }

    public function call(Queue $queue): JsonResponse
    {
        $this->authorize('call', $queue);

        $queue->update([
            'status' => QueueStatus::Called,
            'called_at' => now(),
        ]);
        $queue->visit?->update(['status' => VisitStatus::InService]);

        activity_log('updated', $queue, ['status' => QueueStatus::Called->value], 'Called queue '.$queue->displayNumber(), 'queues');

        return response()->json([
            'data' => $this->payload($queue),
            'message' => 'Nomor '.$queue->displayNumber().' dipanggil.',
        ]);
    }

    public function complete(Queue $queue): JsonResponse
    {
        $this->authorize('complete', $queue);

        $queue->update(['status' => QueueStatus::Done]);
        $queue->visit?->update(['status' => VisitStatus::Done]);

        activity_log('updated', $queue, ['status' => QueueStatus::Done->value], 'Completed queue '.$queue->displayNumber(), 'queues');

        return response()->json([
            'data' => $this->payload($queue),
            'message' => 'Nomor '.$queue->displayNumber().' selesai.',
        ]);
    }

    public function skip(Queue $queue): JsonResponse
    {
        $this->authorize('skip', $queue);

        $queue->update(['status' => QueueStatus::Skipped]);
        $queue->visit?->update(['status' => VisitStatus::Cancelled]);

        activity_log('updated', $queue, ['status' => QueueStatus::Skipped->value], 'Skipped queue '.$queue->displayNumber(), 'queues');

        return response()->json([
            'data' => $this->payload($queue),
            'message' => 'Nomor '.$queue->displayNumber().' dilewati.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Queue $queue): array
    {
        $queue->loadMissing(['visit.patient', 'visit.doctor', 'visit.room']);

        return [
            'id' => $queue->id,
            'queue_number' => $queue->displayNumber(),
            'status' => $queue->status->value,
            'called_at' => $queue->called_at?->toIso8601String(),
            'visit_id' => $queue->visit_id,
            'patient' => $queue->visit?->patient?->name,
            'doctor' => $queue->visit?->doctor?->name,
            'room' => $queue->visit?->room?->name,
        ];
    }
}
