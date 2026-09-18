<?php

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Database\Seeder;

class PharmacySeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PharmacyStockService::class);

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($service) {
            $this->seedForTenant($tenant, $service);
        });
    }

    protected function seedForTenant(Tenant $tenant, PharmacyStockService $service): void
    {
        $actor = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->role(RoleName::Pharmacy->value)
            ->first()
            ?? User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->first();

        $branch = Branch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->first();

        if (! $actor || ! $branch) {
            return;
        }

        $supplier = Supplier::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'PT Kimia Farma'],
            ['contact' => '021-555-0101', 'address' => 'Jakarta']
        );

        $amoxicillin = Medicine::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Amoxicillin 500mg')
            ->first();
        $paracetamol = Medicine::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Paracetamol 500mg')
            ->first();

        if ($amoxicillin) {
            $this->seedStock($service, $tenant, $branch, $actor, $amoxicillin, 'AMOX-NEAR', now()->addDays(18)->toDateString(), 20, 'Stok awal FEFO dekat expired');
            $this->seedStock($service, $tenant, $branch, $actor, $amoxicillin, 'AMOX-FAR', now()->addMonths(8)->toDateString(), 80, 'Stok awal FEFO jauh expired');
        }

        if ($paracetamol) {
            $this->seedStock($service, $tenant, $branch, $actor, $paracetamol, 'PARA-OK', now()->addMonths(4)->toDateString(), 80, 'Stok awal paracetamol');
            $this->seedStock($service, $tenant, $branch, $actor, $paracetamol, 'PARA-EXP', now()->subDays(3)->toDateString(), 8, 'Batch kedaluwarsa (contoh laporan)');
        }

        if (! $amoxicillin || ! $paracetamol) {
            return;
        }

        $ordered = PurchaseOrder::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'PO-'.now()->format('Y').'-00001'],
            [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Ordered,
                'order_date' => now()->toDateString(),
                'created_by' => $actor->id,
            ]
        );

        if ($ordered->items()->doesntExist()) {
            $ordered->items()->create([
                'medicine_id' => $paracetamol->id,
                'quantity' => 30,
                'unit_price' => $paracetamol->base_price,
            ]);
        }
    }

    protected function seedStock(
        PharmacyStockService $service,
        Tenant $tenant,
        Branch $branch,
        User $actor,
        Medicine $medicine,
        string $batch,
        string $expired,
        int $quantity,
        string $notes,
    ): void {
        $exists = MedicineStock::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->where('medicine_id', $medicine->id)
            ->where('batch_number', $batch)
            ->exists();

        if ($exists) {
            return;
        }

        $service->addStock(
            (int) $tenant->id,
            (int) $branch->id,
            (int) $medicine->id,
            $batch,
            $expired,
            $quantity,
            $actor,
            null,
            $notes
        );
    }
}
