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
use App\Support\Onboarding\DentalTariffCatalog;
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
        $serviceModels = [];
        $procedureModels = [];

        foreach (DentalTariffCatalog::items() as $item) {
            $serviceModels[$item['service']] ??= Service::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $item['service']],
                ['category' => $item['category'], 'is_active' => true]
            );

            $procedureModels[$item['code']] = Procedure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $item['code']],
                [
                    'name' => $item['procedure'],
                    'category' => $item['category'],
                    'service_id' => $serviceModels[$item['service']]->id,
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
            ['name' => 'Paracetamol 500mg', 'unit' => 'tablet', 'category' => 'analgesik_antipiretik', 'base_price' => 1500],
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

        foreach (DentalTariffCatalog::items() as $item) {
            $procedure = $procedureModels[$item['code']];
            $components = [
                'price' => $item['price'],
                'bhp_amount' => $item['bhp_amount'],
                'doctor_fee' => $item['doctor_fee'],
                'clinic_fee' => $item['clinic_fee'],
            ];

            Tariff::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'procedure_id' => $procedure->id,
                    'branch_id' => null,
                    'payer_id' => null,
                    'effective_date' => now()->toDateString(),
                ],
                $components
            );

            Tariff::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'procedure_id' => $procedure->id,
                    'branch_id' => null,
                    'payer_id' => $payerModels['BPJS Kesehatan']->id,
                    'effective_date' => now()->toDateString(),
                ],
                [
                    'price' => round($item['price'] * 0.8, 2),
                    'bhp_amount' => round($item['bhp_amount'] * 0.8, 2),
                    'doctor_fee' => round($item['doctor_fee'] * 0.8, 2),
                    'clinic_fee' => round($item['clinic_fee'] * 0.8, 2),
                ]
            );
        }
    }
}
