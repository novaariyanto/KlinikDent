<?php

namespace Tests\Feature;

use App\Enums\OnboardingStatus;
use App\Enums\OnboardingTemplate;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerOnboardingTest extends TestCase
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

    public function test_pending_owner_is_redirected_to_onboarding_and_others_are_not(): void
    {
        [$tenant, $owner] = $this->pendingOwner();
        $cashier = $this->tenantUser(RoleName::Cashier, $tenant);
        $saas = $this->platformAdmin();

        $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('onboarding.show'));
        $this->actingAs($owner)->get(route('onboarding.show'))->assertOk()->assertSee('Pilih tipe klinik Anda');

        $this->actingAs($cashier)->get(route('dashboard'))->assertOk();
        $this->actingAs($cashier)->get(route('onboarding.show'))->assertForbidden();

        $this->actingAs($saas)->get(route('dashboard'))->assertOk();
        $this->actingAs($saas)->get(route('onboarding.show'))->assertForbidden();
    }

    public function test_existing_owner_skips_onboarding(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->tenantUser(RoleName::Owner, $tenant);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        $this->actingAs($owner)->get(route('onboarding.show'))->assertRedirect(route('dashboard'));
    }

    public function test_small_clinic_preset_multi_role_staff_and_completion(): void
    {
        [, $owner] = $this->pendingOwner();

        $this->actingAs($owner)
            ->post(route('onboarding.step', 'template'), ['template' => OnboardingTemplate::Small->value])
            ->assertRedirect(route('onboarding.show'));

        $this->actingAs($owner)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('Role yang disarankan')
            ->assertSee('Dokter Gigi')
            ->assertSee('Pendaftaran')
            ->assertSee('Kasir')
            ->assertSee('Farmasi');

        $this->actingAs($owner)
            ->post(route('onboarding.step', 'roles'), [
                'roles' => [
                    RoleName::Owner->value,
                    RoleName::Dentist->value,
                    RoleName::Registration->value,
                    RoleName::Cashier->value,
                    RoleName::Pharmacy->value,
                ],
            ])
            ->assertRedirect(route('onboarding.show'));

        $this->actingAs($owner)
            ->post(route('onboarding.step', 'profile'), [
                'clinic_name' => 'Klinik Senyum',
                'address' => 'Jl. Melati 1',
                'phone' => '08123456789',
                'rm_prefix' => 'SENYUM',
            ])
            ->assertRedirect(route('onboarding.show'));

        $this->actingAs($owner)
            ->post(route('onboarding.step', 'branch'), [
                'name' => 'Cabang Utama',
                'address' => 'Jl. Melati 1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('onboarding.show'));

        $this->actingAs($owner)
            ->post(route('onboarding.skip', 'doctors'))
            ->assertRedirect(route('onboarding.show'));

        $this->actingAs($owner)
            ->post(route('onboarding.step', 'staff'), [
                'staff' => [[
                    'name' => 'Siti',
                    'email' => 'siti@klinik.test',
                    'password' => 'password',
                    'roles' => [
                        RoleName::Registration->value,
                        RoleName::Cashier->value,
                        RoleName::Pharmacy->value,
                    ],
                ]],
            ])
            ->assertRedirect(route('onboarding.show'));

        $siti = User::query()->where('email', 'siti@klinik.test')->first();
        $this->assertNotNull($siti);
        $this->assertEqualsCanonicalizing(
            [RoleName::Registration->value, RoleName::Cashier->value, RoleName::Pharmacy->value],
            $siti->getRoleNames()->all()
        );

        $this->actingAs($owner)
            ->post(route('onboarding.step', 'services'), ['mode' => 'template'])
            ->assertRedirect(route('onboarding.show'));

        $this->assertGreaterThanOrEqual(6, Service::query()->where('tenant_id', $owner->tenant_id)->count());

        $this->actingAs($owner)->post(route('onboarding.skip', 'medicines'))->assertRedirect();
        $this->actingAs($owner)->post(route('onboarding.skip', 'integrations'))->assertRedirect();

        $this->actingAs($owner)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('Klinik Kecil')
            ->assertSee('Selesaikan Setup Klinik');

        $this->actingAs($owner)
            ->post(route('onboarding.complete'))
            ->assertRedirect(route('onboarding.done'));

        $owner->tenant->refresh();
        $this->assertSame(OnboardingStatus::Completed, $owner->tenant->onboarding_status);
        $this->assertNotNull($owner->tenant->onboarding_completed_at);

        $this->actingAs($owner)->get(route('onboarding.done'))->assertOk()->assertSee('Klinik Anda siap digunakan');
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Setup Klinik');
        $this->actingAs($owner)->get(route('onboarding.show'))->assertRedirect(route('dashboard'));
    }

    public function test_medium_clinic_preset_includes_all_tenant_roles(): void
    {
        [, $owner] = $this->pendingOwner();

        $this->actingAs($owner)
            ->post(route('onboarding.step', 'template'), ['template' => OnboardingTemplate::Medium->value])
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('Manager / Admin Klinik')
            ->assertSee('Asisten Dokter')
            ->assertSee('Perawat')
            ->assertSee('Keuangan')
            ->assertSee('Auditor / Viewer');

        $owner->tenant->refresh();
        $this->assertSame(OnboardingTemplate::Medium, $owner->tenant->onboarding_template);
        $this->assertEqualsCanonicalizing(RoleName::tenantValues(), $owner->tenant->onboarding_selected_roles);
    }

    public function test_owner_can_resume_onboarding_after_logout(): void
    {
        [, $owner] = $this->pendingOwner();

        $this->actingAs($owner)
            ->post(route('onboarding.step', 'template'), ['template' => OnboardingTemplate::Small->value]);

        $owner->tenant->refresh();
        $this->assertSame(OnboardingStatus::InProgress, $owner->tenant->onboarding_status);
        $this->assertSame(2, (int) $owner->tenant->onboarding_current_step);

        $this->actingAs($owner)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('Lanjutkan setup klinik Anda')
            ->assertSee('Langkah 2 dari 10');
    }

    public function test_optional_steps_can_be_skipped_and_complete_requires_profile_and_branch(): void
    {
        [, $owner] = $this->pendingOwner();

        $this->actingAs($owner)->post(route('onboarding.complete'))->assertSessionHasErrors();

        $this->actingAs($owner)->post(route('onboarding.step', 'template'), ['template' => OnboardingTemplate::Small->value]);
        $this->actingAs($owner)->post(route('onboarding.step', 'roles'), ['roles' => [RoleName::Owner->value]]);
        $this->actingAs($owner)->post(route('onboarding.step', 'profile'), [
            'clinic_name' => 'Klinik Ringkas',
            'address' => 'Jl. Kenanga 2',
        ]);
        $this->actingAs($owner)->post(route('onboarding.step', 'branch'), [
            'name' => 'Cabang Utama',
            'is_active' => '1',
        ]);

        foreach (['doctors', 'staff', 'services', 'medicines', 'integrations'] as $step) {
            $this->actingAs($owner)->post(route('onboarding.skip', $step))->assertRedirect(route('onboarding.show'));
        }

        $this->actingAs($owner)->post(route('onboarding.complete'))->assertRedirect(route('onboarding.done'));
        $this->assertSame(0, Service::query()->where('tenant_id', $owner->tenant_id)->count());
    }

    public function test_integrations_default_to_skip_and_do_not_block_completion(): void
    {
        [, $owner] = $this->pendingOwner('Klinik Integrasi', 'klinik-integrasi');

        $this->actingAs($owner)->post(route('onboarding.step', 'template'), ['template' => OnboardingTemplate::Small->value]);
        $this->actingAs($owner)->post(route('onboarding.step', 'roles'), ['roles' => [RoleName::Owner->value]]);
        $this->actingAs($owner)->post(route('onboarding.step', 'profile'), [
            'clinic_name' => 'Klinik Integrasi',
            'address' => 'Jl. Opsional 1',
        ]);
        $this->actingAs($owner)->post(route('onboarding.step', 'branch'), [
            'name' => 'Cabang Utama',
            'is_active' => '1',
        ]);

        foreach (['doctors', 'staff', 'services', 'medicines'] as $step) {
            $this->actingAs($owner)->post(route('onboarding.skip', $step));
        }

        $this->actingAs($owner)
            ->post(route('onboarding.step', 'integrations'), [])
            ->assertRedirect(route('onboarding.show'));

        $this->actingAs($owner)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('Dilewati (opsional)')
            ->assertSee('Selesaikan Setup Klinik');

        $this->actingAs($owner)->post(route('onboarding.complete'))->assertRedirect(route('onboarding.done'));
    }

    public function test_onboarding_data_is_isolated_per_tenant(): void
    {
        [, $ownerA] = $this->pendingOwner('Klinik A', 'klinik-a-onboard');
        [$tenantB, $ownerB] = $this->pendingOwner('Klinik B', 'klinik-b-onboard');

        $this->actingAs($ownerA)->post(route('onboarding.step', 'template'), ['template' => OnboardingTemplate::Small->value]);
        $this->actingAs($ownerA)->post(route('onboarding.step', 'roles'), ['roles' => [RoleName::Owner->value]]);
        $this->actingAs($ownerA)->post(route('onboarding.step', 'profile'), [
            'clinic_name' => 'Klinik A',
            'address' => 'Alamat A',
        ]);
        $this->actingAs($ownerA)->post(route('onboarding.step', 'branch'), [
            'name' => 'Cabang A',
            'is_active' => '1',
        ]);
        $this->actingAs($ownerA)->post(route('onboarding.skip', 'doctors'));
        $this->actingAs($ownerA)->post(route('onboarding.skip', 'staff'));
        $this->actingAs($ownerA)->post(route('onboarding.step', 'services'), ['mode' => 'template']);

        $this->assertSame(0, Service::query()->where('tenant_id', $tenantB->id)->count());
        $this->assertGreaterThan(0, Service::query()->where('tenant_id', $ownerA->tenant_id)->count());

        $this->actingAs($ownerB)->get(route('onboarding.show'))->assertOk()->assertSee('Pilih tipe klinik Anda');
        $this->actingAs($ownerB)->get(route('onboarding.show'))->assertDontSee('Selesaikan Setup Klinik');
    }

    public function test_saas_tenant_create_makes_pending_owner_account(): void
    {
        $saas = $this->platformAdmin();

        $this->actingAs($saas)
            ->post(route('saas.tenants.store'), [
                'name' => 'Klinik Baru Onboard',
                'subdomain' => 'klinik-baru-onboard',
                'status' => TenantStatus::Trial->value,
                'owner_name' => 'Owner Baru',
                'owner_email' => 'owner.onboard@klinik.test',
                'owner_password' => 'password',
                'owner_password_confirmation' => 'password',
            ])
            ->assertRedirect();

        $tenant = Tenant::query()->where('subdomain', 'klinik-baru-onboard')->first();
        $this->assertNotNull($tenant);
        $this->assertSame(OnboardingStatus::Pending, $tenant->onboarding_status);

        $owner = User::query()->where('email', 'owner.onboard@klinik.test')->first();
        $this->assertNotNull($owner);
        $this->assertTrue($owner->hasRole(RoleName::Owner));

        $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('onboarding.show'));
    }

    public function test_cannot_assign_saas_role_during_onboarding(): void
    {
        [, $owner] = $this->pendingOwner();

        $this->actingAs($owner)->post(route('onboarding.step', 'template'), ['template' => OnboardingTemplate::Small->value]);
        $this->actingAs($owner)->post(route('onboarding.step', 'roles'), ['roles' => [RoleName::Owner->value]]);
        $this->actingAs($owner)->post(route('onboarding.step', 'profile'), [
            'clinic_name' => 'Klinik Role',
            'address' => 'Alamat',
        ]);
        $this->actingAs($owner)->post(route('onboarding.step', 'branch'), [
            'name' => 'Cabang Utama',
            'is_active' => '1',
        ]);
        $this->actingAs($owner)->post(route('onboarding.skip', 'doctors'));

        $this->actingAs($owner)
            ->post(route('onboarding.step', 'staff'), [
                'staff' => [[
                    'name' => 'Hacker',
                    'email' => 'hack@klinik.test',
                    'password' => 'password',
                    'roles' => [RoleName::SuperAdminSaas->value],
                ]],
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('users', ['email' => 'hack@klinik.test']);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    protected function pendingOwner(string $name = 'Klinik Onboard', string $subdomain = 'klinik-onboard'): array
    {
        $tenant = Tenant::factory()->pendingOnboarding()->create([
            'name' => $name,
            'subdomain' => $subdomain,
        ]);
        $owner = $this->tenantUser(RoleName::Owner, $tenant);

        return [$tenant, $owner];
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

    protected function platformAdmin(): User
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'branch_id' => null,
        ]);
        $user->assignRole(RoleName::SuperAdminSaas->value);

        return $user;
    }
}
