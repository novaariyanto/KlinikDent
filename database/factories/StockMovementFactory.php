<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\MedicineStock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => MedicineStock::query()->find($attributes['medicine_stock_id'])?->tenant_id,
            'medicine_stock_id' => MedicineStock::factory(),
            'type' => StockMovementType::In,
            'quantity' => 10,
            'notes' => null,
        ];
    }
}
