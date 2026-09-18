<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\SaasInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\SaasPackage;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Saas\SubscriptionService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasSubscriptionTest extends TestCase
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

    public function test_platform_admin_can_manage_packages_and_pages_are_not_placeholders(): void
    {
        $admin = $this->platformAdmin();
        $package = SaasPackage::factory()->create(['name' => 'Starter']);

        $this->actingAs($admin)->get(route('saas.packages.index'))
            ->assertOk()
            ->assertSee('Paket')
            ->assertDontSee('Coming Soon')
            ->assertSee('Starter');

        $this->actingAs($admin)->post(route('saas.packages.store'), [
            'name' => 'Klinik Plus',
            'price' => '199000',
            'interval' => 'monthly',
            'trial_days' => 7,
        ])->assertRedirect(route('saas.packages.index'));

        $this->assertDatabaseHas('saas_packages', ['slug' => 'klinik-plus']);

        $this->actingAs($admin)->get(route('saas.subscriptions.index'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($admin)->get(route('saas.invoices.index'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($admin)->get(route('saas.system.integrations'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($admin)->get(route('saas.system.audit'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($admin)->get(route('saas.users.activity'))->assertOk()->assertDontSee('Coming Soon');

        $owner = $this->tenantUser(RoleName::Owner, Tenant::factory()->create());
        $this->actingAs($owner)->get(route('saas.packages.index'))->assertForbidden();
        $this->actingAs($owner)->delete(route('saas.packages.destroy', $package))->assertForbidden();
    }

    public function test_paid_invoice_activates_tenant_and_expiry_suspends(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->trial()->create();
        $package = SaasPackage::factory()->create(['price' => '250000.00']);
        $service = app(SubscriptionService::class);

        $subscription = $service->start($tenant, $package, false);
        $invoice = $subscription->invoices()->first();

        $this->assertNotNull($invoice);
        $this->assertSame(SaasInvoiceStatus::Unpaid, $invoice->status);

        $this->actingAs($admin)
            ->post(route('saas.invoices.pay', $invoice))
            ->assertRedirect();

        $this->assertSame(SaasInvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);

        $subscription->update(['ends_at' => now()->subDay()]);
        $this->artisan('saas:expire-subscriptions')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Expired, $subscription->fresh()->status);
        $this->assertSame(TenantStatus::Suspended, $tenant->fresh()->status);

        $owner = $this->tenantUser(RoleName::Owner, $tenant, Branch::factory()->create(['tenant_id' => $tenant->id]));
        $this->actingAs($owner)
            ->post(route('registration.patients.store'), [
                'name' => 'Pasien Suspend',
                'branch_id' => $owner->branch_id,
            ])
            ->assertForbidden();
    }

    public function test_owner_cannot_see_other_tenant_subscription_via_saas_routes(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->tenantUser(RoleName::Owner, $tenant);

        $this->actingAs($owner)->get(route('saas.subscriptions.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('saas.invoices.index'))->assertForbidden();
    }

    protected function platformAdmin(): User
    {
        $user = User::factory()->create(['tenant_id' => null, 'branch_id' => null]);
        $user->assignRole(RoleName::SuperAdminSaas->value);

        return $user;
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
