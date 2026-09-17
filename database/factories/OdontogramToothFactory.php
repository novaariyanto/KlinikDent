<?php

namespace Database\Factories;

use App\Enums\ToothStatus;
use App\Models\OdontogramTooth;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OdontogramTooth>
 */
class OdontogramToothFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'tooth_number' => (string) fake()->randomElement([11, 16, 21, 26, 36, 46]),
            'status' => fake()->randomElement(ToothStatus::cases()),
            'notes' => null,
        ];
    }
}
