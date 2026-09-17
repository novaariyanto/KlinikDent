<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tenant.view');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        if (! $user->can('tenant.view')) {
            return false;
        }

        return $user->isPlatformAdmin() || (int) $user->tenant_id === (int) $tenant->id;
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin() && $user->can('tenant.create');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformAdmin() && $user->can('tenant.update');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformAdmin() && $user->can('tenant.manage');
    }

    public function toggleStatus(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformAdmin() && $user->can('tenant.manage');
    }
}
