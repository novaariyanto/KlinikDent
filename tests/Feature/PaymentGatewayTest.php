<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\SaasInvoiceStatus;
use App\Enums\TenantStatus;
use App\Models\Menu;
use App\Models\SaasInvoice;
use App\Models\SaasPackage;
use App\Models\SaasSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Saas\PaymentGatewaySettings;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
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

    public function test_super_admin_can_save_midtrans_settings(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('saas.payment-gateway.index'))
            ->assertOk()
            ->assertSee('Midtrans')
            ->assertSee('Payment Notification')
            ->assertDontSee('Coming Soon');

        $this->actingAs($admin)
            ->put(route('saas.payment-gateway.update'), [
                'enabled' => '1',
                'environment' => 'sandbox',
                'merchant_id' => 'G123456',
                'client_key' => 'SB-Mid-client-test',
                'server_key' => 'SB-Mid-server-test',
            ])
            ->assertRedirect();

        $this->assertTrue(PaymentGatewaySettings::isReady());
        $this->assertSame('sandbox', PaymentGatewaySettings::environment());
        $this->assertSame('SB-Mid-client-test', PaymentGatewaySettings::clientKey());
        $this->assertSame('SB-Mid-server-test', PaymentGatewaySettings::serverKey());
    }

    public function test_owner_cannot_access_payment_gateway_config(): void
    {
        $owner = $this->tenantUser(RoleName::Owner, Tenant::factory()->create());

        $this->actingAs($owner)->get(route('saas.payment-gateway.index'))->assertForbidden();
        $this->actingAs($owner)->put(route('saas.payment-gateway.update'), [
            'enabled' => '1',
            'environment' => 'sandbox',
            'client_key' => 'x',
            'server_key' => 'y',
        ])->assertForbidden();
    }

    public function test_owner_pays_package_via_midtrans_and_notification_activates(): void
    {
        $this->enableMidtrans();
        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-test',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-test',
            ], 201),
        ]);

        $tenant = Tenant::factory()->create();
        $owner = $this->tenantUser(RoleName::Owner, $tenant);
        $package = SaasPackage::factory()->create(['name' => 'Klinik Pro', 'price' => '350000.00']);

        $this->actingAs($owner)->post(route('subscription.subscribe', $package));
        $invoice = SaasInvoice::query()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($invoice);

        $this->actingAs($owner)
            ->get(route('subscription.invoices'))
            ->assertOk()
            ->assertSee('Bayar via Midtrans');

        $this->actingAs($owner)
            ->post(route('subscription.invoices.pay', $invoice))
            ->assertRedirect(route('subscription.invoices.checkout', $invoice));

        $invoice = $invoice->fresh();
        $this->assertSame('snap-token-test', $invoice->midtrans_snap_token);
        $this->assertSame(SaasInvoiceStatus::Unpaid, $invoice->status);

        $this->actingAs($owner)
            ->get(route('subscription.invoices.checkout', $invoice))
            ->assertOk()
            ->assertSee('snap-token-test')
            ->assertSee('Pembayaran Midtrans');

        $payload = $this->settlementPayload($invoice);

        $this->postJson(route('midtrans.notification'), $payload)
            ->assertOk();

        $this->assertSame(SaasInvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
        $this->assertSame('midtrans', $invoice->fresh()->payment_channel);
    }

    public function test_invalid_midtrans_signature_is_rejected(): void
    {
        $this->enableMidtrans();
        $tenant = Tenant::factory()->create();
        $package = SaasPackage::factory()->create();
        $subscription = SaasSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
        ]);
        $invoice = SaasInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'midtrans_order_id' => 'SI-FAKE-0001',
        ]);

        $this->postJson(route('midtrans.notification'), [
            'order_id' => 'SI-FAKE-0001',
            'status_code' => '200',
            'gross_amount' => '250000.00',
            'transaction_status' => 'settlement',
            'signature_key' => 'invalid',
        ])->assertForbidden();

        $this->assertSame(SaasInvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_owner_cannot_checkout_other_tenant_invoice(): void
    {
        $this->enableMidtrans();
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $owner = $this->tenantUser(RoleName::Owner, $tenant);
        $package = SaasPackage::factory()->create();
        $subscription = SaasSubscription::factory()->create([
            'tenant_id' => $other->id,
            'package_id' => $package->id,
        ]);
        $foreign = SaasInvoice::factory()->create([
            'tenant_id' => $other->id,
            'subscription_id' => $subscription->id,
            'midtrans_snap_token' => 'foreign-token',
        ]);

        $this->actingAs($owner)
            ->get(route('subscription.invoices.checkout', $foreign))
            ->assertForbidden();
    }

    protected function enableMidtrans(): void
    {
        PaymentGatewaySettings::save([
            'enabled' => true,
            'environment' => 'sandbox',
            'merchant_id' => 'G123456',
            'client_key' => 'SB-Mid-client-test',
            'server_key' => 'SB-Mid-server-test',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function settlementPayload(SaasInvoice $invoice): array
    {
        $payload = [
            'order_id' => (string) $invoice->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => number_format((float) $invoice->amount, 2, '.', ''),
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'transaction_id' => 'trx-test-1',
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'SB-Mid-server-test'
        );

        return $payload;
    }

    protected function platformAdmin(): User
    {
        $user = User::factory()->create(['tenant_id' => null, 'branch_id' => null]);
        $user->assignRole(RoleName::SuperAdminSaas->value);

        return $user;
    }

    protected function tenantUser(RoleName $role, Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole($role->value);

        return $user;
    }
}
