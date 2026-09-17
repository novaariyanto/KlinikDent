<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = current_tenant_id();

        if ($tenantId === null) {
            return;
        }

        $builder->whereHas('branch', function (Builder $query) use ($tenantId) {
            $query->withoutGlobalScopes()->where('tenant_id', $tenantId);
        });
    }
}
