<?php

namespace App\Policies;

use App\Enums\QueueStatus;
use App\Enums\RoleName;
use App\Models\Queue;
use App\Models\User;

class QueuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('queue.view');
    }

    public function view(User $user, Queue $queue): bool
    {
        if (! $user->can('queue.view') || ! $user->belongsToTenantId($queue->tenantId())) {
            return false;
        }

        $visit = $queue->visit;

        if (! $visit) {
            return false;
        }

        if ($user->can('branch.manage') || ! $user->hasRole(RoleName::Dentist)) {
            return true;
        }

        return $visit->doctor_id === null || (int) $visit->doctor_id === (int) $user->id;
    }

    public function manage(User $user, Queue $queue): bool
    {
        if (! $user->can('queue.manage') || ! $this->view($user, $queue)) {
            return false;
        }

        return $queue->status->isActive();
    }

    public function call(User $user, Queue $queue): bool
    {
        return $this->manage($user, $queue) && $queue->status === QueueStatus::Waiting;
    }

    public function complete(User $user, Queue $queue): bool
    {
        return $this->manage($user, $queue);
    }

    public function skip(User $user, Queue $queue): bool
    {
        return $this->manage($user, $queue);
    }
}
