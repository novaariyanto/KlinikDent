<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'supplier_id' => Supplier::factory(),
            'number' => 'PO-'.now()->format('Y').'-'.fake()->unique()->numerify('#####'),
            'status' => PurchaseOrderStatus::Draft,
            'order_date' => now()->toDateString(),
        ];
    }

    public function ordered(): static
    {
        return $this->state(fn () => ['status' => PurchaseOrderStatus::Ordered]);
    }

    public function received(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Received,
            'received_at' => now(),
        ]);
    }
}
