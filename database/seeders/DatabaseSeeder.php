<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            TenantSeeder::class,
            UserSeeder::class,
            MenuSeeder::class,
            ClinicMasterSeeder::class,
            RegistrationSeeder::class,
            ClinicalCareSeeder::class,
            PharmacySeeder::class,
            BillingSeeder::class,
        ]);

        Setting::setValue('app_name', config('app.name'));
        Setting::setValue('timezone', config('app.timezone'));
        Setting::setValue('records_per_page', '10');
    }
}
