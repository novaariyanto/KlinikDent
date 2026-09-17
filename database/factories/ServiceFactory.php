<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(2, true),
            'category' => fake()->randomKey(Service::CATEGORIES),
            'is_active' => true,
        ];
    }
}
