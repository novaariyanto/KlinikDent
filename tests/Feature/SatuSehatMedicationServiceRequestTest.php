<?php

namespace Tests\Feature;

use App\Enums\IntegrationProvider;
use App\Enums\PrescriptionItemKind;
use App\Enums\PrescriptionStatus;
use App\Enums\RoleName;
use App\Enums\SatuSehatStatus;
use App\Enums\VisitStatus;
use App\Models\Branch;
use App\Models\BranchSatuSehatSetting;
use App\Models\Diagnosis;
use App\Models\Doctor;
use App\Models\IntegrationLog;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Referral;
use App\Models\SatuSehatResource;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SatuSehatMedicationServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            MenuSeeder::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_medication_and_service_request_payloads_accepted_by_staging_mock(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken*' => Http::response([
                'access_token' => 'token-med',
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
                'id' => 'loc-med',
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
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Medication' => Http::response([
                'resourceType' => 'Medication',
                'id' => 'medication-remote-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/MedicationRequest' => Http::response([
                'resourceType' => 'MedicationRequest',
                'id' => 'medrequest-remote-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/ServiceRequest' => Http::response([
                'resourceType' => 'ServiceRequest',
                'id' => 'servicerequest-remote-1',
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

        $owner = $this->tenantUser(RoleName::Owner);
        $this->readyBranch(false);
        $visit = $this->doneVisit();

        $medicine = Medicine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Amoxicillin 500 mg',
            'kfa_code' => '93004418',
        ]);
        $prescription = Prescription::factory()->create([
            'visit_id' => $visit->id,
            'doctor_id' => $visit->doctor_id,
            'status' => PrescriptionStatus::Sent,
        ]);
        PrescriptionItem::query()->create([
            'prescription_id' => $prescription->id,
            'kind' => PrescriptionItemKind::Ready,
            'medicine_id' => $medicine->id,
            'dosage' => '500 mg',
            'frequency' => '3x1',
            'duration' => '5 hari',
            'quantity' => 15,
        ]);
        Referral::factory()->create([
            'visit_id' => $visit->id,
            'referred_to' => 'RS Gigi Rujukan',
            'reason' => 'Perawatan spesialistik',
        ]);

        $this->actingAs($owner)
            ->post(route('integrations.satusehat.send'), ['visit_id' => $visit->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(SatuSehatStatus::Synced, $visit->fresh()->satusehat_status);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/Medication')) {
                return false;
            }

            $extension = collect($request['extension'] ?? [])->firstWhere('url', 'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType');

            return ($request['resourceType'] ?? null) === 'Medication'
                && ($extension['valueCodeableConcept']['coding'][0]['code'] ?? null) === 'NC'
                && ($request['code']['coding'][0]['code'] ?? null) === '93004418';
        });

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/MedicationRequest')) {
                return false;
            }

            $qty = $request['dispenseRequest']['quantity'] ?? [];

            return str_starts_with((string) ($request['medicationReference']['reference'] ?? ''), 'Medication/')
                && ($request['subject']['reference'] ?? null) === 'Patient/patient-ihs'
                && ($request['encounter']['reference'] ?? null) === 'Encounter/encounter-1'
                && ($qty['system'] ?? null) === 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm'
                && filled($qty['code'] ?? null);
        });

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/ServiceRequest')) {
                return false;
            }

            return ($request['code']['coding'][0]['code'] ?? null) === '103697008'
                && ($request['code']['coding'][0]['system'] ?? null) === 'http://snomed.info/sct'
                && ($request['category'][0]['coding'][0]['code'] ?? null) === '3457005';
        });
    }

    public function test_medication_http_400_recorded_with_element_and_does_not_hide_optional_failure(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken*' => Http::response([
                'access_token' => 'token-med',
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
                'id' => 'loc-med',
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Condition' => Http::response([
                'resourceType' => 'Condition',
                'id' => 'condition-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Observation' => Http::response([
                'resourceType' => 'Observation',
                'id' => 'observation-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Medication' => Http::response([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'diagnostics' => 'Element not found: Medication.extension',
                ]],
            ], 400),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/ServiceRequest' => Http::response([
                'resourceType' => 'ServiceRequest',
                'id' => 'servicerequest-remote-1',
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

        $owner = $this->tenantUser(RoleName::Owner);
        $this->readyBranch(false);
        $visit = $this->doneVisit();
        $medicine = Medicine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kfa_code' => '93004418',
        ]);
        $prescription = Prescription::factory()->create([
            'visit_id' => $visit->id,
            'doctor_id' => $visit->doctor_id,
            'status' => PrescriptionStatus::Sent,
        ]);
        PrescriptionItem::query()->create([
            'prescription_id' => $prescription->id,
            'kind' => PrescriptionItemKind::Ready,
            'medicine_id' => $medicine->id,
            'quantity' => 1,
        ]);
        Referral::factory()->create(['visit_id' => $visit->id]);

        $this->actingAs($owner)
            ->post(route('integrations.satusehat.send'), ['visit_id' => $visit->id])
            ->assertRedirect();

        // Medication/ServiceRequest opsional: Encounter finished tetap boleh jika resource wajib ok.
        $this->assertSame(SatuSehatStatus::Synced, $visit->fresh()->satusehat_status);

        $log = IntegrationLog::query()->where('provider', 'satusehat')->latest('id')->first();
        $failures = $log?->response_payload['failures'] ?? [];
        $this->assertNotEmpty($failures);
        $medicationFailure = collect($failures)->firstWhere('type', 'Medication');
        $this->assertNotNull($medicationFailure);
        $this->assertSame(400, $medicationFailure['http_status'] ?? null);
        $this->assertSame('Medication.extension', $medicationFailure['element'] ?? null);
        $this->assertStringContainsString('Medication.extension', (string) ($medicationFailure['error'] ?? ''));

        // Kegagalan opsional tidak mengosongkan required_failures secara palsu.
        $this->assertSame([], $log->response_payload['required_failures'] ?? []);
    }

    public function test_synced_medication_not_duplicated_on_retry(): void
    {
        Http::preventStrayRequests();
        $calls = ['medication' => 0];
        Http::fake([
            'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken*' => Http::response([
                'access_token' => 'token-med',
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
                'id' => 'loc-med',
            ]),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Condition' => Http::response([
                'resourceType' => 'Condition',
                'id' => 'condition-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Observation' => Http::response([
                'resourceType' => 'Observation',
                'id' => 'observation-1',
            ], 201),
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Medication' => function () use (&$calls) {
                $calls['medication']++;

                return Http::response([
                    'resourceType' => 'Medication',
                    'id' => 'medication-remote-1',
                ], 201);
            },
            'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/MedicationRequest' => Http::response([
                'resourceType' => 'MedicationRequest',
                'id' => 'medrequest-remote-1',
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

        $owner = $this->tenantUser(RoleName::Owner);
        $setting = $this->readyBranch(false);
        $visit = $this->doneVisit();
        $medicine = Medicine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kfa_code' => '93004418',
        ]);
        $prescription = Prescription::factory()->create([
            'visit_id' => $visit->id,
            'doctor_id' => $visit->doctor_id,
            'status' => PrescriptionStatus::Sent,
        ]);
        PrescriptionItem::query()->create([
            'prescription_id' => $prescription->id,
            'kind' => PrescriptionItemKind::Ready,
            'medicine_id' => $medicine->id,
            'quantity' => 1,
        ]);

        SatuSehatResource::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'resource_type' => 'Medication',
            'local_key' => 'medication:'.$medicine->id,
            'satusehat_id' => 'medication-remote-1',
            'sync_status' => SatuSehatStatus::Synced,
            'last_synced_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('integrations.satusehat.send'), ['visit_id' => $visit->id])
            ->assertRedirect();

        $this->assertSame(0, $calls['medication']);
        $this->assertSame('org-'.$this->branch->id, $setting->organization_id);
    }

    protected function doneVisit(): Visit
    {
        $doctorUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
        ]);
        Doctor::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $doctorUser->id,
            'nik' => '3174010101010099',
        ]);
        $patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nik' => '1234567890123499',
        ]);
        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorUser->id,
            'payer_id' => Payer::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'status' => VisitStatus::Done,
            'arrived_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ]);
        Diagnosis::factory()->create([
            'visit_id' => $visit->id,
            'code' => 'K02.1',
            'description' => 'Karies dentin',
        ]);

        return $visit;
    }

    protected function readyBranch(bool $fake = true): BranchSatuSehatSetting
    {
        TenantIntegration::query()->updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'provider' => IntegrationProvider::SatuSehat,
            ],
            [
                'enabled' => true,
                'fake' => $fake,
                'credentials' => [],
            ],
        );

        return BranchSatuSehatSetting::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'environment' => 'sandbox',
            'organization_id' => 'org-'.$this->branch->id,
            'client_id' => 'client-'.$this->branch->id,
            'client_secret' => 'secret-'.$this->branch->id,
            'location_id' => 'loc-med',
            'location_name' => 'Poli Gigi',
            'connection_ok' => true,
            'validated_at' => now(),
            'sync_enabled' => true,
        ]);
    }

    protected function tenantUser(RoleName $role): User
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
        ]);
        $user->assignRole($role->value);

        return $user;
    }
}
