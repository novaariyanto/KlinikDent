<?php

namespace App\Support\Integrations;

use App\Enums\IntegrationProvider;
use App\Enums\RoomType;
use App\Enums\SatuSehatStatus;
use App\Enums\VisitStatus;
use App\Jobs\CheckBpjsMembership;
use App\Jobs\SyncSatuSehatEncounter;
use App\Models\Branch;
use App\Models\BranchSatuSehatSetting;
use App\Models\Patient;
use App\Models\Room;
use App\Models\TenantIntegration;
use App\Models\Visit;

class IntegrationService
{
    public function __construct(protected IntegrationLogger $logger) {}

    public function lookupPatientIhs(Patient $patient, Branch $branch): ?string
    {
        if (filled($patient->satusehat_id)) {
            return (string) $patient->satusehat_id;
        }

        if (! filled($patient->nik)) {
            return null;
        }

        $settings = TenantIntegration::forTenant((int) $patient->tenant_id, IntegrationProvider::SatuSehat);
        $branchSetting = BranchSatuSehatSetting::withoutGlobalScopes()
            ->where('tenant_id', $patient->tenant_id)
            ->where('branch_id', $branch->id)
            ->first();

        if (! $settings->enabled || ! $branchSetting?->readyToSync()) {
            return null;
        }

        $log = $this->logger->queued(
            IntegrationProvider::SatuSehat,
            'patient.ihs',
            (int) $patient->tenant_id,
            $patient,
            [
                'patient_id' => $patient->id,
                'branch_id' => $branch->id,
                'organization_id' => $branchSetting->organization_id,
                'location_id' => $branchSetting->location_id,
            ],
        );

        $sync = new SatuSehatSync($settings, $branchSetting);

        try {
            $id = $sync->ensurePatientIhs($patient);
            $this->logger->finish($log, true, [
                'ihs' => $id,
                'exchanges' => $sync->exchanges,
            ]);

            return $id;
        } catch (IntegrationException $exception) {
            $this->logger->finish($log, false, [
                'exchanges' => $sync->exchanges,
            ], $exception->getMessage());

            return $id;
        } catch (IntegrationException $exception) {
            $this->logger->finish($log, false, [
                'exchanges' => $sync->exchanges,
            ], $exception->getMessage());

            return null;
        }
    }

    public function lookupRoomIhs(Room $room): string
    {
        if ($room->type !== RoomType::Poli) {
            throw new IntegrationException('IHS hanya diambil untuk ruangan poli.');
        }

        $tenantId = $room->tenantId();
        $settings = TenantIntegration::forTenant((int) $tenantId, IntegrationProvider::SatuSehat);
        $branchSetting = BranchSatuSehatSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $room->branch_id)
            ->first();

        if (! $settings->enabled || ! $branchSetting?->readyToSync()) {
            throw new IntegrationException('Sinkronisasi SATUSEHAT cabang ini belum aktif. Uji koneksi dan pilih lokasi poli terlebih dahulu.');
        }

        $log = $this->logger->queued(
            IntegrationProvider::SatuSehat,
            'room.ihs',
            (int) $tenantId,
            $room,
            [
                'room_id' => $room->id,
                'branch_id' => $room->branch_id,
                'organization_id' => $branchSetting->organization_id,
                'location_id' => $branchSetting->location_id,
            ],
        );

        $sync = new SatuSehatSync($settings, $branchSetting);

        try {
            $id = $sync->ensureRoomIhs($room);
            $this->logger->finish($log, true, [
                'ihs' => $id,
                'exchanges' => $sync->exchanges,
            ]);

            return $id;
        } catch (IntegrationException $exception) {
            $this->logger->finish($log, false, [
                'exchanges' => $sync->exchanges,
            ], $exception->getMessage());

            throw $exception;
        }
    }

    public function queueIfReady(Visit $visit): void
    {
        try {
            $this->queueSatuSehat($visit);
        } catch (IntegrationException) {
            // Cabang yang belum dikonfigurasi tidak boleh menggagalkan penyelesaian kunjungan.
        }
    }

    public function queueSatuSehat(Visit $visit, bool $manual = false): void
    {
        $settings = TenantIntegration::forTenant((int) $visit->tenant_id, IntegrationProvider::SatuSehat);

        if (! $settings->enabled) {
            throw new IntegrationException('Modul SATUSEHAT belum diaktifkan untuk klinik ini.');
        }

        $branch = BranchSatuSehatSetting::withoutGlobalScopes()
            ->where('tenant_id', $visit->tenant_id)
            ->where('branch_id', $visit->branch_id)
            ->first();

        if (! $branch?->readyToSync()) {
            throw new IntegrationException('Sinkronisasi SATUSEHAT cabang ini belum aktif. Uji koneksi dan pilih lokasi pelayanan terlebih dahulu.');
        }

        if ($visit->status !== VisitStatus::Done) {
            throw new IntegrationException('Hanya kunjungan selesai yang dikirim ke SATUSEHAT.');
        }

        if (! $manual && in_array($visit->satusehat_status, [SatuSehatStatus::Queued, SatuSehatStatus::Synced], true)) {
            return;
        }

        $log = $this->logger->queued(
            IntegrationProvider::SatuSehat,
            'encounter.sync',
            (int) $visit->tenant_id,
            $visit,
            [
                'visit_id' => $visit->id,
                'branch_id' => $visit->branch_id,
                'organization_id' => $branch->organization_id,
                'location_id' => $branch->location_id,
            ],
        );

        $visit->update(['satusehat_status' => SatuSehatStatus::Queued]);

        SyncSatuSehatEncounter::dispatch($visit->id, $log->id);
    }

    public function queueBpjsCheck(Patient $patient, ?string $cardNumber = null): void
    {
        $settings = TenantIntegration::forTenant((int) $patient->tenant_id, IntegrationProvider::Bpjs);

        if (! $settings->enabled) {
            throw new IntegrationException('Modul BPJS belum diaktifkan untuk klinik ini.');
        }

        $log = $this->logger->queued(
            IntegrationProvider::Bpjs,
            'membership.check',
            (int) $patient->tenant_id,
            $patient,
            [
                'nik' => $patient->nik,
                'bpjs_number' => $cardNumber ?: $patient->bpjs_number,
            ],
        );

        CheckBpjsMembership::dispatch($patient->id, $log->id, $cardNumber);
    }
}
