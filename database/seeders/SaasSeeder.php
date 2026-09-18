<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Enums\TenantStatus;
use App\Models\SaasPackage;
use App\Models\Tenant;
use App\Support\Saas\SubscriptionService;
use Illuminate\Database\Seeder;

class SaasSeeder extends Seeder
{
    public function run(): void
    {
        $trial = SaasPackage::query()->updateOrCreate(
            ['slug' => 'trial'],
            [
                'name' => 'Trial',
                'price' => '0.00',
                'interval' => BillingInterval::Monthly,
                'trial_days' => 14,
                'max_branches' => 1,
                'max_users' => 5,
                'is_active' => true,
            ]
        );

        $pro = SaasPackage::query()->updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'price' => '350000.00',
                'interval' => BillingInterval::Monthly,
                'trial_days' => 14,
                'max_branches' => 5,
                'max_users' => 25,
                'is_active' => true,
            ]
        );

        $subscriptions = app(SubscriptionService::class);

        Tenant::query()->each(function (Tenant $tenant) use ($subscriptions, $trial, $pro) {
            if ($tenant->subscriptions()->exists()) {
                return;
            }

            $package = $tenant->status === TenantStatus::Trial ? $trial : $pro;
            $subscriptions->start($tenant, $package, $tenant->status === TenantStatus::Trial || $package->isFree());
        });
    }
}
