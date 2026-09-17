<?php

namespace Database\Seeders;

use App\Enums\PrescriptionStatus;
use App\Enums\ToothStatus;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\Procedure;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use App\Support\Clinical\ClinicalCareService;
use App\Support\Clinical\TariffResolver;
use Illuminate\Database\Seeder;

class ClinicalCareSeeder extends Seeder
{
    public function run(): void
    {
        $care = new ClinicalCareService(app(TariffResolver::class));

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($care) {
            $visit = Visit::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->with(['patient', 'doctor'])
                ->orderBy('id')
                ->first();

            if (! $visit || ! $visit->patient) {
                return;
            }

            $record = $care->ensureRecord($visit);
            $record->update([
                'chief_complaint' => 'Gigi geraham kanan atas terasa sakit saat mengunyah.',
                'anamnesis' => 'Nyeri sejak 3 hari, bertambah di malam hari. Tidak ada alergi obat.',
                'initial_examination' => 'Karies profunda gigi 16, perkusi positif.',
                'clinical_notes' => 'Rencana restorasi dan kontrol 1 minggu.',
                'vital_signs' => [
                    'blood_pressure' => '120/80',
                    'pulse' => '78',
                    'temperature' => '36.6',
                    'respiration' => '18',
                ],
                'care_notes' => 'Pasien kooperatif, edukasi kebersihan gigi.',
            ]);

            $actor = $visit->doctor
                ?? User::query()->where('tenant_id', $tenant->id)->orderBy('id')->first();

            if ($actor) {
                $care->updateTooth($visit, $actor, '16', ToothStatus::Caries, 'Karies profunda');
                $care->updateTooth($visit, $actor, '36', ToothStatus::Filling, 'Tambalan lama');
            }

            $visit->diagnoses()->create([
                'tooth_number' => '16',
                'code' => 'K02.1',
                'description' => 'Karies dentin',
            ]);

            $procedure = Procedure::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('code', 'KSL-01')
                ->first();

            if ($procedure) {
                $care->addProcedure($visit, $procedure->id, '16', 1);
            }

            $medicine = Medicine::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->orderBy('id')
                ->first();

            if ($medicine && $actor) {
                $prescription = Prescription::query()->create([
                    'visit_id' => $visit->id,
                    'doctor_id' => $actor->id,
                    'status' => PrescriptionStatus::Sent,
                ]);
                $prescription->items()->create([
                    'medicine_id' => $medicine->id,
                    'dosage' => '1 kapsul',
                    'frequency' => '3x sehari',
                    'duration' => '5 hari',
                    'quantity' => 15,
                ]);
            }

            $visit->referrals()->create([
                'referred_to' => 'Spesialis Bedah Mulut',
                'reason' => 'Evaluasi lanjutan bila nyeri berlanjut',
                'notes' => 'Contoh rujukan seeder',
            ]);
        });
    }
}
