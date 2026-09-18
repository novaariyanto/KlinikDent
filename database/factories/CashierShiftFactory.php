<?php

namespace Database\Factories;

use App\Enums\CashShiftStatus;
use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashierShift>
 */
class CashierShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'cashier_id' => User::factory(),
            'opening_balance' => '0.00',
            'opened_at' => now(),
            'status' => CashShiftStatus::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => CashShiftStatus::Closed,
            'closed_at' => now(),
            'closing_balance' => '0.00',
            'system_balance' => '0.00',
            'variance' => '0.00',
        ]);
    }
}
