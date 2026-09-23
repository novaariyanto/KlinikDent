<?php

namespace Tests\Feature;

use App\Enums\OnboardingStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Models\Menu;
use App\Models\SaasPackage;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRegisterTest extends TestCase
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

    public function test_guest_can_view_register_form(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Daftarkan klinik Anda')
            ->assertDontSee('name="subdomain"', false);
    }

    public function test_guest_can_register_clinic_and_enters_onboarding(): void
    {
        $package = SaasPackage::factory()->trial()->create();

        $this->post(route('register.store'), [
            'name' => 'Klinik Senyum',
            'subdomain' => 'klinik-senyum',
            'owner_name' => 'Budi Owner',
            'owner_email' => 'budi@klinik.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'package_id' => $package->id,
            'status' => TenantStatus::Active->value,
        ])->assertRedirect(route('onboarding.show'));

        $this->assertAuthenticated();

        $tenant = Tenant::query()->where('subdomain', 'klinik-senyum')->first();
        $this->assertNotNull($tenant);
        $this->assertSame(TenantStatus::Trial, $tenant->status);
        $this->assertSame(OnboardingStatus::Pending, $tenant->onboarding_status);
        $this->assertSame($package->id, $tenant->plan_id);

        $owner = User::query()->where('email', 'budi@klinik.test')->first();
        $this->assertNotNull($owner);
        $this->assertTrue($owner->hasRole(RoleName::Owner));
        $this->assertFalse($owner->hasRole(RoleName::SuperAdminSaas));
        $this->assertSame($tenant->id, $owner->tenant_id);

        $this->get(route('onboarding.show'))->assertOk()->assertSee('Pilih tipe klinik Anda');
        $this->get(route('dashboard'))->assertRedirect(route('onboarding.show'));
    }

    public function test_register_uses_trial_package_when_not_selected(): void
    {
        $trial = SaasPackage::factory()->trial()->create();

        $this->post(route('register.store'), [
            'name' => 'Klinik Default',
            'subdomain' => 'klinik-default',
            'owner_name' => 'Sari',
            'owner_email' => 'sari@klinik.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('onboarding.show'));

        $tenant = Tenant::query()->where('subdomain', 'klinik-default')->first();
        $this->assertNotNull($tenant);
        $this->assertSame($trial->id, $tenant->plan_id);
    }

    public function test_register_rejects_duplicate_email_and_generates_unique_subdomain(): void
    {
        $existing = Tenant::factory()->pendingOnboarding()->create(['subdomain' => 'klinik-ada']);
        User::factory()->create([
            'email' => 'ada@klinik.test',
            'tenant_id' => $existing->id,
        ]);

        $this->post(route('register.store'), [
            'name' => 'Klinik Ada',
            'owner_name' => 'Baru',
            'owner_email' => 'baru@klinik.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('onboarding.show'));

        $this->assertNotNull(Tenant::query()->where('subdomain', 'klinik-ada-2')->first());

        $this->post(route('logout'));

        $this->post(route('register.store'), [
            'name' => 'Klinik Lain',
            'owner_name' => 'Ada',
            'owner_email' => 'ada@klinik.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('owner_email');
    }

    public function test_authenticated_user_cannot_open_register(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);
        $user->assignRole(RoleName::SuperAdminSaas->value);

        $this->actingAs($user)
            ->get(route('register'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_login_page_links_to_register(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('register'), false);
    }
}
