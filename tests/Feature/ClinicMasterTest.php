<?php

namespace Tests\Feature;

use App\Enums\PayerType;
use App\Enums\RoleName;
use App\Enums\RoomType;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\Menu;
use App\Models\Payer;
use App\Models\Procedure;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tariff;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicMasterTest extends TestCase
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

    public function test_owner_and_manager_can_crud_master_data(): void
    {
        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);

        $this->actingAs($owner)->get(route('management.clinic'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($owner)->get(route('management.services.create'))->assertOk();

        $this->actingAs($owner)->post(route('management.services.store'), [
            'name' => 'Konsultasi',
            'category' => 'konsultasi',
            'is_active' => 1,
        ])->assertRedirect(route('management.services.index'));

        $this->assertDatabaseHas('services', [
            'name' => 'Konsultasi',
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->actingAs($owner)->post(route('management.procedures.store'), [
            'code' => 'ksl-01',
            'name' => 'Konsultasi Dokter',
            'category' => 'konsultasi',
            'is_active' => 1,
        ])->assertRedirect(route('management.procedures.index'));

        $procedure = Procedure::query()->where('code', 'KSL-01')->first();
        $this->assertNotNull($procedure);

        $this->actingAs($owner)->post(route('management.payers.store'), [
            'type' => PayerType::Umum->value,
            'name' => 'Umum',
        ])->assertRedirect(route('management.payers.index'));

        $this->actingAs($owner)->post(route('management.medicines.store'), [
            'name' => 'Amoxicillin',
            'unit' => 'kapsul',
            'category' => 'antibiotik',
            'base_price' => 2500,
            'is_active' => 1,
        ])->assertRedirect(route('management.medicines.index'));

        $this->actingAs($owner)->post(route('management.rooms.store'), [
            'branch_id' => $this->branchA->id,
            'name' => 'Poli Gigi',
            'type' => RoomType::Poli->value,
            'is_active' => 1,
        ])->assertRedirect(route('management.rooms.index'));

        $this->actingAs($owner)->post(route('management.tariffs.store'), [
            'procedure_id' => $procedure->id,
            'price' => 150000,
            'effective_date' => now()->toDateString(),
        ])->assertRedirect(route('management.tariffs.index'));

        $manager = $this->tenantUser(RoleName::Manager, $this->tenantA, $this->branchA);
        $this->actingAs($manager)->get(route('management.services.index'))->assertOk();
        $this->actingAs($manager)->get(route('management.tariffs.index'))->assertOk()->assertSee('Matriks Tarif');
    }

    public function test_master_names_are_unique_per_tenant_not_globally(): void
    {
        Service::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Scaling']);
        $ownerB = $this->tenantUser(RoleName::Owner, $this->tenantB, $this->branchB);

        $this->actingAs($ownerB)->post(route('management.services.store'), [
            'name' => 'Scaling',
            'category' => 'preventif',
            'is_active' => 1,
        ])->assertRedirect(route('management.services.index'));

        $this->assertSame(2, Service::withoutGlobalScopes()->where('name', 'Scaling')->count());
    }

    public function test_owner_cannot_access_other_tenant_master_data(): void
    {
        $serviceB = Service::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Rahasia']);
        $procedureB = Procedure::factory()->create(['tenant_id' => $this->tenantB->id, 'code' => 'B-01']);
        $payerB = Payer::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Penjamin B']);
        $medicineB = Medicine::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Obat B']);
        $roomB = Room::factory()->create(['branch_id' => $this->branchB->id, 'name' => 'Poli B']);
        $tariffB = Tariff::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'procedure_id' => $procedureB->id,
        ]);

        $ownerA = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);

        $this->actingAs($ownerA)->get(route('management.services.show', $serviceB))->assertForbidden();
        $this->actingAs($ownerA)->get(route('management.procedures.edit', $procedureB))->assertForbidden();
        $this->actingAs($ownerA)->put(route('management.payers.update', $payerB), [
            'type' => PayerType::Umum->value,
            'name' => 'Hacked',
        ])->assertForbidden();
        $this->actingAs($ownerA)->delete(route('management.medicines.destroy', $medicineB))->assertForbidden();
        $this->actingAs($ownerA)->get(route('management.rooms.show', $roomB))->assertForbidden();
        $this->actingAs($ownerA)->delete(route('management.tariffs.destroy', $tariffB))->assertForbidden();
    }

    public function test_operational_roles_cannot_edit_master_data(): void
    {
        $procedure = Procedure::factory()->create(['tenant_id' => $this->tenantA->id, 'code' => 'KSL-01']);
        $payer = Payer::factory()->create(['tenant_id' => $this->tenantA->id, 'type' => PayerType::Umum]);

        $dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $this->actingAs($dentist)->get(route('management.procedures.index'))->assertOk();
        $this->actingAs($dentist)->get(route('management.procedures.create'))->assertForbidden();
        $this->actingAs($dentist)->post(route('management.procedures.store'), [
            'code' => 'HACK',
            'name' => 'Hack',
            'category' => 'konsultasi',
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($dentist)->get(route('management.procedures.show', $procedure))->assertOk();

        $registration = $this->tenantUser(RoleName::Registration, $this->tenantA, $this->branchA);
        $this->actingAs($registration)->get(route('registration.payers.general'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($registration)->get(route('management.payers.create'))->assertForbidden();
        $this->actingAs($registration)->post(route('management.payers.store'), [
            'type' => PayerType::Umum->value,
            'name' => 'Should Fail',
        ])->assertForbidden();
        $this->actingAs($registration)->get(route('management.payers.show', $payer))->assertOk();

        $pharmacy = $this->tenantUser(RoleName::Pharmacy, $this->tenantA, $this->branchA);
        $this->actingAs($pharmacy)->get(route('pharmacy.medicines.index'))->assertOk();
        $this->actingAs($pharmacy)->get(route('management.medicines.create'))->assertOk();
        $this->actingAs($pharmacy)->get(route('management.services.create'))->assertForbidden();
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
}
