<?php

namespace Database\Factories;

use App\Models\Medicine;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicine>
 */
class MedicineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(2, true),
            'unit' => fake()->randomKey(Medicine::UNITS),
            'category' => fake()->randomKey(Medicine::CATEGORIES),
            'base_price' => fake()->randomFloat(2, 1000, 150000),
            'is_active' => true,
        ];
    }
}
