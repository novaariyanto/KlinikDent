<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Policies\Concerns\AuthorizesTenantResource;
use Illuminate\Database\Eloquent\Model;

class DoctorPolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'doctor.view';
    }

    protected function managePermission(): string
    {
        return 'doctor.manage';
    }

    protected function tenantIdOf(Model $model): ?int
    {
        /** @var Doctor $model */
        $tenantId = $model->tenant_id;

        return $tenantId !== null ? (int) $tenantId : null;
    }
}
