<?php

namespace Database\Factories;

use App\Enums\CashAccountType;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashAccount>
 */
class CashAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'name' => 'Kas Tunai',
            'type' => CashAccountType::Cash,
            'balance' => '0.00',
            'is_default' => true,
        ];
    }

    public function bank(): static
    {
        return $this->state(fn () => [
            'name' => 'Bank Utama',
            'type' => CashAccountType::Bank,
        ]);
    }
}
