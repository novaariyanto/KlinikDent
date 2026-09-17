<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Menu;
use App\Support\Access\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (PermissionCatalog::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');
            $role->syncPermissions(PermissionCatalog::forRole($roleName));
        }

        Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', RoleName::values())
            ->get()
            ->each(function (Role $role) {
                $role->syncPermissions([]);
                $role->delete();
            });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Menu::clearCache();
    }
}
