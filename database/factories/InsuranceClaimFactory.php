<?php

namespace Database\Factories;

use App\Enums\InsuranceClaimStatus;
use App\Models\InsuranceClaim;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InsuranceClaim>
 */
class InsuranceClaimFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'patient_id' => Patient::factory(),
            'visit_id' => null,
            'payer_id' => null,
            'status' => InsuranceClaimStatus::Draft,
            'amount' => '150000.00',
            'document_path' => null,
            'notes' => fake()->optional()->sentence(),
            'submitted_at' => null,
        ];
    }
}
