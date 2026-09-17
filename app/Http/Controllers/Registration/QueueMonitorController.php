<?php

namespace App\Http\Controllers\Registration;

use App\Enums\QueueStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Queue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueueMonitorController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()?->can('queue.view'), 403);

        $branch = $this->branchForUser($request);

        if (! $branch) {
            return view('registration.queues.monitor-select', [
                'branches' => Branch::query()->orderBy('name')->get(),
            ]);
        }

        return $this->monitorView($branch, authenticated: true);
    }

    public function public(string $token): View
    {
        $branch = $this->branchByToken($token);

        abort_unless($branch, 404);

        return $this->monitorView($branch, authenticated: false);
    }

    public function feed(string $token): JsonResponse
    {
        $branch = $this->branchByToken($token);

        abort_unless($branch, 404);

        return response()->json($this->payload($branch));
    }

    protected function monitorView(Branch $branch, bool $authenticated): View
    {
        $payload = $this->payload($branch);

        return view('registration.queues.monitor', [
            'branch' => $branch,
            'payload' => $payload,
            'authenticated' => $authenticated,
            'feedUrl' => route('queue.monitor.feed', $branch->queue_monitor_token),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Branch $branch): array
    {
        $queues = Queue::withoutGlobalScopes()
            ->with(['visit.patient', 'visit.room', 'visit.doctor'])
            ->whereHas('visit', function ($visit) use ($branch) {
                $visit->withoutGlobalScopes()
                    ->where('branch_id', $branch->id)
                    ->whereDate('visit_date', now()->toDateString());
            })
            ->orderBy('queue_number')
            ->get();

        $current = $queues->firstWhere('status', QueueStatus::Called);
        $waiting = $queues->where('status', QueueStatus::Waiting)->values();

        return [
            'branch' => $branch->name,
            'current' => $current ? [
                'number' => $current->displayNumber(),
                'patient' => $current->visit?->patient?->name,
                'room' => $current->visit?->room?->name,
            ] : null,
            'waiting' => $waiting->take(8)->map(fn (Queue $queue) => [
                'number' => $queue->displayNumber(),
                'patient' => $queue->visit?->patient?->name,
            ])->values(),
            'updated_at' => now()->format('H:i:s'),
        ];
    }

    protected function branchByToken(string $token): ?Branch
    {
        return Branch::withoutGlobalScopes()
            ->where('queue_monitor_token', $token)
            ->where('is_active', true)
            ->first();
    }

    protected function branchForUser(Request $request): ?Branch
    {
        $user = $request->user();

        if ($request->filled('branch_id')) {
            return Branch::query()->find($request->integer('branch_id'));
        }

        if ($user?->branch_id) {
            return Branch::query()->find($user->branch_id);
        }

        return Branch::query()->orderBy('id')->first();
    }
}
