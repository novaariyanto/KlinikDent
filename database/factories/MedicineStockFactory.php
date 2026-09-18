<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicineStock>
 */
class MedicineStockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'medicine_id' => Medicine::factory(),
            'batch_number' => strtoupper(fake()->bothify('BTH-####')),
            'expired_date' => now()->addMonths(6)->toDateString(),
            'quantity' => 100,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expired_date' => now()->subDays(5)->toDateString(),
            'quantity' => 10,
        ]);
    }
}
