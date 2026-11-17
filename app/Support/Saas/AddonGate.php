<?php

namespace App\Support\Saas;

use App\Enums\AddonCode;
use App\Enums\AddonStatus;
use App\Models\SaasAddonSubscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

final class AddonGate
{
    public static function isActive(Tenant $tenant, AddonCode $code): bool
    {
        if (! Schema::hasTable('saas_addon_subscriptions') || ! Schema::hasTable('saas_addons')) {
            return false;
        }

        $subscription = SaasAddonSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('addon', fn ($query) => $query->where('code', $code->value))
            ->first();

        if (! $subscription || $subscription->status !== AddonStatus::Active) {
            return false;
        }

        return $subscription->ends_at === null || $subscription->ends_at->isFuture();
    }
}
