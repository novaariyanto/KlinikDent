<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesPlatformResource
{
    abstract protected function viewPermission(): string;

    abstract protected function managePermission(): string;

    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin() && $user->can($this->viewPermission());
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin() && $user->can($this->managePermission());
    }

    public function update(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->create($user);
    }
}
