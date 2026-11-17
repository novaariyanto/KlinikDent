<?php

namespace App\Support\Pharmacy;

use App\Models\MedicineStock;
use Illuminate\Support\Collection;

class MedicineStockQuery
{
    /**
     * Total stok tersedia per medicine di cabang (batch belum kedaluwarsa).
     *
     * @param  list<int>|Collection<int, int>  $medicineIds
     * @return array<int, int> medicine_id => qty
     */
    public function availableByMedicines(int $tenantId, int $branchId, iterable $medicineIds): array
    {
        $ids = collect($medicineIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return MedicineStock::query()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->whereIn('medicine_id', $ids->all())
            ->where('quantity', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expired_date')
                    ->orWhereDate('expired_date', '>=', now()->toDateString());
            })
            ->selectRaw('medicine_id, SUM(quantity) as total_qty')
            ->groupBy('medicine_id')
            ->pluck('total_qty', 'medicine_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    public function availableFor(int $tenantId, int $branchId, int $medicineId): int
    {
        return $this->availableByMedicines($tenantId, $branchId, [$medicineId])[$medicineId] ?? 0;
    }
}
