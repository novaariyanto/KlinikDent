<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->city().' Clinic',
            'address' => fake()->address(),
            'phone' => fake()->numerify('08##########'),
            'opening_hours' => [
                'monday' => '08:00-17:00',
                'tuesday' => '08:00-17:00',
                'wednesday' => '08:00-17:00',
                'thursday' => '08:00-17:00',
                'friday' => '08:00-17:00',
                'saturday' => '08:00-13:00',
                'sunday' => '',
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
