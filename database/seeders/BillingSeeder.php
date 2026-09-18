<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use App\Support\Billing\BillingService;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $billing = app(BillingService::class);

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($billing) {
            $visit = Visit::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->orderBy('id')
                ->first();

            if (! $visit) {
                return;
            }

            if (Invoice::withoutGlobalScopes()->where('visit_id', $visit->id)->exists()) {
                return;
            }

            $actor = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->orderBy('id')
                ->first();

            $billing->generateForVisit($visit, $actor);
        });
    }
}
