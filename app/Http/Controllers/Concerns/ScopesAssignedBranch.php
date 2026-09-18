<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait ScopesAssignedBranch
{
    protected function restrictedBranchId(?User $user): ?int
    {
        if (! $user || $user->can('branch.manage')) {
            return null;
        }

        $ids = $user->assignedBranchIds();

        return $ids[0] ?? ($user->branch_id ? (int) $user->branch_id : null);
    }

    protected function selectedBranchId(?User $user, ?int $requested): ?int
    {
        if (! $user) {
            return $requested;
        }

        $ids = $user->restrictedBranchIds();

        if ($ids === null) {
            return $requested;
        }

        if ($requested && in_array($requested, $ids, true)) {
            return $requested;
        }

        return count($ids) === 1 ? $ids[0] : null;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    protected function constrainBranch(Builder $query, ?User $user, string $column = 'branch_id', ?int $preferred = null): Builder
    {
        if ($user) {
            $user->applyBranchLimit($query, $column, $preferred);
        }

        return $query;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    protected function constrainRelatedBranch(
        Builder $query,
        ?User $user,
        string $relation = 'visit',
        string $column = 'branch_id',
        ?int $preferred = null,
    ): Builder {
        if (! $user) {
            return $query;
        }

        $ids = $user->restrictedBranchIds();

        if ($ids === null) {
            return $preferred ? $query->whereHas($relation, fn ($related) => $related->where($column, $preferred)) : $query;
        }

        $allowed = $preferred && in_array($preferred, $ids, true) ? [$preferred] : $ids;

        return $query->whereHas($relation, fn ($related) => $related->whereIn($column, $allowed));
    }

    /**
     * @return Collection<int, Branch>
     */
    protected function assignedBranches(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        if ($user->can('branch.manage')) {
            return Branch::query()->orderBy('name')->get();
        }

        $ids = $user->assignedBranchIds();

        if ($ids === []) {
            return collect();
        }

        return Branch::query()->whereIn('id', $ids)->orderBy('name')->get();
    }
}
