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
use App\Models\BranchSatuSehatSetting;
use App\Models\Diagnosis;
use App\Models\Doctor;
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
use Illuminate\Support\Facades\Http;
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

    public function test_satusehat_and_bpjs_sandbox_update_status_and_store_exchange(): void
    {
        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'nik' => '1234567890123456',
        ]);
        $doctorUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
        ]);
        Doctor::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $doctorUser->id,
            'nik' => '3174010101010001',
        ]);
        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'payer_id' => Payer::factory()->create(['tenant_id' => $this->tenantA->id]),
            'status' => VisitStatus::Done,
        ]);
        $this->readyBranch($this->branchA);

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
        $encoded = json_encode([$log->request_payload, $log->response_payload]);
        $this->assertNotEmpty($log->request_payload['calls'] ?? null);
        $this->assertNotEmpty($log->response_payload['calls'] ?? null);
        $this->assertStringNotContainsString('1234567890123456', (string) $encoded);
        $this->assertStringContainsString('********', (string) $encoded);
        $this->assertStringNotContainsString('secret-', (string) $encoded);
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
                'branch_id' => $this->branchA->id,
                'environment' => 'sandbox',
                'client_id' => 'clinic-a-client',
                'client_secret' => 'clinic-a-secret',
                'organization_id' => 'org-a',
            ])
            ->assertRedirect();

        $saved = BranchSatuSehatSetting::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $this->branchA->id)
            ->first();

        $this->assertNotNull($saved);
        $this->assertSame('clinic-a-client', $saved->client_id);
        $this->assertSame('clinic-a-secret', $saved->client_secret);
        $this->assertFalse($saved->connection_ok);

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

    public function test_satusehat_http_sync_uses_the_serving_branch_and_official_sandbox(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken*' => Http::response([
                'access_token' => 'token-1',
                'expires_in' => 3600,
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Patient*' => Http::response([
                'resourceType' => 'Bundle',
                'entry' => [['resource' => ['resourceType' => 'Patient', 'id' => 'patient-ihs']]],
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Practitioner*' => Http::response([
                'resourceType' => 'Bundle',
                'entry' => [['resource' => ['resourceType' => 'Practitioner', 'id' => 'practitioner-ihs']]],
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Location/*' => function ($request) {
                $id = basename((string) parse_url($request->url(), PHP_URL_PATH));

                return Http::response([
                    'resourceType' => 'Location',
                    'id' => $id,
                    'status' => 'active',
                    'name' => 'Poli Gigi',
                    'mode' => 'instance',
                ], $request->method() === 'POST' ? 201 : 200);
            },
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Condition' => Http::response([
                'resourceType' => 'Condition',
                'id' => 'condition-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Observation' => Http::response([
                'resourceType' => 'Observation',
                'id' => 'observation-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Encounter' => Http::response([
                'resourceType' => 'Encounter',
                'id' => 'encounter-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Encounter/*' => Http::response([
                'resourceType' => 'Encounter',
                'id' => 'encounter-1',
            ]),
        ]);

        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        $setting = $this->readyBranch($this->branchA, false);
        $doctorUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
        ]);
        Doctor::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $doctorUser->id,
            'nik' => '3174010101010002',
        ]);
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'nik' => '1234567890123456',
        ]);
        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'payer_id' => Payer::factory()->create(['tenant_id' => $this->tenantA->id])->id,
            'status' => VisitStatus::Done,
        ]);
        Diagnosis::factory()->create([
            'visit_id' => $visit->id,
            'code' => 'K02.1',
            'description' => 'Karies dentin',
        ]);

        $this->actingAs($owner)
            ->post(route('integrations.satusehat.send'), ['visit_id' => $visit->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('encounter-1', $visit->fresh()->satusehat_id);
        $this->assertSame(SatuSehatStatus::Synced, $visit->fresh()->satusehat_status);
        $this->assertSame('patient-ihs', $patient->fresh()->satusehat_id);

        Http::assertSent(function ($request) use ($setting) {
            return $request->method() === 'PUT'
                && str_ends_with($request->url(), '/Location/'.$setting->location_id)
                && ($request['id'] ?? null) === $setting->location_id
                && ($request['managingOrganization']['reference'] ?? null) === 'Organization/'.$setting->organization_id;
        });
        Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/Location'));
        Http::assertSent(function ($request) use ($setting) {
            return $request->method() === 'PUT'
                && ($request['status'] ?? null) === 'finished'
                && ($request['class']['code'] ?? null) === 'AMB'
                && ($request['serviceProvider']['reference'] ?? null) === 'Organization/'.$setting->organization_id
                && ($request['location'][0]['location']['reference'] ?? null) === 'Location/'.$setting->location_id;
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api-satusehat.kemkes.go.id'));

        $log = IntegrationLog::query()->where('provider', 'satusehat')->first();
        $encoded = json_encode([$log?->request_payload, $log?->response_payload]);
        $this->assertNotEmpty($log?->request_payload['calls'] ?? null);
        $this->assertNotEmpty($log?->response_payload['calls'] ?? null);
        $this->assertStringNotContainsString('1234567890123456', (string) $encoded);
        $this->assertStringContainsString('********', (string) $encoded);
        $this->assertStringContainsString('encounter-1', (string) $encoded);
        $this->assertStringNotContainsString('secret-', (string) $encoded);
        $this->assertStringNotContainsString('token-1', (string) $encoded);
    }

    public function test_satusehat_partial_when_observation_rejected(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken*' => Http::response([
                'access_token' => 'token-1',
                'expires_in' => 3600,
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Patient*' => Http::response([
                'resourceType' => 'Bundle',
                'entry' => [['resource' => ['resourceType' => 'Patient', 'id' => 'patient-ihs']]],
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Practitioner*' => Http::response([
                'resourceType' => 'Bundle',
                'entry' => [['resource' => ['resourceType' => 'Practitioner', 'id' => 'practitioner-ihs']]],
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Location/*' => Http::response([
                'resourceType' => 'Location',
                'id' => 'loc-x',
                'status' => 'active',
                'name' => 'Poli Gigi',
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Condition' => Http::response([
                'resourceType' => 'Condition',
                'id' => 'condition-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Observation' => Http::response([
                'resourceType' => 'OperationOutcome',
                'issue' => [['severity' => 'error', 'details' => ['text' => 'Element not found: Observation.issued']]],
            ], 400),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Encounter' => Http::response([
                'resourceType' => 'Encounter',
                'id' => 'encounter-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Encounter/*' => Http::response([
                'resourceType' => 'Encounter',
                'id' => 'encounter-1',
            ]),
        ]);

        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        $this->readyBranch($this->branchA, false);
        $doctorUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
        ]);
        Doctor::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $doctorUser->id,
            'nik' => '3174010101010003',
        ]);
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'nik' => '1234567890123457',
        ]);
        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'payer_id' => Payer::factory()->create(['tenant_id' => $this->tenantA->id])->id,
            'status' => VisitStatus::Done,
            'arrived_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ]);
        Diagnosis::factory()->create([
            'visit_id' => $visit->id,
            'code' => 'K02.1',
            'description' => 'Karies dentin',
        ]);

        $this->actingAs($owner)
            ->post(route('integrations.satusehat.send'), ['visit_id' => $visit->id])
            ->assertRedirect();

        $this->assertSame(SatuSehatStatus::Partial, $visit->fresh()->satusehat_status);
        Http::assertNotSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/Encounter/')
            && ($request['status'] ?? null) === 'finished');
    }

    public function test_satusehat_encounter_uses_arrived_and_completed_timestamps(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken*' => Http::response([
                'access_token' => 'token-1',
                'expires_in' => 3600,
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Patient*' => Http::response([
                'resourceType' => 'Bundle',
                'entry' => [['resource' => ['resourceType' => 'Patient', 'id' => 'patient-ihs']]],
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Practitioner*' => Http::response([
                'resourceType' => 'Bundle',
                'entry' => [['resource' => ['resourceType' => 'Practitioner', 'id' => 'practitioner-ihs']]],
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Location/*' => Http::response([
                'resourceType' => 'Location',
                'id' => 'loc-y',
                'status' => 'active',
                'name' => 'Poli Gigi',
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Condition' => Http::response([
                'resourceType' => 'Condition',
                'id' => 'condition-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Observation' => Http::response([
                'resourceType' => 'Observation',
                'id' => 'observation-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Encounter' => Http::response([
                'resourceType' => 'Encounter',
                'id' => 'encounter-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Encounter/*' => Http::response([
                'resourceType' => 'Encounter',
                'id' => 'encounter-1',
            ]),
        ]);

        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        $this->readyBranch($this->branchA, false);
        $doctorUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
        ]);
        Doctor::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $doctorUser->id,
            'nik' => '3174010101010004',
        ]);
        $arrived = now('Asia/Jakarta')->setTime(10, 15, 0);
        $completed = now('Asia/Jakarta')->setTime(11, 45, 0);
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'nik' => '1234567890123458',
        ]);
        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'payer_id' => Payer::factory()->create(['tenant_id' => $this->tenantA->id])->id,
            'status' => VisitStatus::Done,
            'arrived_at' => $arrived,
            'completed_at' => $completed,
        ]);
        Diagnosis::factory()->create([
            'visit_id' => $visit->id,
            'code' => 'K02.1',
            'description' => 'Karies dentin',
        ]);

        $this->actingAs($owner)
            ->post(route('integrations.satusehat.send'), ['visit_id' => $visit->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $visit = $visit->fresh();
        $expectedPrefix = $visit->arrived_at->copy()->utc()->format('Y-m-d\TH:i');
        Http::assertSent(function ($request) use ($expectedPrefix) {
            $start = (string) ($request['period']['start'] ?? '');

            return str_contains($request->url(), '/Encounter')
                && str_starts_with($start, $expectedPrefix)
                && ! str_contains($start, 'T01:00'); // bukan hardcode 08:00 WIB
        });
        $this->assertSame(SatuSehatStatus::Synced, $visit->satusehat_status);
    }

    public function test_payload_redactor_masks_nik_and_secrets(): void
    {
        $payload = [
            'identifier' => [['system' => 'https://fhir.kemkes.go.id/id/nik', 'value' => '1234567890123456']],
            'name' => [['text' => 'Budi']],
            'access_token' => 'token-secret',
            'client_secret' => 'super-secret',
            'nested' => ['nik' => '1234567890123456', 'ok' => true],
        ];

        $redacted = \App\Support\Integrations\PayloadRedactor::redact($payload);

        $this->assertSame('********', $redacted['identifier'][0]['value']);
        $this->assertSame('********', $redacted['name']);
        $this->assertSame('********', $redacted['access_token']);
        $this->assertSame('********', $redacted['client_secret']);
        $this->assertSame('********', $redacted['nested']['nik']);
        $this->assertTrue($redacted['nested']['ok']);
    }

    public function test_connection_test_stores_locations_from_the_api(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken*' => Http::response([
                'access_token' => 'token-1',
                'expires_in' => 3600,
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Organization/org-1' => Http::response([
                'resourceType' => 'Organization',
                'id' => 'org-1',
                'name' => 'Klinik Uji',
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Location*' => Http::response([
                'entry' => [[
                    'resource' => ['resourceType' => 'Location', 'id' => 'loc-9', 'name' => 'Poli Gigi'],
                ]],
            ]),
        ]);

        $owner = $this->tenantUser(RoleName::Owner, $this->tenantA, $this->branchA);
        TenantIntegration::query()->create([
            'tenant_id' => $this->tenantA->id,
            'provider' => IntegrationProvider::SatuSehat,
            'enabled' => true,
            'fake' => false,
            'credentials' => [],
        ]);
        BranchSatuSehatSetting::query()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'environment' => 'sandbox',
            'organization_id' => 'org-1',
            'client_id' => 'client-1',
            'client_secret' => 'secret-1',
        ]);

        $this->actingAs($owner)
            ->post(route('integrations.satusehat.test'), ['branch_id' => $this->branchA->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($owner)
            ->put(route('integrations.satusehat.location'), [
                'branch_id' => $this->branchA->id,
                'location_id' => 'loc-9',
                'sync_enabled' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $saved = BranchSatuSehatSetting::query()->where('branch_id', $this->branchA->id)->first();
        $this->assertTrue($saved->connection_ok);
        $this->assertSame('Klinik Uji', $saved->organization_name);
        $this->assertSame('loc-9', $saved->location_id);
        $this->assertSame('Poli Gigi', $saved->location_name);
        $this->assertTrue($saved->sync_enabled);
    }

    protected function readyBranch(Branch $branch, bool $fake = true): BranchSatuSehatSetting
    {
        TenantIntegration::query()->updateOrCreate(
            [
                'tenant_id' => $branch->tenant_id,
                'provider' => IntegrationProvider::SatuSehat,
            ],
            [
                'enabled' => true,
                'fake' => $fake,
                'credentials' => [],
            ],
        );

        return BranchSatuSehatSetting::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'environment' => 'sandbox',
            'organization_id' => 'org-'.$branch->id,
            'client_id' => 'client-'.$branch->id,
            'client_secret' => 'secret-'.$branch->id,
            'location_id' => 'loc-'.$branch->id,
            'location_name' => 'Poli Gigi',
            'connection_ok' => true,
            'validated_at' => now(),
            'sync_enabled' => true,
        ]);
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
