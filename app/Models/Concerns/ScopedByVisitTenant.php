<?php

namespace App\Models\Concerns;

use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait ScopedByVisitTenant
{
    public static function bootScopedByVisitTenant(): void
    {
        static::addGlobalScope('visit_tenant', function (Builder $builder) {
            $tenantId = current_tenant_id();

            if ($tenantId === null) {
                return;
            }

            $builder->whereHas('visit', function (Builder $query) use ($tenantId) {
                $query->withoutGlobalScopes()->where('tenant_id', $tenantId);
            });
        });
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function tenantId(): ?int
    {
        if ($this->relationLoaded('visit')) {
            return $this->visit?->tenant_id ? (int) $this->visit->tenant_id : null;
        }

        $tenantId = Visit::withoutGlobalScopes()->whereKey($this->visit_id)->value('tenant_id');

        return $tenantId !== null ? (int) $tenantId : null;
    }
}
