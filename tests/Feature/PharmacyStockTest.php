<?php

namespace Tests\Feature;

use App\Enums\PrescriptionStatus;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Prescription;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use App\Support\Pharmacy\InsufficientStockException;
use App\Support\Pharmacy\PharmacyStockService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyStockTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Payer $payerA;

    protected Medicine $medicineA;

    protected User $pharmacy;

    protected User $dentist;

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
        $this->medicineA = Medicine::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Amoxicillin 500mg']);
        $this->pharmacy = $this->tenantUser(RoleName::Pharmacy, $this->tenantA, $this->branchA);
        $this->dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
    }

    public function test_pharmacy_pages_are_not_placeholders(): void
    {
        $this->actingAs($this->pharmacy)
            ->get(route('pharmacy.index'))
            ->assertOk()
            ->assertDontSee('Coming Soon');

        $this->actingAs($this->pharmacy)
            ->get(route('pharmacy.stock.index'))
            ->assertOk()
            ->assertDontSee('Coming Soon');

        $this->actingAs($this->pharmacy)
            ->get(route('pharmacy.purchases.suppliers'))
            ->assertOk()
            ->assertDontSee('Coming Soon');
    }

    public function test_pharmacy_can_fulfill_sent_prescription_using_fefo(): void
    {
        $near = $this->stock('NEAR', now()->addDays(10)->toDateString(), 5);
        $far = $this->stock('FAR', now()->addMonths(6)->toDateString(), 20);
        $expired = $this->stock('EXP', now()->subDay()->toDateString(), 50);

        $prescription = $this->sentPrescription(8);

        $this->actingAs($this->pharmacy)
            ->get(route('pharmacy.prescriptions.show', $prescription))
            ->assertOk()
            ->assertSee('NEAR')
            ->assertDontSee('EXP');

        $this->actingAs($this->pharmacy)
            ->from(route('pharmacy.prescriptions.show', $prescription))
            ->post(route('pharmacy.prescriptions.fulfill', $prescription))
            ->assertRedirect(route('pharmacy.prescriptions.show', $prescription))
            ->assertSessionHas('success');

        $this->assertTrue($prescription->fresh()->isFulfilled());
        $this->assertSame(0, $near->fresh()->quantity);
        $this->assertSame(17, $far->fresh()->quantity);
        $this->assertSame(50, $expired->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'medicine_stock_id' => $near->id,
            'type' => StockMovementType::Out->value,
            'quantity' => 5,
        ]);
    }

    public function test_fulfill_is_blocked_when_usable_stock_is_insufficient(): void
    {
        $this->stock('OK', now()->addMonths(3)->toDateString(), 2);
        $this->stock('EXP', now()->subDays(2)->toDateString(), 40);
        $prescription = $this->sentPrescription(10);

        $this->actingAs($this->pharmacy)
            ->from(route('pharmacy.prescriptions.show', $prescription))
            ->post(route('pharmacy.prescriptions.fulfill', $prescription))
            ->assertRedirect(route('pharmacy.prescriptions.show', $prescription))
            ->assertSessionHas('error');

        $this->assertSame(PrescriptionStatus::Sent, $prescription->fresh()->status);
        $this->assertSame(2, MedicineStock::query()->where('batch_number', 'OK')->value('quantity'));
    }

    public function test_concurrent_fulfill_does_not_make_stock_negative(): void
    {
        $stock = $this->stock('ONE', now()->addMonths(2)->toDateString(), 10);
        $first = $this->sentPrescription(10);
        $second = $this->sentPrescription(10);
        $service = app(PharmacyStockService::class);

        $service->fulfill($first, $this->pharmacy);

        try {
            $service->fulfill($second, $this->pharmacy);
            $secondFailed = false;
        } catch (InsufficientStockException) {
            $secondFailed = true;
        }

        $this->assertTrue($secondFailed);
        $this->assertSame(0, $stock->fresh()->quantity);
        $this->assertTrue($first->fresh()->isFulfilled());
        $this->assertTrue($second->fresh()->isSent());
        $this->assertSame(0, MedicineStock::query()->where('quantity', '<', 0)->count());
    }

    public function test_receiving_purchase_order_increases_stock(): void
    {
        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'PT Sehat']);
        $order = PurchaseOrder::factory()->ordered()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $supplier->id,
            'number' => 'PO-2026-00999',
        ]);
        $item = $order->items()->create([
            'medicine_id' => $this->medicineA->id,
            'quantity' => 25,
            'unit_price' => 2000,
        ]);

        $this->actingAs($this->pharmacy)
            ->post(route('pharmacy.orders.receive.store', $order), [
                'items' => [
                    $item->id => [
                        'batch_number' => 'RCV-01',
                        'expired_date' => now()->addYear()->toDateString(),
                    ],
                ],
            ])
            ->assertRedirect(route('pharmacy.orders.show', $order));

        $this->assertTrue($order->fresh()->isReceived());
        $this->assertSame(25, (int) MedicineStock::query()->where('batch_number', 'RCV-01')->value('quantity'));
    }

    public function test_adjustment_cannot_make_stock_negative(): void
    {
        $stock = $this->stock('ADJ', now()->addMonths(4)->toDateString(), 3);

        $this->actingAs($this->pharmacy)
            ->from(route('pharmacy.stock.adjustments'))
            ->post(route('pharmacy.stock.adjustments.store'), [
                'medicine_stock_id' => $stock->id,
                'delta' => -5,
                'notes' => 'Koreksi opname',
            ])
            ->assertRedirect(route('pharmacy.stock.adjustments'))
            ->assertSessionHas('error');

        $this->assertSame(3, $stock->fresh()->quantity);
    }

    public function test_expired_report_shows_near_and_past_expiry(): void
    {
        $this->stock('OLD', now()->subDays(4)->toDateString(), 7);
        $this->stock('SOON', now()->addDays(5)->toDateString(), 4);
        $this->stock('SAFE', now()->addMonths(9)->toDateString(), 9);

        $this->actingAs($this->pharmacy)
            ->get(route('pharmacy.stock.expired'))
            ->assertOk()
            ->assertSee('OLD')
            ->assertSee('SOON')
            ->assertDontSee('SAFE');
    }

    public function test_cross_tenant_pharmacy_url_returns_forbidden(): void
    {
        $visitB = Visit::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->branchB->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenantB->id])->id,
            'payer_id' => Payer::factory()->create(['tenant_id' => $this->tenantB->id])->id,
        ]);
        $prescriptionB = Prescription::factory()->sent()->create([
            'visit_id' => $visitB->id,
            'doctor_id' => $this->tenantUser(RoleName::Dentist, $this->tenantB, $this->branchB)->id,
        ]);
        $stockB = MedicineStock::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->branchB->id,
            'medicine_id' => Medicine::factory()->create(['tenant_id' => $this->tenantB->id])->id,
            'batch_number' => 'OTHER',
            'quantity' => 12,
        ]);

        $this->actingAs($this->pharmacy)->get(route('pharmacy.prescriptions.show', $prescriptionB))->assertForbidden();
        $this->actingAs($this->pharmacy)->post(route('pharmacy.prescriptions.fulfill', $prescriptionB))->assertForbidden();
        $this->actingAs($this->pharmacy)->get(route('pharmacy.stock.index'))->assertOk()->assertDontSee('OTHER');
        $this->actingAs($this->pharmacy)
            ->post(route('pharmacy.stock.adjustments.store'), [
                'medicine_stock_id' => $stockB->id,
                'delta' => 1,
                'notes' => 'Hacked',
            ])
            ->assertInvalid(['medicine_stock_id']);
    }

    public function test_dentist_cannot_fulfill_prescription(): void
    {
        $this->stock('OK', now()->addMonths(2)->toDateString(), 20);
        $prescription = $this->sentPrescription(5);

        $this->actingAs($this->dentist)
            ->post(route('pharmacy.prescriptions.fulfill', $prescription))
            ->assertForbidden();
    }

    protected function stock(string $batch, string $expired, int $quantity): MedicineStock
    {
        return MedicineStock::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'medicine_id' => $this->medicineA->id,
            'batch_number' => $batch,
            'expired_date' => $expired,
            'quantity' => $quantity,
        ]);
    }

    protected function sentPrescription(int $quantity): Prescription
    {
        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Budi Santoso'])->id,
            'doctor_id' => $this->dentist->id,
            'payer_id' => $this->payerA->id,
        ]);

        $prescription = Prescription::factory()->sent()->create([
            'visit_id' => $visit->id,
            'doctor_id' => $this->dentist->id,
        ]);

        $prescription->items()->create([
            'medicine_id' => $this->medicineA->id,
            'dosage' => '1 kapsul',
            'quantity' => $quantity,
        ]);

        return $prescription->fresh('items');
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
