<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'sip' => 'SIP/'.fake()->numerify('####/####'),
            'str' => 'STR/'.fake()->numerify('########'),
            'specialization' => 'Dokter Gigi Umum',
            'phone' => fake()->numerify('08##########'),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Doctor $doctor) {
            if ($doctor->tenant_id) {
                return;
            }

            $doctor->tenant_id = $doctor->user?->tenant_id
                ?: User::withoutGlobalScopes()->whereKey($doctor->user_id)->value('tenant_id');
        });
    }
}
