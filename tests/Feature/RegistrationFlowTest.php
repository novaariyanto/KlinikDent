<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\QueueStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Queue;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Payer $payerA;

    protected Payer $payerB;

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
        $this->payerA = Payer::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Umum A']);
        $this->payerB = Payer::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Umum B']);
    }

    public function test_registration_can_create_patient_visit_and_queue(): void
    {
        $staff = $this->tenantUser(RoleName::Registration, $this->tenantA, $this->branchA);
        $dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);

        $this->actingAs($staff)->get(route('registration.new'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertSee('Kunjungan Hari Ini');

        $this->actingAs($staff)->post(route('registration.visits.store'), [
            'branch_id' => $this->branchA->id,
            'payer_id' => $this->payerA->id,
            'doctor_id' => $dentist->id,
            'name' => 'Siti Rahma',
            'gender' => Gender::Female->value,
            'phone' => '081234567890',
        ])->assertRedirect();

        $patient = Patient::query()->where('name', 'Siti Rahma')->first();
        $this->assertNotNull($patient);
        $this->assertSame($this->tenantA->id, $patient->tenant_id);
        $this->assertMatchesRegularExpression('/^KLINIK-A-\d{2}-\d{4}-\d{6}$/', $patient->medical_record_number);

        $visit = Visit::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame(1, $visit->queue?->queue_number);

        $this->actingAs($staff)->get(route('queue.today'))->assertOk();
        $this->actingAs($dentist)->get(route('queue.mine'))->assertOk();
        $this->actingAs($dentist)->post(route('queue.call', $visit->queue))->assertRedirect();
        $this->assertSame(QueueStatus::Called, $visit->queue->fresh()->status);

        $this->get(route('queue.monitor.public', $this->branchA->queue_monitor_token))
            ->assertOk()
            ->assertSee('001');
    }

    public function test_queue_numbers_are_concurrency_safe_per_branch_and_day(): void
    {
        $staff = $this->tenantUser(RoleName::Registration, $this->tenantA, $this->branchA);
        $staffB = $this->tenantUser(RoleName::Registration, $this->tenantB, $this->branchB);
        $secondBranch = Branch::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cabang A2']);

        foreach (range(1, 8) as $i) {
            $this->actingAs($staff)->post(route('registration.visits.store'), [
                'branch_id' => $this->branchA->id,
                'payer_id' => $this->payerA->id,
                'name' => 'Pasien A '.$i,
            ])->assertRedirect();
        }

        $numbers = Queue::query()
            ->whereHas('visit', fn ($query) => $query->where('branch_id', $this->branchA->id))
            ->orderBy('queue_number')
            ->pluck('queue_number')
            ->all();

        $this->assertSame(range(1, 8), $numbers);

        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        $this->actingAs($owner)->post(route('registration.visits.store'), [
            'branch_id' => $secondBranch->id,
            'payer_id' => $this->payerA->id,
            'name' => 'Pasien Cabang 2',
        ])->assertRedirect();

        $otherBranchNumber = Queue::query()
            ->whereHas('visit', fn ($query) => $query->where('branch_id', $secondBranch->id))
            ->value('queue_number');

        $this->assertSame(1, $otherBranchNumber);

        $this->actingAs($staffB)->post(route('registration.visits.store'), [
            'branch_id' => $this->branchB->id,
            'payer_id' => $this->payerB->id,
            'name' => 'Pasien B',
        ])->assertRedirect();

        $tenantBNumber = Queue::query()
            ->whereHas('visit', fn ($query) => $query->where('branch_id', $this->branchB->id))
            ->value('queue_number');

        $this->assertSame(1, $tenantBNumber);

        $recordNumbers = Patient::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)
            ->pluck('medical_record_number');

        $this->assertCount(9, $recordNumbers);
        $this->assertCount(9, $recordNumbers->unique());
    }

    public function test_owner_cannot_access_other_tenant_patient_or_visit(): void
    {
        $ownerA = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        $ownerB = $this->tenantUser(RoleName::Owner, $this->tenantB, $this->branchB);

        $this->actingAs($ownerB)->post(route('registration.visits.store'), [
            'branch_id' => $this->branchB->id,
            'payer_id' => $this->payerB->id,
            'name' => 'Rahasia B',
        ])->assertRedirect();

        $patientB = Patient::withoutGlobalScopes()->where('name', 'Rahasia B')->first();
        $visitB = Visit::withoutGlobalScopes()->where('patient_id', $patientB->id)->first();
        $queueB = Queue::withoutGlobalScopes()->where('visit_id', $visitB->id)->first();

        $this->actingAs($ownerA)->get(route('registration.patients.show', $patientB))->assertForbidden();
        $this->actingAs($ownerA)->get(route('registration.visits.show', $visitB))->assertForbidden();
        $this->actingAs($ownerA)->post(route('queue.call', $queueB))->assertForbidden();
    }

    public function test_operational_roles_follow_registration_permissions(): void
    {
        $auditor = $this->tenantUser(RoleName::Auditor, $this->tenantA, $this->branchA);
        $this->actingAs($auditor)->get(route('registration.patients'))->assertOk();
        $this->actingAs($auditor)->get(route('registration.new'))->assertForbidden();
        $this->actingAs($auditor)->post(route('registration.visits.store'), [
            'branch_id' => $this->branchA->id,
            'payer_id' => $this->payerA->id,
            'name' => 'Should Fail',
        ])->assertForbidden();

        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $this->actingAs($cashier)->get(route('registration.patients'))->assertOk();
        $this->actingAs($cashier)->get(route('registration.patients.create'))->assertForbidden();
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
