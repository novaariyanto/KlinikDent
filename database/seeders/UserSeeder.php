<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD', 'password');
        $tenantA = Tenant::query()->where('subdomain', 'klinik-a')->first();
        $tenantB = Tenant::query()->where('subdomain', 'klinik-b')->first();
        $branchA = $tenantA
            ? Branch::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->orderBy('id')->first()
            : null;
        $branchB = $tenantB
            ? Branch::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->orderBy('id')->first()
            : null;

        $users = [
            [
                'email' => env('ADMIN_EMAIL', 'superadmin@klinikdent.test'),
                'name' => 'Super Admin SaaS',
                'role' => RoleName::SuperAdminSaas,
            ],
            [
                'email' => 'owner@klinikdent.test',
                'name' => 'Owner Klinik',
                'role' => RoleName::Owner,
            ],
            [
                'email' => 'manager@klinikdent.test',
                'name' => 'Manager Klinik',
                'role' => RoleName::Manager,
            ],
            [
                'email' => 'registration@klinikdent.test',
                'name' => 'Petugas Pendaftaran',
                'role' => RoleName::Registration,
            ],
            [
                'email' => 'dentist@klinikdent.test',
                'name' => 'Dokter Gigi',
                'role' => RoleName::Dentist,
            ],
            [
                'email' => 'assistant@klinikdent.test',
                'name' => 'Asisten Dokter',
                'role' => RoleName::DentalAssistant,
            ],
            [
                'email' => 'nurse@klinikdent.test',
                'name' => 'Perawat',
                'role' => RoleName::Nurse,
            ],
            [
                'email' => 'pharmacy@klinikdent.test',
                'name' => 'Petugas Farmasi',
                'role' => RoleName::Pharmacy,
            ],
            [
                'email' => 'cashier@klinikdent.test',
                'name' => 'Kasir',
                'role' => RoleName::Cashier,
            ],
            [
                'email' => 'finance@klinikdent.test',
                'name' => 'Keuangan',
                'role' => RoleName::Finance,
            ],
            [
                'email' => 'auditor@klinikdent.test',
                'name' => 'Auditor',
                'role' => RoleName::Auditor,
            ],
        ];

        foreach ($users as $item) {
            $isPlatform = $item['role']->isPlatform();

            $user = User::query()->updateOrCreate(
                ['email' => $item['email']],
                [
                    'name' => $item['name'],
                    'password' => $password,
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                    'tenant_id' => $isPlatform ? null : $tenantA?->id,
                    'branch_id' => $isPlatform ? null : $branchA?->id,
                ]
            );

            $user->syncRoles([$item['role']->value]);
        }

        if ($tenantB && $branchB) {
            $ownerB = User::query()->updateOrCreate(
                ['email' => 'owner.b@klinikdent.test'],
                [
                    'name' => 'Owner Klinik B',
                    'password' => $password,
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                    'tenant_id' => $tenantB->id,
                    'branch_id' => $branchB->id,
                ]
            );

            $ownerB->syncRoles([RoleName::Owner->value]);
        }
    }
}
