<?php

namespace App\Http\Requests\Concerns;

trait ResolvesActorTenant
{
    protected function actorTenantId(): ?int
    {
        $user = $this->user();

        if (! $user || $user->isPlatformAdmin()) {
            return null;
        }

        return $user->tenant_id ? (int) $user->tenant_id : null;
    }
}
