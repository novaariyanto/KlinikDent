<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'category_id' => ExpenseCategory::factory(),
            'cash_account_id' => CashAccount::factory(),
            'amount' => '50000.00',
            'description' => fake()->sentence(3),
            'expense_date' => now()->toDateString(),
        ];
    }
}
