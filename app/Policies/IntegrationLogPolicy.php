<?php

namespace App\Policies;

use App\Models\IntegrationLog;
use App\Models\User;

class IntegrationLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('integration.view') || $user->can('saas.integration.view');
    }

    public function view(User $user, IntegrationLog $log): bool
    {
        if ($user->can('saas.integration.view') && $user->isPlatformAdmin()) {
            return true;
        }

        return $user->can('integration.view') && $user->belongsToTenantId($log->tenant_id);
    }
}
