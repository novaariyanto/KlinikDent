<?php

namespace App\Policies;

use App\Models\DoctorFeeWaiverLog;
use App\Models\User;

class DoctorFeeWaiverLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procedure.manage_doctor_fee_waiver_policy')
            || $user->can('procedure.approve_doctor_fee_waiver')
            || $user->can('finance.view')
            || $user->can('audit_log.view')
            || $user->can('report.view');
    }

    public function view(User $user, DoctorFeeWaiverLog $log): bool
    {
        return $this->viewAny($user)
            && $user->belongsToTenantId((int) $log->tenant_id)
            && ($log->branch_id === null || $user->canAccessBranch((int) $log->branch_id));
    }

    public function approve(User $user, DoctorFeeWaiverLog $log): bool
    {
        return $user->can('procedure.approve_doctor_fee_waiver')
            && $user->belongsToTenantId((int) $log->tenant_id)
            && $log->isPending();
    }
}
