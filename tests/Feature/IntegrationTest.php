<?php

namespace Tests\Feature;

use App\Enums\BpjsMembershipStatus;
use App\Enums\InsuranceClaimStatus;
use App\Enums\IntegrationProvider;
use App\Enums\PayerType;
use App\Enums\RoleName;
use App\Enums\SatuSehatStatus;
use App\Enums\VisitStatus;
use App\Models\Branch;
use App\Models\InsuranceClaim;
use App\Models\IntegrationLog;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Branch $branchA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            MenuSeeder::class,
        ]);
        Menu::clearCache();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->branchA = Branch::factory()->create(['tenant_id' => $this->tenantA->id]);
    }

    public function test_satusehat_and_bpjs_sandbox_update_status_and_redact_nik(): void
    {
        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'nik' => '1234567890123456',
        ]);
        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $patient->id,
            'payer_id' => Payer::factory()->create(['tenant_id' => $this->tenantA->id]),
            'status' => VisitStatus::Done,
        ]);

        $this->actingAs($owner)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertDontSee('Coming Soon')
            ->assertSee('SATUSEHAT')
            ->assertSee('BPJS VClaim');

        $this->actingAs($owner)
            ->get(route('integrations.satusehat'))
            ->assertOk()
            ->assertSee('Modul SATUSEHAT');

        $this->actingAs($owner)
            ->get(route('integrations.bpjs'))
            ->assertOk()
            ->assertSee('Modul BPJS');

        $this->actingAs($owner)
            ->post(route('integrations.satusehat.send'), ['visit_id' => $visit->id])
            ->assertRedirect();

        $this->assertSame(SatuSehatStatus::Synced, $visit->fresh()->satusehat_status);
        $this->assertNotEmpty($visit->fresh()->satusehat_id);

        $this->actingAs($owner)
            ->post(route('integrations.bpjs.check'), ['patient_id' => $patient->id])
            ->assertRedirect();

        $this->assertSame(BpjsMembershipStatus::Active, $patient->fresh()->bpjs_status);
        $this->assertNotNull($patient->fresh()->bpjs_checked_at);

        $log = IntegrationLog::query()->where('provider', 'satusehat')->first();
        $this->assertNotNull($log);
        $encoded = json_encode($log->request_payload);
        $this->assertStringNotContainsString('1234567890123456', (string) $encoded);
    }

    public function test_insurance_claim_upload_is_tenant_isolated(): void
    {
        Storage::fake('local');
        $ownerA = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        $ownerB = $this->tenantUser(RoleName::Owner, $this->tenantB, Branch::factory()->create(['tenant_id' => $this->tenantB->id]));
        $patientA = Patient::factory()->create(['tenant_id' => $this->tenantA->id]);
        $payer = Payer::factory()->create(['tenant_id' => $this->tenantA->id, 'type' => PayerType::Asuransi]);

        $this->actingAs($ownerA)
            ->post(route('integrations.claims.store'), [
                'patient_id' => $patientA->id,
                'payer_id' => $payer->id,
                'amount' => '150000',
                'document' => UploadedFile::fake()->create('klaim.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('integrations.bpjs'));

        $claim = InsuranceClaim::query()->first();
        $this->assertNotNull($claim);
        $this->assertSame(InsuranceClaimStatus::Submitted, $claim->status);
        $this->assertSame($this->tenantA->id, $claim->tenant_id);

        $this->actingAs($ownerB)
            ->get(route('integrations.claims.download', $claim))
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->get(route('integrations.claims.download', $claim))
            ->assertOk();
    }

    public function test_owner_saves_credentials_isolated_per_clinic(): void
    {
        $ownerA = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        $ownerB = $this->tenantUser(RoleName::Owner, $this->tenantB, Branch::factory()->create(['tenant_id' => $this->tenantB->id]));

        $this->actingAs($ownerA)
            ->put(route('integrations.satusehat.update'), [
                'enabled' => '1',
                'fake' => '1',
                'client_id' => 'clinic-a-client',
                'client_secret' => 'clinic-a-secret',
                'organization_id' => 'org-a',
                'base_url' => 'https://api-satusehat-stg.dto.kemkes.go.id',
                'token_url' => 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken',
            ])
            ->assertRedirect();

        $saved = TenantIntegration::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)
            ->where('provider', IntegrationProvider::SatuSehat)
            ->first();

        $this->assertNotNull($saved);
        $this->assertSame('clinic-a-client', $saved->credential('client_id'));
        $this->assertSame('clinic-a-secret', $saved->credential('client_secret'));

        $this->actingAs($ownerB)
            ->get(route('integrations.satusehat'))
            ->assertOk()
            ->assertDontSee('clinic-a-client')
            ->assertDontSee('clinic-a-secret');

        $this->actingAs($ownerB);
        $this->assertSame(0, TenantIntegration::query()->where('tenant_id', $this->tenantA->id)->count());

        $this->actingAs($ownerB)
            ->put(route('integrations.bpjs.update'), [
                'enabled' => '1',
                'fake' => '0',
                'cons_id' => 'clinic-b-cons',
                'secret_key' => 'clinic-b-secret',
                'user_key' => 'clinic-b-user',
                'base_url' => 'https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-rest-dev',
            ])
            ->assertRedirect();

        $this->actingAs($ownerA)
            ->get(route('integrations.bpjs'))
            ->assertOk()
            ->assertDontSee('clinic-b-cons');
    }

    public function test_disabled_satusehat_module_cannot_send(): void
    {
        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        TenantIntegration::query()->create([
            'tenant_id' => $this->tenantA->id,
            'provider' => IntegrationProvider::SatuSehat,
            'enabled' => false,
            'fake' => true,
            'credentials' => [],
        ]);

        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenantA->id])->id,
            'payer_id' => Payer::factory()->create(['tenant_id' => $this->tenantA->id])->id,
            'status' => VisitStatus::Done,
        ]);

        $this->actingAs($owner)
            ->from(route('integrations.satusehat'))
            ->post(route('integrations.satusehat.send'), ['visit_id' => $visit->id])
            ->assertRedirect(route('integrations.satusehat'))
            ->assertSessionHas('error');

        $this->assertNotSame(SatuSehatStatus::Synced, $visit->fresh()->satusehat_status);
        $this->assertSame(0, IntegrationLog::query()->count());
    }

    public function test_staff_can_open_own_module_but_cannot_change_credentials(): void
    {
        $manager = $this->tenantUser(RoleName::Manager, $this->tenantA, $this->branchA);
        $registration = $this->tenantUser(RoleName::Registration, $this->tenantA, $this->branchA);
        $dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);

        $this->actingAs($manager)
            ->get(route('integrations.satusehat'))
            ->assertOk();
        $this->actingAs($manager)
            ->put(route('integrations.satusehat.update'), [
                'enabled' => '0',
                'fake' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($registration)->get(route('integrations.bpjs'))->assertOk();
        $this->actingAs($registration)->get(route('integrations.satusehat'))->assertForbidden();

        $this->actingAs($dentist)->get(route('integrations.satusehat'))->assertOk();
        $this->actingAs($dentist)->get(route('integrations.bpjs'))->assertForbidden();
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
