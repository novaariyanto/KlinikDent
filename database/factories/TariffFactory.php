<?php

namespace Database\Factories;

use App\Models\Procedure;
use App\Models\Tariff;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tariff>
 */
class TariffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => null,
            'procedure_id' => Procedure::factory(),
            'payer_id' => null,
            'price' => fake()->randomFloat(2, 50000, 1500000),
            'effective_date' => now()->toDateString(),
        ];
    }
}
