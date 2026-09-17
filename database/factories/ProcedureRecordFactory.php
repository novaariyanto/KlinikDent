<?php

namespace Database\Factories;

use App\Enums\BillingStatus;
use App\Models\Procedure;
use App\Models\ProcedureRecord;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedureRecord>
 */
class ProcedureRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'procedure_id' => Procedure::factory(),
            'tooth_number' => '16',
            'quantity' => 1,
            'price_at_time' => '150000.00',
            'billing_status' => BillingStatus::Unbilled,
        ];
    }
}
