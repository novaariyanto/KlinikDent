<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('logs.view');
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $user->can('logs.view');
    }

    public function delete(User $user, ActivityLog $activityLog): bool
    {
        return $user->can('logs.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('logs.delete');
    }
}
