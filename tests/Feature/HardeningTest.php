<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Branch $branchA;

    protected Branch $branchA2;

    protected Branch $branchB;

    protected Payer $payerA;

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
        $this->branchA = Branch::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cabang A1']);
        $this->branchA2 = Branch::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cabang A2']);
        $this->branchB = Branch::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Cabang B']);
        $this->payerA = Payer::factory()->create(['tenant_id' => $this->tenantA->id]);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                'email' => 'nobody@klinikdent.test',
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => 'nobody@klinikdent.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_route_permission_blocks_unrelated_modules(): void
    {
        $staff = $this->tenantUser(RoleName::Registration, $this->tenantA, $this->branchA);

        $this->actingAs($staff)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('billing.invoices'))->assertForbidden();
    }

    public function test_staff_with_two_branches_sees_both_but_not_other_tenant(): void
    {
        $staff = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $staff->syncAssignedBranches([$this->branchA->id, $this->branchA2->id]);

        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);

        $patientA = Patient::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pasien Cabang Satu',
        ]);
        $patientA2 = Patient::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pasien Cabang Dua',
        ]);
        $patientB = Patient::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Pasien Klinik B',
        ]);

        Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $patientA->id,
            'payer_id' => $this->payerA->id,
            'status' => VisitStatus::Waiting,
            'visit_date' => now()->toDateString(),
        ]);
        Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA2->id,
            'patient_id' => $patientA2->id,
            'payer_id' => $this->payerA->id,
            'status' => VisitStatus::Waiting,
            'visit_date' => now()->toDateString(),
        ]);
        Visit::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->branchB->id,
            'patient_id' => $patientB->id,
            'payer_id' => Payer::factory()->create(['tenant_id' => $this->tenantB->id])->id,
            'status' => VisitStatus::Waiting,
            'visit_date' => now()->toDateString(),
        ]);

        $this->actingAs($staff->fresh())->get(route('reports.visits'))
            ->assertOk()
            ->assertSee('Pasien Cabang Satu')
            ->assertSee('Pasien Cabang Dua')
            ->assertDontSee('Pasien Klinik B');

        $this->actingAs($owner)->get(route('reports.visits'))
            ->assertOk()
            ->assertSee('Pasien Cabang Satu')
            ->assertSee('Pasien Cabang Dua');
    }

    public function test_patient_update_stores_before_after_audit(): void
    {
        $staff = $this->tenantUser(RoleName::Registration, $this->tenantA, $this->branchA);
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Nama Lama',
        ]);

        $this->actingAs($staff)->put(route('registration.patients.update', $patient), [
            'name' => 'Nama Baru',
            'nik' => $patient->nik,
            'phone' => $patient->phone,
            'address' => $patient->address,
            'dob' => $patient->dob?->toDateString(),
            'gender' => $patient->gender?->value,
            'default_payer_id' => $patient->default_payer_id,
        ])->assertRedirect();

        $log = ActivityLog::query()->where('event', 'updated')->where('module', 'patients')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame('Nama Lama', $log->properties['before']['name'] ?? null);
        $this->assertSame('Nama Baru', $log->properties['after']['name'] ?? null);
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
