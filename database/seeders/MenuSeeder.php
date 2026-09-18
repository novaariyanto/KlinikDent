<?php

namespace Database\Seeders;

use App\Enums\MenuType;
use App\Enums\UserStatus;
use App\Models\Menu;
use App\Support\Access\MenuCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('menu_role')->delete();
        Menu::query()->update(['parent_id' => null]);
        Menu::query()->delete();
        Schema::enableForeignKeyConstraints();

        Menu::clearCache();

        foreach (MenuCatalog::trees() as $roleName => $items) {
            $role = Role::findByName($roleName, 'web');
            $scope = MenuCatalog::scopeFor($roleName);

            foreach ($items as $index => $item) {
                $this->seedItem($role, $scope->value, null, $item, $index + 1);
            }
        }

        Menu::clearCache();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function seedItem(Role $role, string $scope, ?Menu $parent, array $item, int $sortOrder): Menu
    {
        $isHeading = ($item['type'] ?? null) === MenuType::Heading->value;
        $isGroup = ($item['children'] ?? []) !== [] && empty($item['route']);

        $menu = Menu::query()->updateOrCreate(
            ['name' => $item['name']],
            [
                'parent_id' => $parent?->id,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'scope' => $scope,
                'tenant_id' => null,
                'icon' => $item['icon'] ?? null,
                'type' => $isHeading ? MenuType::Heading : MenuType::Item,
                'route_name' => ($isGroup || $isHeading) ? null : ($item['route'] ?? null),
                'url' => null,
                'permission' => $item['permission'] ?: null,
                'sort_order' => $sortOrder,
                'status' => UserStatus::Active,
            ]
        );

        $menu->roles()->syncWithoutDetaching([$role->id]);

        foreach ($item['children'] ?? [] as $childIndex => $child) {
            $this->seedItem($role, $scope, $menu, $child, $childIndex + 1);
        }

        return $menu;
    }
}
