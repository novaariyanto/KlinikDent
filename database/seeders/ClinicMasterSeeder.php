<?php

namespace Database\Seeders;

use App\Enums\PayerType;
use App\Enums\RoomType;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\Payer;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tariff;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ClinicMasterSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) {
            $this->seedForTenant($tenant);
        });
    }

    protected function seedForTenant(Tenant $tenant): void
    {
        $services = [
            ['name' => 'Konsultasi', 'category' => 'konsultasi'],
            ['name' => 'Perawatan Preventif', 'category' => 'preventif'],
            ['name' => 'Bedah Mulut', 'category' => 'bedah'],
        ];

        $serviceModels = [];

        foreach ($services as $service) {
            $serviceModels[$service['name']] = Service::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $service['name']],
                ['category' => $service['category'], 'is_active' => true]
            );
        }

        $procedures = [
            ['code' => 'KSL-01', 'name' => 'Konsultasi Dokter Gigi', 'category' => 'konsultasi', 'service' => 'Konsultasi', 'price' => 150000],
            ['code' => 'SCL-01', 'name' => 'Scaling', 'category' => 'preventif', 'service' => 'Perawatan Preventif', 'price' => 350000],
            ['code' => 'CBT-01', 'name' => 'Cabut Gigi', 'category' => 'bedah', 'service' => 'Bedah Mulut', 'price' => 500000],
        ];

        $procedureModels = [];

        foreach ($procedures as $procedure) {
            $procedureModels[$procedure['code']] = Procedure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $procedure['code']],
                [
                    'name' => $procedure['name'],
                    'category' => $procedure['category'],
                    'service_id' => $serviceModels[$procedure['service']]->id,
                    'is_active' => true,
                ]
            );
        }

        $payers = [
            ['type' => PayerType::Umum, 'name' => 'Umum'],
            ['type' => PayerType::Bpjs, 'name' => 'BPJS Kesehatan', 'contract_number' => 'BPJS-001'],
            ['type' => PayerType::Asuransi, 'name' => 'Asuransi Swasta'],
        ];

        $payerModels = [];

        foreach ($payers as $payer) {
            $payerModels[$payer['name']] = Payer::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $payer['name']],
                [
                    'type' => $payer['type'],
                    'contract_number' => $payer['contract_number'] ?? null,
                ]
            );
        }

        foreach ([
            ['name' => 'Amoxicillin 500mg', 'unit' => 'kapsul', 'category' => 'antibiotik', 'base_price' => 2500],
            ['name' => 'Paracetamol 500mg', 'unit' => 'tablet', 'category' => 'analgesik', 'base_price' => 1500],
        ] as $medicine) {
            Medicine::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $medicine['name']],
                [
                    'unit' => $medicine['unit'],
                    'category' => $medicine['category'],
                    'base_price' => $medicine['base_price'],
                    'is_active' => true,
                ]
            );
        }

        $branches = Branch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();

        foreach ($branches as $branch) {
            foreach ([
                ['name' => 'Poli Umum', 'type' => RoomType::Poli],
                ['name' => 'Poli Gigi 1', 'type' => RoomType::Poli],
                ['name' => 'Poli Gigi 2', 'type' => RoomType::Poli],
                ['name' => 'Ruang Tindakan', 'type' => RoomType::Tindakan],
                ['name' => 'Ruang Tunggu', 'type' => RoomType::Tunggu],
            ] as $room) {
                Room::withoutGlobalScopes()->updateOrCreate(
                    ['branch_id' => $branch->id, 'name' => $room['name']],
                    ['type' => $room['type'], 'is_active' => true]
                );
            }
        }

        foreach ($procedureModels as $procedure) {
            $price = collect($procedures)->firstWhere('code', $procedure->code)['price'] ?? 100000;

            Tariff::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'procedure_id' => $procedure->id,
                    'branch_id' => null,
                    'payer_id' => null,
                    'effective_date' => now()->toDateString(),
                ],
                ['price' => $price]
            );

            Tariff::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'procedure_id' => $procedure->id,
                    'branch_id' => null,
                    'payer_id' => $payerModels['BPJS Kesehatan']->id,
                    'effective_date' => now()->toDateString(),
                ],
                ['price' => round($price * 0.8, 2)]
            );
        }
    }
}
