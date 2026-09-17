<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('patient.view');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->can('patient.view')
            && $user->belongsToTenantId($patient->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->can('patient.create');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->can('patient.update')
            && $user->belongsToTenantId($patient->tenant_id);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->can('patient.delete')
            && $user->belongsToTenantId($patient->tenant_id);
    }
}
