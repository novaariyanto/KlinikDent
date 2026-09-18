<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;
use App\Models\Visit;

class VisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('visit.view')
            || $user->can('registration.view')
            || $user->can('patient_history.view')
            || $user->can('queue.view');
    }

    public function view(User $user, Visit $visit): bool
    {
        if (! $this->viewAny($user) || ! $user->belongsToTenantId($visit->tenant_id) || ! $user->canAccessBranch((int) $visit->branch_id)) {
            return false;
        }

        return $this->sameDoctorScope($user, $visit);
    }

    public function create(User $user): bool
    {
        return $user->can('registration.create');
    }

    public function update(User $user, Visit $visit): bool
    {
        return $user->can('registration.update')
            && $user->belongsToTenantId($visit->tenant_id)
            && $user->canAccessBranch((int) $visit->branch_id);
    }

    public function cancel(User $user, Visit $visit): bool
    {
        if (! $visit->status->isOpen()) {
            return false;
        }

        return $user->can('registration.cancel')
            && $user->belongsToTenantId($visit->tenant_id)
            && $user->canAccessBranch((int) $visit->branch_id);
    }

    public function delete(User $user, Visit $visit): bool
    {
        return false;
    }

    protected function sameDoctorScope(User $user, Visit $visit): bool
    {
        if ($user->can('branch.manage') || ! $user->hasRole(RoleName::Dentist)) {
            return true;
        }

        return $visit->doctor_id === null || (int) $visit->doctor_id === (int) $user->id;
    }
}
