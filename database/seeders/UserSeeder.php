<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles(['Super Admin']);

        $demoAdmin = User::query()->updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff Admin',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
        $demoAdmin->syncRoles(['Admin']);

        $demoUser = User::query()->updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
        $demoUser->syncRoles(['User']);

        if (User::query()->count() < 20) {
            User::factory()
                ->count(15)
                ->create()
                ->each(fn (User $user) => $user->assignRole('User'));
        }
    }
}
