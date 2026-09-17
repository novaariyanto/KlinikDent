<?php

namespace Database\Factories;

use App\Enums\QueueStatus;
use App\Models\Queue;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Queue>
 */
class QueueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'queue_number' => fake()->numberBetween(1, 99),
            'called_at' => null,
            'status' => QueueStatus::Waiting,
        ];
    }
}
