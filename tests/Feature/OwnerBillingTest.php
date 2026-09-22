<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\SaasInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Menu;
use App\Models\SaasInvoice;
use App\Models\SaasPackage;
use App\Models\SaasSubscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerBillingTest extends TestCase
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

    public function test_owner_can_view_current_package_and_subscribe_then_pay(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->tenantUser(RoleName::Owner, $tenant);
        $package = SaasPackage::factory()->create([
            'name' => 'Klinik Pro',
            'price' => '350000.00',
        ]);

        $this->actingAs($owner)
            ->get(route('subscription.index'))
            ->assertOk()
            ->assertDontSee('Coming Soon')
            ->assertSee('Paket Langganan')
            ->assertSee('Klinik Pro');

        $this->actingAs($owner)
            ->post(route('subscription.subscribe', $package))
            ->assertRedirect(route('subscription.invoices'));

        $invoice = SaasInvoice::query()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(SaasInvoiceStatus::Unpaid, $invoice->status);
        $this->assertSame((int) $package->id, (int) $tenant->fresh()->plan_id);

        $this->actingAs($owner)
            ->get(route('subscription.invoices'))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('Bayar sekarang');

        $this->actingAs($owner)
            ->post(route('subscription.invoices.pay', $invoice))
            ->assertRedirect(route('subscription.invoices'));

        $this->assertSame(SaasInvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $invoice->subscription->fresh()->status);
    }

    public function test_owner_cannot_see_or_pay_other_tenant_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $owner = $this->tenantUser(RoleName::Owner, $tenant);
        $package = SaasPackage::factory()->create(['name' => 'Paket Lain']);
        $subscription = SaasSubscription::factory()->create([
            'tenant_id' => $other->id,
            'package_id' => $package->id,
        ]);
        $foreign = SaasInvoice::factory()->create([
            'tenant_id' => $other->id,
            'subscription_id' => $subscription->id,
            'number' => 'SI-FOREIGN-0001',
        ]);

        $this->actingAs($owner)
            ->get(route('subscription.invoices'))
            ->assertOk()
            ->assertDontSee('SI-FOREIGN-0001')
            ->assertDontSee('Paket Lain');

        $this->actingAs($owner)
            ->post(route('subscription.invoices.pay', $foreign))
            ->assertForbidden();

        $this->assertSame(SaasInvoiceStatus::Unpaid, $foreign->fresh()->status);
    }

    public function test_cashier_cannot_access_owner_plan_module(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = $this->tenantUser(RoleName::Cashier, $tenant);
        $package = SaasPackage::factory()->create();

        $this->actingAs($cashier)->get(route('subscription.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('subscription.invoices'))->assertForbidden();
        $this->actingAs($cashier)->post(route('subscription.subscribe', $package))->assertForbidden();
    }

    public function test_suspended_owner_can_pay_invoice_to_reactivate(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $owner = $this->tenantUser(RoleName::Owner, $tenant);
        $package = SaasPackage::factory()->create(['name' => 'Pulihkan']);

        $this->actingAs($owner)
            ->post(route('subscription.subscribe', $package))
            ->assertRedirect(route('subscription.invoices'));

        $this->assertSame(TenantStatus::Suspended, $tenant->fresh()->status);

        $invoice = SaasInvoice::query()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($invoice);

        $this->actingAs($owner)
            ->post(route('subscription.invoices.pay', $invoice))
            ->assertRedirect(route('subscription.invoices'));

        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
        $this->assertSame(SaasInvoiceStatus::Paid, $invoice->fresh()->status);
    }

    protected function tenantUser(RoleName $role, Tenant $tenant): User
    {
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $user->assignRole($role->value);

        return $user;
    }
}
