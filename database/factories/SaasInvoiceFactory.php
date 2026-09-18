<?php

namespace Database\Factories;

use App\Enums\SaasInvoiceStatus;
use App\Models\SaasInvoice;
use App\Models\SaasSubscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaasInvoice>
 */
class SaasInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => SaasSubscription::factory(),
            'number' => 'SI-'.now()->format('Ym').'-'.fake()->unique()->numerify('####'),
            'amount' => '250000.00',
            'status' => SaasInvoiceStatus::Unpaid,
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
            'paid_at' => null,
        ];
    }
}
