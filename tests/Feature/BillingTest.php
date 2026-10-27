<?php

namespace Tests\Feature;

use App\Enums\BillingStatus;
use App\Enums\CashShiftStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PrescriptionStatus;
use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Procedure;
use App\Models\ProcedureRecord;
use App\Models\Tariff;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use App\Support\Billing\BillingService;
use App\Support\Pharmacy\PharmacyStockService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
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

    public function test_completing_visit_generates_invoice_with_consistent_total(): void
    {
        $dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $visit = $this->openVisit($dentist);

        $this->actingAs($dentist)->post(route('care.procedure.store', $visit), [
            'procedure_id' => $this->procedureA->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->actingAs($dentist)->post(route('care.complete', $visit))->assertRedirect();

        $invoice = Invoice::query()->where('visit_id', $visit->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('500000.00', $invoice->total_amount);
        $this->assertSame(0, bccomp((string) $invoice->total_amount, (string) $invoice->items()->sum('subtotal'), 2));
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertSame(BillingStatus::Billed, ProcedureRecord::query()->where('visit_id', $visit->id)->first()?->billing_status);
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $this->actingAs($cashier)
            ->get(route('billing.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('Coming Soon')
            ->assertSee('500.000')
            ->assertSee('Cetak');
        $this->actingAs($cashier)
            ->get(route('billing.invoices.print', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_fulfilled_prescription_is_added_to_open_invoice(): void
    {
        $dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $pharmacy = $this->tenantUser(RoleName::Pharmacy, $this->tenantA, $this->branchA);
        $visit = $this->openVisit($dentist);

        $this->actingAs($dentist)->post(route('care.procedure.store', $visit), [
            'procedure_id' => $this->procedureA->id,
            'quantity' => 1,
        ]);
        $this->actingAs($dentist)->post(route('care.prescription-item.store', $visit), [
            'medicine_id' => $this->medicineA->id,
            'quantity' => 10,
        ]);
        $rx = Prescription::query()->where('visit_id', $visit->id)->first();
        $this->actingAs($dentist)->post(route('care.prescription.send', [$visit, $rx]));
        $this->actingAs($dentist)->post(route('care.complete', $visit));

        MedicineStock::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'medicine_id' => $this->medicineA->id,
            'quantity' => 20,
            'expired_date' => now()->addMonth()->toDateString(),
        ]);
        app(PharmacyStockService::class)->fulfill($rx->fresh(), $pharmacy);

        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $this->actingAs($cashier)->post(route('billing.invoices.generate'), [
            'visit_id' => $visit->id,
        ])->assertRedirect();

        $invoice = Invoice::query()->where('visit_id', $visit->id)->first();
        $this->assertCount(2, $invoice->items);
        $this->assertEquals('275000.00', $invoice->fresh()->total_amount);
    }

    public function test_partial_then_full_payment_updates_status(): void
    {
        $invoice = $this->invoiceFromVisit();
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $this->openShift($cashier);

        $this->actingAs($cashier)
            ->from(route('billing.invoices.show', $invoice))
            ->post(route('billing.invoices.pay', $invoice), [
                'amount' => '100000',
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertRedirect();

        $this->assertSame(InvoiceStatus::Partial, $invoice->fresh()->status);
        $this->assertSame('100000.00', $invoice->fresh()->paid_amount);

        $this->actingAs($cashier)
            ->post(route('billing.invoices.pay', $invoice), [
                'amount' => '150000',
                'method' => PaymentMethod::Qris->value,
            ])
            ->assertRedirect();

        $this->assertTrue($invoice->fresh()->isPaid());
        $this->assertSame('0.00', $invoice->fresh()->remainingAmount());
    }

    public function test_overpay_is_rejected_and_cashier_needs_open_shift(): void
    {
        $invoice = $this->invoiceFromVisit();
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);

        $this->actingAs($cashier)
            ->from(route('billing.invoices.show', $invoice))
            ->post(route('billing.invoices.pay', $invoice), [
                'amount' => '10000',
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->openShift($cashier);

        $this->actingAs($cashier)
            ->from(route('billing.invoices.show', $invoice))
            ->post(route('billing.invoices.pay', $invoice), [
                'amount' => '999999',
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
        $this->assertSame('0.00', $invoice->fresh()->paid_amount);
    }

    public function test_cannot_open_two_shifts_and_close_records_variance(): void
    {
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);

        $this->actingAs($cashier)->post(route('cashier.shifts.open.store'), [
            'branch_id' => $this->branchA->id,
            'opening_balance' => '50000',
        ])->assertRedirect();

        $this->actingAs($cashier)
            ->from(route('cashier.shifts.open'))
            ->post(route('cashier.shifts.open.store'), [
                'branch_id' => $this->branchA->id,
                'opening_balance' => '10000',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $invoice = $this->invoiceFromVisit();
        $this->actingAs($cashier)->post(route('billing.invoices.pay', $invoice), [
            'amount' => '25000',
            'method' => PaymentMethod::Cash->value,
        ]);

        $this->actingAs($cashier)->post(route('cashier.shifts.close.store'), [
            'closing_balance' => '80000',
            'close_notes' => 'Ada kelebihan tunai',
        ])->assertRedirect();

        $closed = \App\Models\CashierShift::query()->where('cashier_id', $cashier->id)->first();
        $this->assertSame(CashShiftStatus::Closed, $closed->status);
        $this->assertSame('75000.00', $closed->system_balance);
        $this->assertSame('80000.00', $closed->closing_balance);
        $this->assertSame('5000.00', $closed->variance);
    }

    public function test_void_requires_owner_and_restores_unbilled_status(): void
    {
        $invoice = $this->invoiceFromVisit();
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);

        $this->actingAs($cashier)
            ->post(route('billing.invoices.void', $invoice), ['reason' => 'Salah tagih pasien'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('billing.invoices.void', $invoice), ['reason' => 'Salah tagih pasien'])
            ->assertRedirect();

        $this->assertTrue($invoice->fresh()->isVoid());
        $this->assertSame(
            BillingStatus::Unbilled,
            ProcedureRecord::query()->where('visit_id', $invoice->visit_id)->first()?->billing_status
        );
    }

    public function test_cross_tenant_invoice_is_forbidden(): void
    {
        $invoice = $this->invoiceFromVisit();
        $other = $this->tenantUser(RoleName::Cashier, $this->tenantB, $this->branchB);

        $this->actingAs($other)->get(route('billing.invoices.show', $invoice))->assertForbidden();
        $this->actingAs($other)->post(route('billing.invoices.pay', $invoice), [
            'amount' => '1000',
            'method' => PaymentMethod::Cash->value,
        ])->assertForbidden();
    }

    public function test_cashier_pages_are_not_placeholders(): void
    {
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);

        foreach ([
            route('billing.invoices.today'),
            route('billing.payments'),
            route('billing.receivables'),
            route('cashier.shifts.open'),
            route('cashier.reports'),
        ] as $url) {
            $this->actingAs($cashier)->get($url)->assertOk()->assertDontSee('Coming Soon');
        }
    }

    public function test_cashier_page_adds_fulfilled_medicine_to_the_open_invoice(): void
    {
        $invoice = $this->invoiceFromVisit();
        $dentist = $invoice->visit->doctor;
        $this->actingAs($dentist)->post(route('care.prescription-item.store', $invoice->visit), [
            'medicine_id' => $this->medicineA->id,
            'quantity' => 10,
        ])->assertRedirect();
        Prescription::query()->where('visit_id', $invoice->visit_id)->update([
            'status' => PrescriptionStatus::Fulfilled,
        ]);

        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $this->actingAs($cashier)
            ->get(route('billing.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Amoxicillin')
            ->assertSee('275.000')
            ->assertSee('Cetak');

        $this->assertSame('275000.00', $invoice->fresh()->total_amount);
        $this->assertSame(2, $invoice->items()->count());
    }

    public function test_cashier_search_stays_inside_the_branch(): void
    {
        $invoice = $this->invoiceFromVisit();
        $invoice->patient->update(['name' => 'Pasien Kasir Unik', 'medical_record_number' => 'RM-KASIR-23']);
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $otherBranch = Branch::factory()->create(['tenant_id' => $this->tenantA->id]);
        $other = $this->tenantUser(RoleName::Cashier, $this->tenantA, $otherBranch);

        $this->actingAs($cashier)
            ->getJson(route('billing.invoices.search', ['q' => 'RM-KASIR-23']))
            ->assertOk()
            ->assertJsonFragment(['number' => $invoice->number]);

        $this->actingAs($other)
            ->getJson(route('billing.invoices.search', ['q' => 'RM-KASIR-23']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($other)
            ->get(route('billing.invoices.show', $invoice))
            ->assertForbidden();
    }

    public function test_service_confirmation_is_recorded_separately_from_payment(): void
    {
        $invoice = $this->invoiceFromVisit();
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $procedureId = ProcedureRecord::query()->where('visit_id', $invoice->visit_id)->value('id');

        $this->actingAs($cashier)
            ->post(route('billing.invoices.confirm', $invoice), [
                'procedures' => [$procedureId],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'cashier_confirmed',
            'subject_id' => $invoice->id,
        ]);
        $this->assertSame(0, $invoice->payments()->count());

        $this->actingAs($cashier)
            ->get(route('billing.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Dikonfirmasi')
            ->assertDontSee('Tidak sesuai');
    }

    public function test_mismatched_procedure_quantity_is_shown_before_payment(): void
    {
        $invoice = $this->invoiceFromVisit();
        $item = $invoice->items()->first();
        $item->update([
            'quantity' => 3,
            'subtotal' => bcmul((string) $item->unit_price, '3', 2),
        ]);

        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $this->actingAs($cashier)
            ->get(route('billing.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Tidak sesuai')
            ->assertSee('berbeda dari rekam tindakan');
    }

    public function test_cash_payment_rejects_short_tender_and_duplicate_full_payment(): void
    {
        $invoice = $this->invoiceFromVisit();
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $this->openShift($cashier);

        $this->actingAs($cashier)
            ->from(route('billing.invoices.show', $invoice))
            ->post(route('billing.invoices.pay', $invoice), [
                'cashier' => '1',
                'amount' => '250000',
                'method' => PaymentMethod::Cash->value,
                'tendered' => '1000',
                'service_confirmed' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, $invoice->payments()->count());

        $this->actingAs($cashier)
            ->post(route('billing.invoices.pay', $invoice), [
                'cashier' => '1',
                'amount' => '250000',
                'method' => PaymentMethod::Cash->value,
                'tendered' => '300000',
                'service_confirmed' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('cashier_print');

        $this->assertTrue($invoice->fresh()->isPaid());
        $this->assertSame('250000.00', $invoice->fresh()->paid_amount);

        $this->actingAs($cashier)
            ->from(route('billing.invoices.show', $invoice))
            ->post(route('billing.invoices.pay', $invoice), [
                'cashier' => '1',
                'amount' => '250000',
                'method' => PaymentMethod::Cash->value,
                'tendered' => '250000',
                'service_confirmed' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, $invoice->payments()->count());
        $this->actingAs($cashier)
            ->get(route('billing.invoices.receipt', [$invoice, 'paper' => '80']))
            ->assertOk()
            ->assertSee($invoice->number);
        $this->actingAs($cashier)
            ->get(route('billing.invoices.print', [$invoice, 'paper' => 'a5']))
            ->assertOk();
    }

    public function test_declined_medicine_is_removed_from_the_bill_and_stock_is_returned_once(): void
    {
        [$invoice, $item, $stock] = $this->fulfilledMedicineInvoice();
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);

        $this->actingAs($cashier)
            ->post(route('billing.invoices.medicines.decline', [$invoice, $item]), [
                'quantity' => 4,
                'reason' => 'tidak_jadi',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($cashier)
            ->post(route('billing.invoices.medicines.decline', [$invoice, $item]), [
                'quantity' => 4,
                'reason' => 'tidak_jadi',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('255000.00', $invoice->total_amount);
        $this->assertSame(2, (int) $invoice->items()->where('source_type', 'medicine')->where('source_id', $item->id)->value('quantity'));
        $this->assertNotNull(PrescriptionItem::query()->find($item->id));
        $this->assertSame(18, (int) $stock->fresh()->quantity);
        $this->assertSame(2, ActivityLog::query()->where('event', 'medicine_not_taken')->where('subject_id', $item->id)->count());

        $this->actingAs($cashier)
            ->from(route('billing.invoices.show', $invoice))
            ->post(route('billing.invoices.medicines.decline', [$invoice, $item]), [
                'quantity' => 4,
                'reason' => 'tidak_jadi',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(18, (int) $stock->fresh()->quantity);
    }

    public function test_confirmed_handover_and_paid_invoice_do_not_silently_change_the_medicine_bill(): void
    {
        [$invoice, $item, $stock] = $this->fulfilledMedicineInvoice();
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $this->openShift($cashier);

        $this->actingAs($cashier)
            ->post(route('billing.invoices.confirm', $invoice), [
                'medicines' => [$item->id],
            ])
            ->assertRedirect();

        $this->actingAs($cashier)
            ->from(route('billing.invoices.show', $invoice))
            ->post(route('billing.invoices.medicines.decline', [$invoice, $item]), [
                'quantity' => 2,
                'reason' => 'menolak',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('275000.00', $invoice->fresh()->total_amount);
        $this->assertSame(10, (int) $stock->fresh()->quantity);

        [$paidInvoice, $paidItem] = array_slice($this->fulfilledMedicineInvoice(), 0, 2);
        $this->actingAs($cashier)->post(route('billing.invoices.pay', $paidInvoice), [
            'amount' => '275000',
            'method' => PaymentMethod::Transfer->value,
        ])->assertRedirect();

        $this->actingAs($cashier)
            ->from(route('billing.invoices.show', $paidInvoice))
            ->post(route('billing.invoices.medicines.decline', [$paidInvoice, $paidItem]), [
                'quantity' => 1,
                'reason' => 'lainnya',
                'detail' => 'Pasien batal setelah lunas',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('275000.00', $paidInvoice->fresh()->total_amount);
        $this->assertTrue($paidInvoice->fresh()->isPaid());
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'medicine_decline_requested',
            'subject_id' => $paidItem->id,
        ]);
    }

    public function test_partial_payment_stays_when_declined_medicine_makes_the_bill_smaller(): void
    {
        [$invoice, $item] = array_slice($this->fulfilledMedicineInvoice(), 0, 2);
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);
        $this->openShift($cashier);

        $this->actingAs($cashier)->post(route('billing.invoices.pay', $invoice), [
            'amount' => '270000',
            'method' => PaymentMethod::Card->value,
        ])->assertRedirect();

        $this->actingAs($cashier)
            ->post(route('billing.invoices.medicines.decline', [$invoice, $item]), [
                'quantity' => 4,
                'reason' => 'beli_luar',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame('265000.00', $invoice->total_amount);
        $this->assertSame('270000.00', $invoice->paid_amount);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_medicine_declined_before_dispensing_is_not_billed_or_stocked(): void
    {
        $invoice = $this->invoiceFromVisit();
        $dentist = $invoice->visit->doctor;
        $pharmacy = $this->tenantUser(RoleName::Pharmacy, $this->tenantA, $this->branchA);
        $cashier = $this->tenantUser(RoleName::Cashier, $this->tenantA, $this->branchA);

        $this->actingAs($dentist)->post(route('care.prescription-item.store', $invoice->visit), [
            'medicine_id' => $this->medicineA->id,
            'quantity' => 10,
        ])->assertRedirect();
        $rx = Prescription::query()->where('visit_id', $invoice->visit_id)->first();
        $item = $rx->items()->first();
        $this->actingAs($dentist)->post(route('care.prescription.send', [$invoice->visit, $rx]));
        $stock = MedicineStock::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'medicine_id' => $this->medicineA->id,
            'quantity' => 20,
            'expired_date' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($cashier)
            ->post(route('billing.invoices.medicines.decline', [$invoice, $item]), [
                'quantity' => 10,
                'reason' => 'tidak_jadi',
            ])
            ->assertRedirect();

        app(PharmacyStockService::class)->fulfill($rx->fresh(), $pharmacy);

        $this->assertSame(20, (int) $stock->fresh()->quantity);
        $this->assertSame('250000.00', $invoice->fresh()->total_amount);
        $this->assertNotNull(PrescriptionItem::query()->find($item->id));
    }

    /**
     * @return array{0: Invoice, 1: PrescriptionItem, 2: MedicineStock}
     */
    protected function fulfilledMedicineInvoice(): array
    {
        $invoice = $this->invoiceFromVisit();
        $dentist = $invoice->visit->doctor;
        $pharmacy = $this->tenantUser(RoleName::Pharmacy, $this->tenantA, $this->branchA);

        $this->actingAs($dentist)->post(route('care.prescription-item.store', $invoice->visit), [
            'medicine_id' => $this->medicineA->id,
            'quantity' => 10,
        ])->assertRedirect();
        $rx = Prescription::query()->where('visit_id', $invoice->visit_id)->first();
        $this->actingAs($dentist)->post(route('care.prescription.send', [$invoice->visit, $rx]));
        $stock = MedicineStock::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'medicine_id' => $this->medicineA->id,
            'quantity' => 20,
            'expired_date' => now()->addMonth()->toDateString(),
        ]);
        app(PharmacyStockService::class)->fulfill($rx->fresh(), $pharmacy);

        return [
            $invoice->fresh(),
            $rx->items()->first(),
            $stock->fresh(),
        ];
    }

    protected function invoiceFromVisit(): Invoice
    {
        $dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $visit = $this->openVisit($dentist);
        ProcedureRecord::factory()->create([
            'visit_id' => $visit->id,
            'procedure_id' => $this->procedureA->id,
            'quantity' => 1,
            'price_at_time' => '250000.00',
            'billing_status' => BillingStatus::Unbilled,
        ]);

        return app(BillingService::class)->generateForVisit($visit, $dentist);
    }

    protected function openVisit(User $dentist): Visit
    {
        return Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenantA->id])->id,
            'doctor_id' => $dentist->id,
            'payer_id' => $this->payerA->id,
        ]);
    }

    protected function openShift(User $cashier): void
    {
        $this->actingAs($cashier)->post(route('cashier.shifts.open.store'), [
            'branch_id' => $this->branchA->id,
            'opening_balance' => '0',
        ])->assertRedirect();
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
