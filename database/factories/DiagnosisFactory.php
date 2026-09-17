<?php

namespace Database\Factories;

use App\Models\Diagnosis;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Diagnosis>
 */
class DiagnosisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'tooth_number' => '16',
            'code' => 'K02.1',
            'description' => 'Karies dentin',
        ];
    }
}
