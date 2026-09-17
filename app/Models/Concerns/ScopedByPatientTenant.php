<?php

namespace App\Models\Concerns;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait ScopedByPatientTenant
{
    public static function bootScopedByPatientTenant(): void
    {
        static::addGlobalScope('patient_tenant', function (Builder $builder) {
            $tenantId = current_tenant_id();

            if ($tenantId === null) {
                return;
            }

            $builder->whereHas('patient', function (Builder $query) use ($tenantId) {
                $query->withoutGlobalScopes()->where('tenant_id', $tenantId);
            });
        });
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function tenantId(): ?int
    {
        if ($this->relationLoaded('patient')) {
            return $this->patient?->tenant_id ? (int) $this->patient->tenant_id : null;
        }

        $tenantId = Patient::withoutGlobalScopes()->whereKey($this->patient_id)->value('tenant_id');

        return $tenantId !== null ? (int) $tenantId : null;
    }
}
