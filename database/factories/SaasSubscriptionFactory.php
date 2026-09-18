<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\SaasPackage;
use App\Models\SaasSubscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaasSubscription>
 */
class SaasSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $starts = now()->startOfDay();

        return [
            'tenant_id' => Tenant::factory(),
            'package_id' => SaasPackage::factory(),
            'status' => SubscriptionStatus::Active,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addMonth(),
            'trial_ends_at' => null,
            'cancelled_at' => null,
        ];
    }
}
