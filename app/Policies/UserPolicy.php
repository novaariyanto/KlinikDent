<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        if (! $user->can('users.view')) {
            return false;
        }

        return $this->sameTenant($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        if (! $user->can('users.edit')) {
            return false;
        }

        return $this->sameTenant($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        if (! $user->can('users.delete')) {
            return false;
        }

        return $this->sameTenant($user, $model);
    }

    public function resetPassword(User $user, User $model): bool
    {
        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        if (! $user->can('users.edit')) {
            return false;
        }

        return $this->sameTenant($user, $model);
    }

    public function toggleStatus(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        if (! $user->can('users.edit')) {
            return false;
        }

        return $this->sameTenant($user, $model);
    }

    public function impersonate(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if (is_impersonating()) {
            return false;
        }

        if ($model->isSuperAdmin()) {
            return false;
        }

        if (! $model->isActive()) {
            return false;
        }

        if (! $user->can('users.impersonate')) {
            return false;
        }

        return $this->sameTenant($user, $model);
    }

    public function assignRole(User $user, string $role): bool
    {
        if ($role === RoleName::SuperAdminSaas->value) {
            return $user->isPlatformAdmin();
        }

        return true;
    }

    protected function sameTenant(User $actor, User $model): bool
    {
        if ($actor->isPlatformAdmin()) {
            return true;
        }

        return $actor->tenant_id !== null
            && $model->tenant_id !== null
            && (int) $actor->tenant_id === (int) $model->tenant_id;
    }
}
