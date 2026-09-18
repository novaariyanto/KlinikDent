<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            MenuSeeder::class,
        ]);

        Menu::clearCache();

        $this->tenantA = Tenant::factory()->create(['name' => 'Klinik A', 'subdomain' => 'klinik-a']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Klinik B', 'subdomain' => 'klinik-b']);
        $this->branchA = Branch::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cabang A']);
        $this->branchB = Branch::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Cabang B']);
    }

    public function test_super_admin_saas_can_manage_tenants_and_see_all_branches(): void
    {
        $saas = $this->userWithRole(RoleName::SuperAdminSaas);

        $this->actingAs($saas)
            ->get(route('saas.tenants.index'))
            ->assertOk()
            ->assertSee('Semua Klinik');

        $this->actingAs($saas)
            ->post(route('saas.tenants.store'), [
                'name' => 'Klinik Baru',
                'subdomain' => 'klinik-baru',
                'status' => TenantStatus::Trial->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tenants', ['subdomain' => 'klinik-baru']);

        $this->actingAs($saas)
            ->get(route('branches.index'))
            ->assertOk();

        $this->actingAs($saas)
            ->get(route('branches.show', $this->branchB))
            ->assertOk()
            ->assertSee('Cabang B');
    }

    public function test_owner_can_manage_own_branch_and_user(): void
    {
        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);

        $this->actingAs($owner)
            ->get(route('branches.index'))
            ->assertOk();

        $this->actingAs($owner)
            ->post(route('branches.store'), [
                'name' => 'Cabang Baru A',
                'address' => 'Jl. Baru',
                'phone' => '08123456789',
                'is_active' => 1,
            ])
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', [
            'name' => 'Cabang Baru A',
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->actingAs($owner)
            ->post(route('users.store'), [
                'name' => 'Staff A',
                'email' => 'staff.a@klinikdent.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'status' => 'active',
                'roles' => [RoleName::Registration->value],
                'branch_id' => $this->branchA->id,
            ])
            ->assertRedirect(route('users.index'));

        $staff = User::query()->where('email', 'staff.a@klinikdent.test')->first();
        $this->assertNotNull($staff);
        $this->assertSame($this->tenantA->id, $staff->tenant_id);
        $this->assertTrue($staff->hasRole(RoleName::Registration));

        $titles = $this->sidebarTitles($staff);
        $this->assertContains('Pendaftaran Baru', $titles);
        $this->assertNotContains('Farmasi', $titles);
        $this->assertNotContains('Semua Klinik', $titles);
    }

    public function test_owner_cannot_access_other_tenant_branch_or_user_by_guessing_id(): void
    {
        $ownerA = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        $userB = $this->tenantUser(RoleName::Manager, $this->tenantB, $this->branchB);

        $this->actingAs($ownerA)->get(route('branches.show', $this->branchB))->assertForbidden();
        $this->actingAs($ownerA)->get(route('branches.edit', $this->branchB))->assertForbidden();
        $this->actingAs($ownerA)->put(route('branches.update', $this->branchB), [
            'name' => 'Hacked',
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($ownerA)->delete(route('branches.destroy', $this->branchB))->assertForbidden();

        $this->actingAs($ownerA)->get(route('users.show', $userB))->assertForbidden();
        $this->actingAs($ownerA)->get(route('users.edit', $userB))->assertForbidden();
        $this->actingAs($ownerA)->put(route('users.update', $userB), [
            'name' => 'Hacked',
            'email' => $userB->email,
            'status' => 'active',
            'roles' => [RoleName::Manager->value],
        ])->assertForbidden();
        $this->actingAs($ownerA)->delete(route('users.destroy', $userB))->assertForbidden();
    }

    public function test_owner_cannot_assign_super_admin_saas_role(): void
    {
        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);

        $this->actingAs($owner)
            ->post(route('users.store'), [
                'name' => 'Fake SaaS',
                'email' => 'fake.saas@klinikdent.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'status' => 'active',
                'roles' => [RoleName::SuperAdminSaas->value],
            ])
            ->assertSessionHasErrors('roles');
    }

    public function test_manager_can_access_branches(): void
    {
        $manager = $this->tenantUser(RoleName::Manager, $this->tenantA, $this->branchA);
        $titles = $this->sidebarTitles($manager);

        $this->assertContains('Cabang', $titles);

        $this->actingAs($manager)->get(route('branches.index'))->assertOk();
        $this->actingAs($manager)->get(route('branches.create'))->assertOk();
        $this->actingAs($manager)->get(route('roles.show', Role::findByName(RoleName::Manager->value)))->assertOk();
    }

    public function test_auditor_cannot_create_branch(): void
    {
        $auditor = $this->tenantUser(RoleName::Auditor, $this->tenantA, $this->branchA);

        $this->actingAs($auditor)->get(route('branches.create'))->assertForbidden();
        $this->actingAs($auditor)->post(route('branches.store'), [
            'name' => 'Should Fail',
            'is_active' => 1,
        ])->assertForbidden();
    }

    public function test_tenant_roles_cannot_access_saas_tenant_routes(): void
    {
        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);

        $this->actingAs($owner)->get(route('saas.tenants.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('saas.tenants.show', $this->tenantB))->assertForbidden();
    }

    protected function tenantUser(RoleName $role, Tenant $tenant, ?Branch $branch = null): User
    {
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch?->id,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    protected function userWithRole(RoleName|string $role): User
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'branch_id' => null,
        ]);
        $user->assignRole($role instanceof RoleName ? $role->value : $role);

        return $user;
    }

    /**
     * @return list<string>
     */
    protected function sidebarTitles(User $user): array
    {
        $titles = [];

        foreach (Menu::sidebarFor($user) as $menu) {
            $titles[] = $menu->title;
            foreach ($menu->children as $child) {
                $titles[] = $child->title;
                foreach ($child->children as $grandchild) {
                    $titles[] = $grandchild->title;
                }
            }
        }

        return $titles;
    }
}
