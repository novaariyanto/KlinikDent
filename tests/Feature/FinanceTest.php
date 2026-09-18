<?php

namespace Tests\Feature;

use App\Enums\BillingStatus;
use App\Enums\CashMutationType;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\CashMutation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Procedure;
use App\Models\ProcedureRecord;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tariff;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use App\Support\Billing\BillingService;
use App\Support\Finance\CashLedgerService;
use App\Support\Finance\FinanceReportService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Payer $payerA;

    protected Procedure $procedureA;

    protected Medicine $medicineA;

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
        $this->medicineA = Medicine::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Amoxicillin 500mg',
            'base_price' => '2500.00',
        ]);

        Tariff::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'procedure_id' => $this->procedureA->id,
            'branch_id' => null,
            'payer_id' => null,
            'price' => '250000.00',
            'effective_date' => now()->subDay()->toDateString(),
        ]);
    }

    public function test_payment_observer_posts_cash_mutation_idempotently(): void
    {
        $invoice = $this->invoiceFromVisit();
        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);

        $this->actingAs($owner)->post(route('billing.invoices.pay', $invoice), [
            'amount' => '100000',
            'method' => PaymentMethod::Cash->value,
        ])->assertRedirect();

        $account = CashAccount::query()
            ->where('branch_id', $this->branchA->id)
            ->where('type', 'cash')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('100000.00', $account->fresh()->balance);
        $this->assertSame(1, CashMutation::query()->where('cash_account_id', $account->id)->count());

        $payment = $invoice->fresh()->payments()->first();
        app(CashLedgerService::class)->record(
            $account->fresh(),
            CashMutationType::In,
            '100000.00',
            $payment,
            'duplikat',
        );

        $this->assertSame(1, CashMutation::query()->where('cash_account_id', $account->id)->count());
        $this->assertSame('100000.00', $account->fresh()->balance);
        $this->assertSame($account->fresh()->balance, $account->fresh()->load('mutations')->mutationsSum());
    }

    public function test_expense_and_payable_reduce_cash_and_profit_loss_matches(): void
    {
        $invoice = $this->invoiceFromVisit();
        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        $finance = $this->tenantUser(RoleName::Finance, $this->tenantA, $this->branchA);
        $category = ExpenseCategory::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Operasional']);

        $this->actingAs($owner)->post(route('billing.invoices.pay', $invoice), [
            'amount' => '200000',
            'method' => PaymentMethod::Cash->value,
        ])->assertRedirect();

        $stock = MedicineStock::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'medicine_id' => $this->medicineA->id,
            'quantity' => 20,
            'expired_date' => now()->addMonth()->toDateString(),
        ]);
        StockMovement::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'medicine_stock_id' => $stock->id,
            'type' => StockMovementType::Out,
            'quantity' => 4,
        ]);

        $account = CashAccount::query()->where('branch_id', $this->branchA->id)->where('type', 'cash')->first();

        $this->actingAs($finance)->post(route('finance.expenses.store'), [
            'branch_id' => $this->branchA->id,
            'category_id' => $category->id,
            'cash_account_id' => $account->id,
            'amount' => '30000',
            'description' => 'Biaya listrik',
            'expense_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame('170000.00', $account->fresh()->balance);
        $this->assertSame(1, Expense::query()->count());

        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenantA->id]);
        $order = PurchaseOrder::factory()->received()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $supplier->id,
        ]);
        $order->items()->create([
            'medicine_id' => $this->medicineA->id,
            'quantity' => 2,
            'unit_price' => '20000.00',
        ]);

        $this->actingAs($finance)->post(route('finance.payables.pay', $order), [
            'cash_account_id' => $account->id,
        ])->assertRedirect();

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame('130000.00', $account->fresh()->balance);
        $this->assertSame($account->fresh()->balance, $account->fresh()->load('mutations')->mutationsSum());

        $pl = app(FinanceReportService::class)->profitLoss(
            now()->toDateString(),
            now()->toDateString(),
            $this->branchA->id,
        );

        $this->assertSame('200000.00', $pl['revenue']);
        $this->assertSame('30000.00', $pl['expenses']);
        $this->assertSame('10000.00', $pl['hpp']);
        $this->assertSame('160000.00', $pl['profit']);
    }

    public function test_finance_pages_are_not_placeholders_and_isolated(): void
    {
        $finance = $this->tenantUser(RoleName::Finance, $this->tenantA, $this->branchA);
        $other = $this->tenantUser(RoleName::Finance, $this->tenantB, $this->branchB);
        $category = ExpenseCategory::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Rahasia']);

        foreach ([
            route('finance.index'),
            route('finance.revenue.daily'),
            route('finance.revenue.monthly'),
            route('finance.revenue.doctors'),
            route('finance.expenses'),
            route('finance.expenses.categories'),
            route('finance.expenses.suppliers'),
            route('finance.receivables'),
            route('finance.payables'),
            route('finance.cash-bank'),
            route('finance.reports.expenses'),
            route('finance.reports.cashflow'),
            route('finance.reports.receivables'),
            route('finance.reports.profit-loss'),
            route('dashboard'),
        ] as $url) {
            $this->actingAs($finance)->get($url)->assertOk()->assertDontSee('Coming Soon');
        }

        $this->actingAs($other)->get(route('finance.expenses.categories'))->assertOk()->assertDontSee('Rahasia');
        $this->actingAs($other)->put(route('finance.expenses.categories.update', $category), [
            'name' => 'Bocor',
        ])->assertForbidden();
        $this->actingAs($this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA))
            ->get(route('finance.cash-bank'))
            ->assertForbidden();
    }

    protected function invoiceFromVisit(): Invoice
    {
        $dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenantA->id])->id,
            'doctor_id' => $dentist->id,
            'payer_id' => $this->payerA->id,
        ]);
        ProcedureRecord::factory()->create([
            'visit_id' => $visit->id,
            'procedure_id' => $this->procedureA->id,
            'quantity' => 1,
            'price_at_time' => '250000.00',
            'billing_status' => BillingStatus::Unbilled,
        ]);

        return app(BillingService::class)->generateForVisit($visit, $dentist);
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
