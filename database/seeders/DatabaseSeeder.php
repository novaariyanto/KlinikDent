<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Laravolt\Indonesia\Seeds\CitiesSeeder;
use Laravolt\Indonesia\Seeds\DistrictsSeeder;
use Laravolt\Indonesia\Seeds\ProvincesSeeder;
use Laravolt\Indonesia\Seeds\VillagesSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProvincesSeeder::class,
            CitiesSeeder::class,
            DistrictsSeeder::class,
            VillagesSeeder::class,
            RolePermissionSeeder::class,
            TenantSeeder::class,
            UserSeeder::class,
            MenuSeeder::class,
            ClinicMasterSeeder::class,
            DoctorSeeder::class,
            RegistrationSeeder::class,
            ClinicalCareSeeder::class,
            PharmacySeeder::class,
            BillingSeeder::class,
            WhatsAppTemplateSeeder::class,
            FinanceSeeder::class,
            SaasSeeder::class,
            SaasAddonSeeder::class,
        ]);

        Setting::setValue('app_name', config('app.name'));
        Setting::setValue('timezone', config('app.timezone'));
        Setting::setValue('records_per_page', '10');
    }
}
