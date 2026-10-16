<?php

namespace Tests\Feature;

use App\Enums\BillingStatus;
use App\Enums\InvoiceItemSource;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\SharingFeeCalcType;
use App\Enums\SharingFeeMaterialDeductionType;
use App\Enums\SharingFeePeriodType;
use App\Enums\SharingFeeSettlementStatus;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\CashMutation;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Procedure;
use App\Models\ProcedureRecord;
use App\Models\SharingFeeRule;
use App\Models\SharingFeeSettlement;
use App\Models\SharingFeeSettlementItem;
use App\Models\Tariff;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use App\Support\Billing\BillingService;
use App\Support\Finance\SharingFee\SharingFeeException;
use App\Support\Finance\SharingFee\SharingFeePaymentService;
use App\Support\Finance\SharingFee\SharingFeeRuleResolver;
use App\Support\Finance\SharingFee\SharingFeeSettlementService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharingFeeTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Payer $payerA;

    protected Procedure $procedureA;

    protected Procedure $procedureB;

    protected User $doctor;

    protected User $finance;

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
        $this->procedureB = Procedure::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Tambal']);

        Tariff::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'procedure_id' => $this->procedureA->id,
            'branch_id' => null,
            'payer_id' => null,
            'price' => '250000.00',
            'effective_date' => now()->subDay()->toDateString(),
        ]);

        $this->doctor = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $this->finance = $this->tenantUser(RoleName::Finance, $this->tenantA, $this->branchA);
    }

    public function test_percentage_and_fixed_rules_produce_expected_settlement_fees(): void
    {
        $this->createRule([
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '40',
            'procedure_id' => $this->procedureA->id,
            'priority' => 10,
        ]);

        $invoice = $this->paidInvoice($this->procedureA, 1, '250000.00');
        $settlements = $this->generateForCurrentMonth();

        $this->assertCount(1, $settlements);
        $item = $settlements->first()->items->first();
        $this->assertSame('100000.00', (string) $item->fee_amount);

        $this->createRule([
            'calc_type' => SharingFeeCalcType::Fixed,
            'rate' => '50000',
            'procedure_id' => $this->procedureB->id,
            'priority' => 10,
        ]);

        Tariff::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'procedure_id' => $this->procedureB->id,
            'price' => '100000.00',
            'effective_date' => now()->subDay()->toDateString(),
        ]);

        $this->paidInvoice($this->procedureB, 2, '100000.00');
        $settlements2 = $this->generateForCurrentMonth();
        $fixedItem = $settlements2->first()->items->firstWhere('procedure_id', $this->procedureB->id);
        $this->assertSame('100000.00', (string) $fixedItem->fee_amount);
    }

    public function test_rule_engine_specificity_matrix(): void
    {
        $general = $this->createRule([
            'doctor_user_id' => null,
            'rate' => '40',
            'priority' => 100,
        ]);
        $procOnly = $this->createRule([
            'doctor_user_id' => null,
            'procedure_id' => $this->procedureA->id,
            'rate' => '45',
            'priority' => 100,
        ]);
        $doctorOnly = $this->createRule([
            'doctor_user_id' => $this->doctor->id,
            'rate' => '35',
            'priority' => 100,
        ]);
        $mostSpecific = $this->createRule([
            'doctor_user_id' => $this->doctor->id,
            'branch_id' => $this->branchA->id,
            'procedure_id' => $this->procedureA->id,
            'rate' => '50',
            'priority' => 100,
        ]);

        $resolver = app(SharingFeeRuleResolver::class);
        $otherDoctor = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);

        // Dokter selain Y, selain Scaling → 40%
        $this->assertSame($general->id, $resolver->resolve(
            $this->tenantA->id, $otherDoctor->id, $this->branchA->id, $this->procedureB->id, now()->toDateString()
        )?->id);

        // Dokter selain Y, Scaling → 45%
        $this->assertSame($procOnly->id, $resolver->resolve(
            $this->tenantA->id, $otherDoctor->id, $this->branchA->id, $this->procedureA->id, now()->toDateString()
        )?->id);

        // Dokter Y, selain Scaling → 35%
        $this->assertSame($doctorOnly->id, $resolver->resolve(
            $this->tenantA->id, $this->doctor->id, $this->branchA->id, $this->procedureB->id, now()->toDateString()
        )?->id);

        // Dokter Y, Cabang A, Scaling → 50%
        $this->assertSame($mostSpecific->id, $resolver->resolve(
            $this->tenantA->id, $this->doctor->id, $this->branchA->id, $this->procedureA->id, now()->toDateString()
        )?->id);
    }

    public function test_priority_only_breaks_ties_at_same_specificity(): void
    {
        $low = $this->createRule([
            'doctor_user_id' => null,
            'procedure_id' => $this->procedureA->id,
            'rate' => '10',
            'priority' => 10,
        ]);
        $high = $this->createRule([
            'doctor_user_id' => null,
            'procedure_id' => $this->procedureA->id,
            'rate' => '55',
            'priority' => 200,
        ]);

        $resolved = app(SharingFeeRuleResolver::class)->resolve(
            $this->tenantA->id,
            $this->doctor->id,
            $this->branchA->id,
            $this->procedureA->id,
            now()->toDateString(),
        );

        $this->assertSame($high->id, $resolved?->id);
        $this->assertNotSame($low->id, $resolved?->id);
    }

    public function test_inactive_and_out_of_period_rules_are_ignored(): void
    {
        $this->createRule([
            'doctor_user_id' => null,
            'rate' => '40',
            'is_active' => false,
        ]);
        $this->createRule([
            'doctor_user_id' => null,
            'rate' => '30',
            'effective_from' => now()->addWeek()->toDateString(),
        ]);
        $this->createRule([
            'doctor_user_id' => null,
            'rate' => '20',
            'effective_from' => now()->subMonths(2)->toDateString(),
            'effective_to' => now()->subMonth()->toDateString(),
        ]);

        $resolved = app(SharingFeeRuleResolver::class)->resolve(
            $this->tenantA->id,
            $this->doctor->id,
            $this->branchA->id,
            $this->procedureA->id,
            now()->toDateString(),
        );

        $this->assertNull($resolved);
    }

    public function test_separated_periods_same_scope_can_coexist(): void
    {
        $this->createRule([
            'doctor_user_id' => null,
            'rate' => '40',
            'priority' => 100,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-03-31',
        ]);

        $this->actingAs($this->finance)
            ->post(route('finance.sharing-fees.rules.store'), [
                'doctor_user_id' => '__all__',
                'branch_id' => '__all__',
                'procedure_id' => '__all__',
                'calc_type' => SharingFeeCalcType::Percentage->value,
                'rate' => '42',
                'material_deduction_type' => SharingFeeMaterialDeductionType::None->value,
                'material_deduction_value' => '0',
                'priority' => 100,
                'effective_from' => '2026-04-01',
                'effective_to' => '2026-12-31',
                'is_active' => '1',
            ])
            ->assertRedirect(route('finance.sharing-fees.rules.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, SharingFeeRule::query()->whereNull('doctor_user_id')->count());
    }

    public function test_preview_endpoint_uses_same_resolver(): void
    {
        $this->createRule([
            'doctor_user_id' => null,
            'rate' => '40',
            'material_deduction_type' => SharingFeeMaterialDeductionType::Percent,
            'material_deduction_value' => '10',
        ]);

        $this->actingAs($this->finance)
            ->postJson(route('finance.sharing-fees.rules.preview'), [
                'doctor_user_id' => '__all__',
                'branch_id' => '__all__',
                'procedure_id' => '__all__',
                'on_date' => now()->toDateString(),
                'gross_base' => '250000',
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('calculation.fee_amount', '90000.00');
    }

    public function test_material_deduction_reduces_fee_base(): void
    {
        $this->createRule([
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '50',
            'material_deduction_type' => SharingFeeMaterialDeductionType::Percent,
            'material_deduction_value' => '20',
            'priority' => 10,
        ]);

        $this->paidInvoice($this->procedureA, 1, '100000.00');
        $item = $this->generateForCurrentMonth()->first()->items->first();

        $this->assertSame('20000.00', (string) $item->material_deduction);
        $this->assertSame('80000.00', (string) $item->fee_base);
        $this->assertSame('40000.00', (string) $item->fee_amount);
    }

    public function test_partial_invoice_is_excluded_until_paid(): void
    {
        $this->createRule([
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '40',
            'priority' => 10,
        ]);

        $invoice = $this->createUnpaidInvoice($this->procedureA, 1, '250000.00');
        app(BillingService::class)->pay($invoice, $this->finance, '100000', PaymentMethod::Cash);
        $this->assertSame(InvoiceStatus::Partial, $invoice->fresh()->status);

        try {
            $this->generateForCurrentMonth();
            $this->fail('Expected exception for no eligible items');
        } catch (SharingFeeException $e) {
            $this->assertStringContainsString('Tidak ada item', $e->getMessage());
        }

        app(BillingService::class)->pay($invoice->fresh(), $this->finance, '150000', PaymentMethod::Cash);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $settlements = $this->generateForCurrentMonth();
        $this->assertCount(1, $settlements->first()->items);
    }

    public function test_void_invoice_items_are_not_settled(): void
    {
        $this->createRule([
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '40',
            'priority' => 10,
        ]);

        $invoice = $this->paidInvoice($this->procedureA, 1, '250000.00');
        $invoice->update(['status' => InvoiceStatus::Void]);

        $this->expectException(SharingFeeException::class);
        $this->generateForCurrentMonth();
    }

    public function test_duplicate_generate_does_not_double_fee_items(): void
    {
        $this->createRule([
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '40',
            'priority' => 10,
        ]);

        $this->paidInvoice($this->procedureA, 1, '250000.00');
        $first = $this->generateForCurrentMonth();
        $second = $this->generateForCurrentMonth();

        $this->assertSame(1, SharingFeeSettlementItem::query()->count());
        $this->assertSame($first->first()->id, $second->first()->id);
        $this->assertSame('100000.00', (string) $second->first()->fresh()->gross_fee_amount);
    }

    public function test_rule_change_does_not_alter_existing_settlement_snapshot(): void
    {
        $rule = $this->createRule([
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '40',
            'priority' => 10,
        ]);

        $this->paidInvoice($this->procedureA, 1, '250000.00');
        $settlement = $this->generateForCurrentMonth()->first();
        $item = $settlement->items->first();

        $rule->update(['rate' => '10']);

        $this->assertSame('40.0000', (string) $item->fresh()->rate);
        $this->assertSame('100000.00', (string) $item->fresh()->fee_amount);
        $this->assertSame('40.0000', (string) ($item->fresh()->rule_snapshot['rate'] ?? ''));
    }

    public function test_settlement_approve_pay_and_adjustment_flow(): void
    {
        $this->createRule([
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '40',
            'priority' => 10,
        ]);
        $this->paidInvoice($this->procedureA, 1, '250000.00');
        $settlement = $this->generateForCurrentMonth()->first();

        $service = app(SharingFeeSettlementService::class);
        $approved = $service->approve($settlement, $this->finance);
        $this->assertSame(SharingFeeSettlementStatus::Approved, $approved->status);

        $account = CashAccount::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'balance' => '500000.00',
        ]);

        $payment = app(SharingFeePaymentService::class)->pay(
            $approved,
            $this->finance,
            '60000.00',
            PaymentMethod::Transfer,
            $account->id,
            now()->toDateString(),
            'TRX-1',
        );

        $this->assertSame(SharingFeeSettlementStatus::Approved, $approved->fresh()->status);
        $this->assertNotNull($payment->expense_id);
        $this->assertSame(1, Expense::query()->whereKey($payment->expense_id)->count());
        $this->assertSame(1, CashMutation::query()->where('source_type', $payment->getMorphClass())->count());
        $this->assertSame('40000.00', $approved->fresh()->outstandingAmount());

        $service->addAdjustment($approved->fresh(), $this->finance, '-25000.00', 'Koreksi refund pasien');
        $fresh = $approved->fresh();
        $this->assertSame('75000.00', (string) $fresh->net_fee_amount);
        $this->assertSame(SharingFeeSettlementStatus::Approved, $fresh->status);
        $this->assertSame('15000.00', $fresh->outstandingAmount());

        app(SharingFeePaymentService::class)->pay(
            $fresh,
            $this->finance,
            '15000.00',
            PaymentMethod::Transfer,
            $account->id,
            now()->toDateString(),
            'TRX-2',
        );

        $this->assertSame(SharingFeeSettlementStatus::Paid, $fresh->fresh()->status);
    }

    public function test_access_isolation_and_permissions(): void
    {
        $this->createRule(['rate' => '40', 'priority' => 10]);
        $this->paidInvoice($this->procedureA, 1, '250000.00');
        $settlement = $this->generateForCurrentMonth()->first();

        $this->actingAs($this->finance)
            ->get(route('finance.sharing-fees.rules.index'))
            ->assertOk();

        $this->actingAs($this->finance)
            ->get(route('finance.sharing-fees.settlements.show', $settlement))
            ->assertOk();

        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $this->actingAs($cashier)
            ->get(route('finance.sharing-fees.rules.index'))
            ->assertForbidden();

        $otherFinance = $this->tenantUser(RoleName::Finance, $this->tenantB, $this->branchB);
        $this->actingAs($otherFinance)
            ->get(route('finance.sharing-fees.settlements.show', $settlement))
            ->assertForbidden();
    }

    public function test_report_and_csv_export(): void
    {
        $this->createRule(['rate' => '40', 'priority' => 10]);
        $this->paidInvoice($this->procedureA, 1, '250000.00');
        $this->generateForCurrentMonth();

        $this->actingAs($this->finance)
            ->get(route('finance.sharing-fees.reports.doctors', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee($this->doctor->name);

        $csv = $this->actingAs($this->finance)
            ->get(route('finance.sharing-fees.reports.doctors', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
                'export' => 'csv',
            ]));

        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));
        $this->assertStringContainsString('Dokter', $csv->streamedContent());
    }

    public function test_conflicting_priority_on_same_scope_is_rejected(): void
    {
        $this->createRule([
            'doctor_user_id' => null,
            'rate' => '40',
            'priority' => 100,
            'procedure_id' => null,
            'branch_id' => null,
        ]);

        $this->actingAs($this->finance)
            ->post(route('finance.sharing-fees.rules.store'), [
                'doctor_user_id' => '__all__',
                'branch_id' => '__all__',
                'procedure_id' => '__all__',
                'calc_type' => SharingFeeCalcType::Percentage->value,
                'rate' => '30',
                'material_deduction_type' => SharingFeeMaterialDeductionType::None->value,
                'material_deduction_value' => '0',
                'priority' => 100,
                'effective_from' => now()->toDateString(),
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_general_and_specific_rules_may_coexist(): void
    {
        $this->createRule([
            'doctor_user_id' => null,
            'rate' => '40',
            'priority' => 100,
        ]);

        $this->actingAs($this->finance)
            ->post(route('finance.sharing-fees.rules.store'), [
                'doctor_user_id' => $this->doctor->id,
                'branch_id' => '__all__',
                'procedure_id' => '__all__',
                'calc_type' => SharingFeeCalcType::Percentage->value,
                'rate' => '35',
                'material_deduction_type' => SharingFeeMaterialDeductionType::None->value,
                'material_deduction_value' => '0',
                'priority' => 100,
                'effective_from' => now()->toDateString(),
                'is_active' => '1',
            ])
            ->assertRedirect(route('finance.sharing-fees.rules.index'))
            ->assertSessionHas('success');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createRule(array $overrides = []): SharingFeeRule
    {
        return SharingFeeRule::query()->create(array_merge([
            'tenant_id' => $this->tenantA->id,
            'doctor_user_id' => $this->doctor->id,
            'branch_id' => null,
            'procedure_id' => null,
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '40',
            'material_deduction_type' => SharingFeeMaterialDeductionType::None,
            'material_deduction_value' => '0',
            'priority' => 100,
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
            'created_by' => $this->finance->id,
        ], $overrides));
    }

    protected function paidInvoice(Procedure $procedure, int $qty, string $unitPrice): Invoice
    {
        $invoice = $this->createUnpaidInvoice($procedure, $qty, $unitPrice);
        $total = bcmul($unitPrice, (string) $qty, 2);
        app(BillingService::class)->pay($invoice, $this->finance, $total, PaymentMethod::Cash);

        return $invoice->fresh(['items', 'visit']);
    }

    protected function createUnpaidInvoice(Procedure $procedure, int $qty, string $unitPrice): Invoice
    {
        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenantA->id])->id,
            'doctor_id' => $this->doctor->id,
            'payer_id' => $this->payerA->id,
        ]);

        $record = ProcedureRecord::factory()->create([
            'visit_id' => $visit->id,
            'procedure_id' => $procedure->id,
            'quantity' => $qty,
            'price_at_time' => $unitPrice,
            'billing_status' => BillingStatus::Unbilled,
        ]);

        $invoice = app(BillingService::class)->generateForVisit($visit, $this->finance);
        $this->assertNotNull($invoice);
        $this->assertTrue(
            $invoice->items()->where('source_type', InvoiceItemSource::Procedure->value)->where('source_id', $record->id)->exists()
        );

        return $invoice->fresh(['items', 'visit']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, SharingFeeSettlement>
     */
    protected function generateForCurrentMonth()
    {
        $result = app(SharingFeeSettlementService::class)->generate(
            $this->finance,
            SharingFeePeriodType::Monthly,
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
            $this->branchA->id,
            $this->doctor->id,
        );

        return $result['settlements'];
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
