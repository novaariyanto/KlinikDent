<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'medical_record_number' => strtoupper(fake()->unique()->bothify('KLINIK-##-2026-######')),
            'name' => fake()->name(),
            'nik' => fake()->unique()->numerify('################'),
            'dob' => fake()->dateTimeBetween('-70 years', '-5 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'default_payer_id' => null,
        ];
    }
}
