<?php

namespace Database\Factories;

use App\Enums\RoomType;
use App\Models\Branch;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(RoomType::cases()),
            'is_active' => true,
        ];
    }
}
