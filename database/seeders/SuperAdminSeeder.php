<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);

        $this->command?->info('Demo users seeded for role: '.RoleName::SuperAdminSaas->value);
    }
}
