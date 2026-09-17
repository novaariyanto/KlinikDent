<?php

namespace Database\Seeders;

use App\Enums\MenuType;
use App\Enums\UserStatus;
use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Menu::clearCache();

        $this->upsert(null, [
            'title' => 'Dashboard',
            'icon' => 'bx bx-home-circle',
            'type' => MenuType::Item,
            'route_name' => 'dashboard',
            'permission' => 'dashboard.view',
            'sort_order' => 1,
        ]);

        $management = $this->upsert(null, [
            'title' => 'Management',
            'icon' => null,
            'type' => MenuType::Heading,
            'route_name' => null,
            'permission' => null,
            'sort_order' => 10,
        ]);

        $this->upsert($management, [
            'title' => 'Users',
            'icon' => 'bx bx-user',
            'type' => MenuType::Item,
            'route_name' => 'users.index',
            'permission' => 'users.view',
            'sort_order' => 11,
        ]);

        $this->upsert($management, [
            'title' => 'Roles & Permissions',
            'icon' => 'bx bx-shield-quarter',
            'type' => MenuType::Item,
            'route_name' => 'roles.index',
            'permission' => 'roles.view',
            'sort_order' => 12,
        ]);

        $this->upsert($management, [
            'title' => 'Menus',
            'icon' => 'bx bx-menu',
            'type' => MenuType::Item,
            'route_name' => 'menus.index',
            'permission' => 'menus.view',
            'sort_order' => 13,
        ]);

        $system = $this->upsert(null, [
            'title' => 'System',
            'icon' => null,
            'type' => MenuType::Heading,
            'route_name' => null,
            'permission' => null,
            'sort_order' => 20,
        ]);

        $this->upsert($system, [
            'title' => 'Settings',
            'icon' => 'bx bx-cog',
            'type' => MenuType::Item,
            'route_name' => 'settings.index',
            'permission' => 'settings.update',
            'sort_order' => 21,
        ]);

        $this->upsert($system, [
            'title' => 'Activity Logs',
            'icon' => 'bx bx-history',
            'type' => MenuType::Item,
            'route_name' => 'logs.index',
            'permission' => 'logs.view',
            'sort_order' => 22,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function upsert(?Menu $parent, array $attributes): Menu
    {
        return Menu::query()->updateOrCreate(
            [
                'title' => $attributes['title'],
                'parent_id' => $parent?->id,
            ],
            [
                'icon' => $attributes['icon'],
                'type' => $attributes['type'],
                'route_name' => $attributes['route_name'],
                'url' => null,
                'permission' => $attributes['permission'],
                'sort_order' => $attributes['sort_order'],
                'status' => UserStatus::Active,
            ]
        );
    }
}
