<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Menu;
use App\Models\User;
use App\Support\Access\MenuCatalog;
use App\Support\Access\PermissionCatalog;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            MenuSeeder::class,
        ]);

        Menu::clearCache();
    }

    public function test_super_admin_saas_can_access_saas_menu_and_pages(): void
    {
        $user = $this->userWithRole(RoleName::SuperAdminSaas);
        $titles = $this->sidebarTitles($user);

        $this->assertContains('Dashboard SaaS', $titles);
        $this->assertContains('Semua Klinik', $titles);
        $this->assertContains('Platform Settings', $titles);
        $this->assertNotContains('Dashboard', $titles);
        $this->assertNotContains('Pendaftaran Baru', $titles);
        $this->assertNotContains('Farmasi', $titles);

        $this->actingAs($user)
            ->get(route('saas.tenants.index'))
            ->assertOk()
            ->assertSee('Semua Klinik')
            ->assertDontSee('Coming Soon');
    }

    public function test_owner_can_access_tenant_menu_but_not_saas_routes(): void
    {
        $user = $this->userWithRole(RoleName::Owner);
        $titles = $this->sidebarTitles($user);

        $this->assertContains('Dashboard', $titles);
        $this->assertContains('Pendaftaran', $titles);
        $this->assertContains('Pengaturan Klinik', $titles);
        $this->assertNotContains('Semua Klinik', $titles);
        $this->assertNotContains('Platform Settings', $titles);

        $this->actingAs($user)->get(route('registration.index'))->assertOk();
        $this->actingAs($user)->get(route('saas.tenants.index'))->assertForbidden();
    }

    public function test_registration_only_sees_allowed_menus(): void
    {
        $user = $this->userWithRole(RoleName::Registration);
        $titles = $this->sidebarTitles($user);

        foreach (['Dashboard Pendaftaran', 'Pendaftaran', 'Pendaftaran Baru', 'Antrean', 'Jadwal Dokter', 'Penjamin'] as $title) {
            $this->assertContains($title, $titles);
        }

        foreach (['Farmasi', 'Billing', 'Keuangan', 'Semua Klinik', 'Pelayanan'] as $title) {
            $this->assertNotContains($title, $titles);
        }

        $this->actingAs($user)->get(route('registration.patients'))->assertOk();
        $this->actingAs($user)->get(route('pharmacy.medicines.index'))->assertForbidden();
    }

    public function test_dentist_only_sees_allowed_menus(): void
    {
        $user = $this->userWithRole(RoleName::Dentist);
        $titles = $this->sidebarTitles($user);

        foreach (['Dashboard', 'Dashboard Dokter', 'Pelayanan', 'Pemeriksaan', 'Riwayat Pasien', 'Lainnya', 'Jadwal Saya', 'Laporan Pribadi'] as $title) {
            $this->assertContains($title, $titles);
        }

        foreach (['Antrean Saya', 'Odontogram', 'Diagnosis', 'Tindakan', 'Resep', 'Rujukan', 'Rekam Medis', 'Pendaftaran Baru'] as $title) {
            $this->assertNotContains($title, $titles);
        }

        $this->actingAs($user)->get(route('examinations.index'))->assertOk();
        $this->actingAs($user)->get(route('billing.payments'))->assertForbidden();
    }

    public function test_pharmacy_only_sees_pharmacy_menus(): void
    {
        $user = $this->userWithRole(RoleName::Pharmacy);
        $titles = $this->sidebarTitles($user);

        foreach (['Dashboard Farmasi', 'Resep Masuk', 'Daftar Obat', 'Purchase Order'] as $title) {
            $this->assertContains($title, $titles);
        }

        $this->assertNotContains('Pendaftaran Baru', $titles);
        $this->assertNotContains('Buka Shift', $titles);

        $this->actingAs($user)->get(route('pharmacy.medicines.index'))->assertOk();
        $this->actingAs($user)->get(route('registration.new'))->assertForbidden();
    }

    public function test_cashier_only_sees_cashier_menus(): void
    {
        $user = $this->userWithRole(RoleName::Cashier);
        $titles = $this->sidebarTitles($user);

        foreach (['Dashboard Kasir', 'Tagihan Hari Ini', 'Buka Shift', 'Laporan Kasir'] as $title) {
            $this->assertContains($title, $titles);
        }

        $this->assertNotContains('Resep Masuk', $titles);
        $this->assertNotContains('Odontogram', $titles);

        $this->actingAs($user)->get(route('billing.payments'))->assertOk();
        $this->actingAs($user)->get(route('finance.revenue.daily'))->assertForbidden();
    }

    public function test_finance_only_sees_finance_menus(): void
    {
        $user = $this->userWithRole(RoleName::Finance);
        $titles = $this->sidebarTitles($user);

        foreach (['Dashboard Keuangan', 'Pendapatan Harian', 'Hutang', 'Kas & Bank', 'Laba Rugi'] as $title) {
            $this->assertContains($title, $titles);
        }

        $this->assertNotContains('Pendaftaran Baru', $titles);
        $this->assertNotContains('Buka Shift', $titles);

        $this->actingAs($user)->get(route('finance.cash-bank'))->assertOk();
        $this->actingAs($user)->get(route('cashier.shifts.open'))->assertForbidden();
    }

    public function test_auditor_is_read_only(): void
    {
        $role = Role::findByName(RoleName::Auditor->value, 'web');

        foreach ($role->permissions->pluck('name') as $permission) {
            $this->assertFalse(
                PermissionCatalog::isWritePermission($permission),
                "Auditor should not have write permission [{$permission}]"
            );
        }

        $user = $this->userWithRole(RoleName::Auditor);

        $this->actingAs($user)->get(route('registration.history'))->assertOk();
        $this->actingAs($user)->get(route('registration.new'))->assertForbidden();
        $this->assertFalse($user->can('patient.create'));
        $this->assertFalse($user->can('billing.update'));
        $this->assertFalse($user->can('users.delete'));
    }

    public function test_user_without_permission_cannot_access_protected_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('registration.patients'))->assertForbidden();
        $this->assertSame([], $this->sidebarTitles($user));
    }

    public function test_tenant_roles_cannot_access_super_admin_saas_routes(): void
    {
        foreach (RoleName::tenantValues() as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('saas.tenants.index'))->assertForbidden();
            $this->actingAs($user)->get(route('saas.packages.index'))->assertForbidden();
            $this->assertFalse($user->can(PermissionCatalog::SAAS_ACCESS));
        }
    }

    public function test_parent_menu_is_active_when_child_is_active(): void
    {
        $user = $this->userWithRole(RoleName::Registration);

        $this->actingAs($user)
            ->get(route('registration.patients'))
            ->assertOk()
            ->assertSee('mm-active')
            ->assertSee('Pasien')
            ->assertSee('Daftar Pasien');
    }

    public function test_seeded_roles_and_placeholder_routes_exist(): void
    {
        foreach (RoleName::values() as $role) {
            $this->assertNotNull(Role::findByName($role, 'web'));
        }

        foreach (MenuCatalog::placeholderRoutes() as $route) {
            $this->assertTrue(
                Route::has($route['name']),
                "Missing route [{$route['name']}]"
            );
        }
    }

    protected function userWithRole(RoleName|string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role instanceof RoleName ? $role->value : $role);

        return $user;
    }

    /**
     * @return list<string>
     */
    protected function sidebarTitles(User $user): array
    {
        return $this->flattenTitles(Menu::sidebarFor($user));
    }

    /**
     * @param  Collection<int, Menu>|iterable<int, Menu>  $menus
     * @return list<string>
     */
    protected function flattenTitles(iterable $menus): array
    {
        $titles = [];

        foreach ($menus as $menu) {
            $titles[] = $menu->title;
            $titles = array_merge($titles, $this->flattenTitles($menu->children));
        }

        return $titles;
    }
}
