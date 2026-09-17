<?php

namespace Database\Factories;

use App\Enums\PayerType;
use App\Models\Payer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payer>
 */
class PayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => fake()->randomElement(PayerType::cases()),
            'name' => fake()->unique()->company(),
            'contract_number' => fake()->optional()->numerify('KTR-####'),
        ];
    }
}
