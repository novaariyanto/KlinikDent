<?php

namespace App\Http\Controllers\Billing\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopesBillingBranch
{
    protected function restrictedBranchId(?User $user): ?int
    {
        if (! $user || $user->can('branch.manage')) {
            return null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    protected function constrainBranch(Builder $query, ?User $user, string $column = 'branch_id'): Builder
    {
        $branchId = $this->restrictedBranchId($user);

        if ($branchId) {
            $query->where($column, $branchId);
        }

        return $query;
    }
}
