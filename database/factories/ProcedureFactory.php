<?php

namespace Database\Factories;

use App\Models\Procedure;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Procedure>
 */
class ProcedureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'service_id' => null,
            'code' => strtoupper(fake()->unique()->bothify('TDK-###')),
            'name' => fake()->words(3, true),
            'category' => fake()->randomKey(Procedure::CATEGORIES),
            'is_active' => true,
        ];
    }
}
