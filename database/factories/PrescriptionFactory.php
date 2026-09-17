<?php

namespace Database\Factories;

use App\Enums\PrescriptionStatus;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'doctor_id' => User::factory(),
            'status' => PrescriptionStatus::Draft,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => PrescriptionStatus::Sent]);
    }
}
