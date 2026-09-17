<?php

namespace Database\Factories;

use App\Enums\VisitStatus;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Tenant;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'patient_id' => Patient::factory(),
            'doctor_id' => null,
            'room_id' => null,
            'payer_id' => Payer::factory(),
            'status' => VisitStatus::Waiting,
            'visit_date' => now()->toDateString(),
        ];
    }
}
