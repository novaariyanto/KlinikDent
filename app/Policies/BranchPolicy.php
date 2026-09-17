<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('branch.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        if (! $user->can('branch.view')) {
            return false;
        }

        return $user->belongsToTenantId($branch->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->can('branch.manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        if (! $user->can('branch.manage')) {
            return false;
        }

        return $user->belongsToTenantId($branch->tenant_id);
    }

    public function delete(User $user, Branch $branch): bool
    {
        if (! $user->can('branch.manage')) {
            return false;
        }

        return $user->belongsToTenantId($branch->tenant_id);
    }
}
