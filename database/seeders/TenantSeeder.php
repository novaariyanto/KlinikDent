<?php

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $klinikA = Tenant::query()->updateOrCreate(
            ['subdomain' => 'klinik-a'],
            [
                'name' => 'Klinik Gigi A',
                'status' => TenantStatus::Active,
            ]
        );

        $klinikB = Tenant::query()->updateOrCreate(
            ['subdomain' => 'klinik-b'],
            [
                'name' => 'Klinik Gigi B',
                'status' => TenantStatus::Active,
            ]
        );

        Branch::query()->updateOrCreate(
            ['tenant_id' => $klinikA->id, 'name' => 'Cabang Pusat A'],
            [
                'address' => 'Jl. Kesehatan No. 1, Jakarta',
                'phone' => '02111111111',
                'opening_hours' => [
                    'monday' => '08:00-17:00',
                    'tuesday' => '08:00-17:00',
                    'wednesday' => '08:00-17:00',
                    'thursday' => '08:00-17:00',
                    'friday' => '08:00-17:00',
                    'saturday' => '08:00-13:00',
                    'sunday' => '',
                ],
                'is_active' => true,
            ]
        );

        Branch::query()->updateOrCreate(
            ['tenant_id' => $klinikB->id, 'name' => 'Cabang Pusat B'],
            [
                'address' => 'Jl. Melati No. 2, Bandung',
                'phone' => '02222222222',
                'opening_hours' => [
                    'monday' => '08:00-17:00',
                    'tuesday' => '08:00-17:00',
                    'wednesday' => '08:00-17:00',
                    'thursday' => '08:00-17:00',
                    'friday' => '08:00-17:00',
                    'saturday' => '08:00-13:00',
                    'sunday' => '',
                ],
                'is_active' => true,
            ]
        );
    }
}
