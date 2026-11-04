<?php

namespace App\Policies;

use App\Models\DoctorFeeWaiverSetting;
use App\Models\User;

class DoctorFeeWaiverSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procedure.manage_doctor_fee_waiver_policy')
            || $user->can('finance.view')
            || $user->can('audit_log.view');
    }

    public function view(User $user, DoctorFeeWaiverSetting $setting): bool
    {
        return $this->viewAny($user) && $user->belongsToTenantId((int) $setting->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->can('procedure.manage_doctor_fee_waiver_policy');
    }

    public function update(User $user, DoctorFeeWaiverSetting $setting): bool
    {
        return $user->can('procedure.manage_doctor_fee_waiver_policy')
            && $user->belongsToTenantId((int) $setting->tenant_id);
    }

    public function delete(User $user, DoctorFeeWaiverSetting $setting): bool
    {
        return $this->update($user, $setting);
    }
}
