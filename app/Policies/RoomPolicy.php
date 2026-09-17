<?php

namespace App\Policies;

use App\Models\Room;
use App\Policies\Concerns\AuthorizesTenantResource;
use Illuminate\Database\Eloquent\Model;

class RoomPolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'room.view';
    }

    protected function managePermission(): string
    {
        return 'room.manage';
    }

    protected function tenantIdOf(Model $model): ?int
    {
        /** @var Room $model */
        $tenantId = $model->tenantId();

        return $tenantId !== null ? (int) $tenantId : null;
    }
}
