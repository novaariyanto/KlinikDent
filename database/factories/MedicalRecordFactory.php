<?php

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecord>
 */
class MedicalRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'chief_complaint' => fake()->sentence(),
            'anamnesis' => fake()->paragraph(),
            'clinical_notes' => fake()->sentence(),
            'initial_examination' => fake()->sentence(),
            'vital_signs' => [
                'blood_pressure' => '120/80',
                'pulse' => '78',
                'temperature' => '36.5',
                'respiration' => '18',
            ],
            'care_notes' => fake()->sentence(),
        ];
    }
}
