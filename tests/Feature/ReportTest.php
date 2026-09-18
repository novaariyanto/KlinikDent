<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Models\Branch;
use App\Models\Diagnosis;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Procedure;
use App\Models\ProcedureRecord;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use App\Support\Billing\BillingService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Payer $payerA;

    protected Procedure $procedureA;

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
        $this->branchA = Branch::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->branchB = Branch::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->payerA = Payer::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->procedureA = Procedure::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Scaling']);
    }

    public function test_report_pages_are_not_placeholders(): void
    {
        $manager = $this->tenantUser(RoleName::Manager, $this->tenantA, $this->branchA);
        $dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $auditor = $this->tenantUser(RoleName::Auditor, $this->tenantA, $this->branchA);
        $finance = $this->tenantUser(RoleName::Finance, $this->tenantA, $this->branchA);

        foreach ([
            route('reports.index'),
            route('reports.visits'),
            route('reports.revenue'),
            route('reports.procedures'),
            route('reports.patients'),
            route('reports.operational'),
            route('reports.medical'),
            route('reports.finance'),
            route('dashboard'),
        ] as $url) {
            $this->actingAs($manager)->get($url)->assertOk()->assertDontSee('Coming Soon');
        }

        $this->actingAs($dentist)->get(route('reports.personal'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($dentist)->get(route('dashboard'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($auditor)->get(route('reports.operational'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($finance)->get(route('finance.reports.revenue'))->assertRedirect(route('reports.revenue'));
        $this->actingAs($this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA))
            ->get(route('reports.revenue'))
            ->assertForbidden();
    }

    public function test_reports_are_isolated_per_tenant_and_dentist(): void
    {
        $dentistA = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $dentistOther = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $managerA = $this->tenantUser(RoleName::Manager, $this->tenantA, $this->branchA);
        $managerB = $this->tenantUser(RoleName::Manager, $this->tenantB, $this->branchB);

        $patientA = Patient::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pasien Rahasia A',
        ]);
        $visitA = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $patientA->id,
            'doctor_id' => $dentistA->id,
            'payer_id' => $this->payerA->id,
            'status' => VisitStatus::Done,
            'visit_date' => now()->toDateString(),
        ]);
        ProcedureRecord::factory()->create([
            'visit_id' => $visitA->id,
            'procedure_id' => $this->procedureA->id,
            'quantity' => 2,
            'price_at_time' => '150000.00',
        ]);
        Diagnosis::factory()->create([
            'visit_id' => $visitA->id,
            'code' => 'K02.1',
            'description' => 'Karies dentin',
        ]);

        $invoice = app(BillingService::class)->generateForVisit($visitA, $dentistA);
        $this->actingAs($managerA)->post(route('billing.invoices.pay', $invoice), [
            'amount' => '100000',
            'method' => PaymentMethod::Cash->value,
        ])->assertRedirect();

        $this->actingAs($managerA)->get(route('reports.visits'))
            ->assertOk()
            ->assertSee('Pasien Rahasia A');
        $this->actingAs($managerB)->get(route('reports.visits'))
            ->assertOk()
            ->assertDontSee('Pasien Rahasia A');

        $this->actingAs($dentistA)->get(route('reports.personal'))
            ->assertOk()
            ->assertSee('Scaling');
        $this->actingAs($dentistOther)->get(route('reports.personal'))
            ->assertOk()
            ->assertDontSee('Scaling');

        $csv = $this->actingAs($managerA)->get(route('reports.visits', ['export' => 'csv']));
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Pasien Rahasia A', $csv->streamedContent());
    }

    public function test_auditor_can_read_reports_but_cannot_write_expense(): void
    {
        $auditor = $this->tenantUser(RoleName::Auditor, $this->tenantA, $this->branchA);

        $this->actingAs($auditor)->get(route('reports.medical'))->assertOk();
        $this->actingAs($auditor)->get(route('reports.finance'))->assertOk();
        $this->actingAs($auditor)->post(route('finance.expenses.store'), [
            'amount' => '1000',
            'description' => 'Tidak boleh',
            'expense_date' => now()->toDateString(),
            'category_id' => 1,
            'cash_account_id' => 1,
            'branch_id' => $this->branchA->id,
        ])->assertForbidden();
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
