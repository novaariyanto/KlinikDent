<?php

namespace Tests\Feature;

use App\Enums\BillingStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\RoleName;
use App\Enums\ToothStatus;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Prescription;
use App\Models\Procedure;
use App\Models\ProcedureRecord;
use App\Models\Tariff;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalCareTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Payer $payerA;

    protected Payer $payerB;

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
        $this->branchA = Branch::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cabang A']);
        $this->branchB = Branch::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Cabang B']);
        $this->payerA = Payer::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Umum A']);
        $this->payerB = Payer::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Umum B']);
        $this->procedureA = Procedure::factory()->create(['tenant_id' => $this->tenantA->id, 'code' => 'SCL-01', 'name' => 'Scaling']);
        $this->medicineA = Medicine::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Amoxicillin 500mg']);
    }

    public function test_dentist_can_complete_visit_end_to_end_with_price_snapshot(): void
    {
        $dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenantA->id]);
        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $patient->id,
            'doctor_id' => $dentist->id,
            'payer_id' => $this->payerA->id,
        ]);

        Tariff::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'procedure_id' => $this->procedureA->id,
            'branch_id' => null,
            'payer_id' => null,
            'price' => '100000.00',
            'effective_date' => now()->subDays(10)->toDateString(),
        ]);
        Tariff::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'procedure_id' => $this->procedureA->id,
            'branch_id' => $this->branchA->id,
            'payer_id' => $this->payerA->id,
            'price' => '250000.00',
            'effective_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($dentist)->get(route('examinations.index'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($dentist)->get(route('care.show', $visit))->assertOk()->assertSee('Odontogram');

        $this->actingAs($dentist)->put(route('care.record.update', $visit), [
            'chief_complaint' => 'Sakit gigi geraham',
            'clinical_notes' => 'Karies profunda 16',
        ])->assertRedirect();

        $this->actingAs($dentist)->put(route('care.tooth.update', $visit), [
            'tooth_number' => '16',
            'status' => ToothStatus::Caries->value,
            'notes' => 'Karies profunda',
        ])->assertRedirect();

        $this->actingAs($dentist)->post(route('care.diagnosis.store', $visit), [
            'tooth_number' => '16',
            'code' => 'K02.1',
            'description' => 'Karies dentin',
        ])->assertRedirect();

        $this->actingAs($dentist)->post(route('care.procedure.store', $visit), [
            'procedure_id' => $this->procedureA->id,
            'tooth_number' => '16',
            'quantity' => 1,
        ])->assertRedirect();

        $record = ProcedureRecord::query()->where('visit_id', $visit->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('250000.00', $record->price_at_time);
        $this->assertSame(BillingStatus::Unbilled, $record->billing_status);

        Tariff::query()->whereKey(
            Tariff::query()->where('price', '250000.00')->value('id')
        )->update(['price' => '999999.00']);

        $this->assertSame('250000.00', $record->fresh()->price_at_time);

        $this->actingAs($dentist)->post(route('care.prescription-item.store', $visit), [
            'medicine_id' => $this->medicineA->id,
            'dosage' => '1 kapsul',
            'frequency' => '3x sehari',
            'duration' => '5 hari',
            'quantity' => 15,
        ])->assertRedirect();

        $prescription = Prescription::query()->where('visit_id', $visit->id)->first();
        $this->assertSame(PrescriptionStatus::Draft, $prescription?->status);

        $this->actingAs($dentist)->post(route('care.prescription.send', [$visit, $prescription]))->assertRedirect();
        $this->assertSame(PrescriptionStatus::Sent, $prescription->fresh()->status);

        $this->actingAs($dentist)->post(route('care.referral.store', $visit), [
            'referred_to' => 'RS Gigi Rujukan',
            'reason' => 'Perawatan spesialistik',
        ])->assertRedirect();

        $this->actingAs($dentist)->post(route('care.complete', $visit))->assertRedirect();
        $this->assertTrue($visit->fresh()->status->value === 'done');
    }

    public function test_nurse_and_assistant_can_fill_initial_exam_but_not_diagnosis(): void
    {
        $nurse = $this->tenantUser(RoleName::Nurse, $this->tenantA, $this->branchA);
        $assistant = $this->tenantUser(RoleName::DentalAssistant, $this->tenantA, $this->branchA);
        $visit = $this->openVisit();

        $this->actingAs($nurse)->get(route('examinations.vitals'))->assertOk()->assertDontSee('Coming Soon');
        $this->actingAs($nurse)->put(route('care.vitals.update', $visit), [
            'blood_pressure' => '110/70',
            'pulse' => '80',
            'temperature' => '36.7',
            'respiration' => '18',
        ])->assertRedirect();
        $this->actingAs($nurse)->put(route('care.anamnesis.update', $visit), [
            'anamnesis' => 'Nyeri sejak kemarin',
        ])->assertRedirect();
        $this->actingAs($nurse)->put(route('care.notes.update', $visit), [
            'care_notes' => 'Pasien kooperatif',
        ])->assertRedirect();
        $this->actingAs($nurse)->post(route('care.diagnosis.store', $visit), [
            'code' => 'K02.1',
            'description' => 'Karies',
        ])->assertForbidden();

        $this->actingAs($assistant)->put(route('care.examination.update', $visit), [
            'initial_examination' => 'Karies 16',
        ])->assertRedirect();
        $this->actingAs($assistant)->post(route('care.diagnosis.store', $visit), [
            'code' => 'K02.1',
            'description' => 'Karies',
        ])->assertForbidden();
    }

    public function test_pharmacy_sees_sent_prescriptions_only(): void
    {
        $dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $pharmacy = $this->tenantUser(RoleName::Pharmacy, $this->tenantA, $this->branchA);
        $visit = $this->openVisit($dentist);

        $this->actingAs($dentist)->post(route('care.prescription-item.store', $visit), [
            'medicine_id' => $this->medicineA->id,
            'quantity' => 10,
        ])->assertRedirect();

        $draft = Prescription::query()->where('visit_id', $visit->id)->first();

        $this->actingAs($pharmacy)->get(route('pharmacy.prescriptions.incoming'))
            ->assertOk()
            ->assertDontSee('Coming Soon')
            ->assertDontSee($visit->patient->name);

        $this->actingAs($dentist)->post(route('care.prescription.send', [$visit, $draft]))->assertRedirect();

        $this->actingAs($pharmacy)->get(route('pharmacy.prescriptions.incoming'))
            ->assertOk()
            ->assertSee($visit->patient->name);
    }

    public function test_cross_tenant_care_url_returns_forbidden(): void
    {
        $dentistA = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $visitB = Visit::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->branchB->id,
            'patient_id' => Patient::factory()->create(['tenant_id' => $this->tenantB->id])->id,
            'payer_id' => $this->payerB->id,
        ]);

        $this->actingAs($dentistA)->get(route('care.show', $visitB))->assertForbidden();
        $this->actingAs($dentistA)->put(route('care.record.update', $visitB), [
            'chief_complaint' => 'Hacked',
        ])->assertForbidden();
    }

    public function test_patient_history_shows_across_visits(): void
    {
        $dentist = $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Siti Rahma']);
        $first = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $patient->id,
            'doctor_id' => $dentist->id,
            'payer_id' => $this->payerA->id,
            'visit_date' => now()->subWeek()->toDateString(),
        ]);
        $first->diagnoses()->create([
            'code' => 'K02.1',
            'description' => 'Karies dentin',
        ]);
        $second = Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $patient->id,
            'doctor_id' => $dentist->id,
            'payer_id' => $this->payerA->id,
        ]);

        $this->actingAs($dentist)
            ->get(route('patients.history.show', $patient))
            ->assertOk()
            ->assertSee('Karies dentin')
            ->assertSee($second->visit_date->format('d M Y'));
    }

    protected function openVisit(?User $dentist = null): Visit
    {
        $dentist ??= $this->tenantUser(RoleName::Dentist, $this->tenantA, $this->branchA);
        $patient = Patient::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Budi Santoso']);

        return Visit::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'patient_id' => $patient->id,
            'doctor_id' => $dentist->id,
            'payer_id' => $this->payerA->id,
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
