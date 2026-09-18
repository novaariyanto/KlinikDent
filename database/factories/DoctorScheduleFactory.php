<?php

namespace Database\Factories;

use App\Enums\Weekday;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorSchedule>
 */
class DoctorScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doctor_id' => Doctor::factory(),
            'branch_id' => Branch::factory(),
            'room_id' => null,
            'weekday' => fake()->randomElement(Weekday::cases()),
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DoctorSchedule $schedule) {
            if ($schedule->tenant_id) {
                return;
            }

            $schedule->tenant_id = $schedule->doctor?->tenant_id
                ?: Doctor::withoutGlobalScopes()->whereKey($schedule->doctor_id)->value('tenant_id');
        });
    }
}
