<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesTenantResource
{
    abstract protected function viewPermission(): string;

    abstract protected function managePermission(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->viewPermission());
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->viewPermission())
            && $user->belongsToTenantId($this->tenantIdOf($model))
            && $this->canAccessModelBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can($this->managePermission());
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->managePermission())
            && $user->belongsToTenantId($this->tenantIdOf($model))
            && $this->canAccessModelBranch($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }

    protected function tenantIdOf(Model $model): ?int
    {
        $tenantId = $model->getAttribute('tenant_id');

        return $tenantId !== null ? (int) $tenantId : null;
    }

    protected function canAccessModelBranch(User $user, Model $model): bool
    {
        $branchId = $model->getAttribute('branch_id');

        if ($branchId === null) {
            return true;
        }

        return $user->canAccessBranch((int) $branchId);
    }
}
