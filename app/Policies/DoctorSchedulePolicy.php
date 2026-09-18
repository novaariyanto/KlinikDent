<?php

namespace App\Policies;

use App\Models\DoctorSchedule;
use App\Policies\Concerns\AuthorizesTenantResource;
use Illuminate\Database\Eloquent\Model;

class DoctorSchedulePolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'schedule.view';
    }

    protected function managePermission(): string
    {
        return 'schedule.manage';
    }

    protected function tenantIdOf(Model $model): ?int
    {
        /** @var DoctorSchedule $model */
        $tenantId = $model->tenant_id;

        return $tenantId !== null ? (int) $tenantId : null;
    }
}
