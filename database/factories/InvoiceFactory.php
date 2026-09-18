<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Tenant;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'visit_id' => Visit::factory(),
            'patient_id' => Patient::factory(),
            'payer_id' => Payer::factory(),
            'number' => 'INV-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'total_amount' => '0.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::Unpaid,
        ];
    }
}
