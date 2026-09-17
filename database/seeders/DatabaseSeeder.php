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
            UserSeeder::class,
            MenuSeeder::class,
        ]);

        Setting::setValue('app_name', config('app.name'));
        Setting::setValue('timezone', config('app.timezone'));
        Setting::setValue('records_per_page', '10');
    }
}
