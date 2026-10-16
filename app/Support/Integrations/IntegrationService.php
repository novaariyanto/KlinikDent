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
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Room;
use App\Models\TenantIntegration;
use App\Models\Visit;

class IntegrationService
{
    public function __construct(protected IntegrationLogger $logger) {}

    public function lookupPatientIhs(Patient $patient, Branch $branch): ?string
    {
        try {
            return $this->fetchPatientIhs($patient, $branch);
        } catch (IntegrationException) {
            return null;
        }
    }

    /**
     * Ambil / daftarkan IHS pasien (throw jika gagal) — untuk aksi manual di UI.
     */
    public function fetchPatientIhs(Patient $patient, Branch $branch): string
    {
        if (filled($patient->satusehat_id)) {
            return (string) $patient->satusehat_id;
        }

        if (! filled($patient->nik)) {
            throw new IntegrationException('Isi NIK pasien terlebih dahulu sebelum mencari IHS.');
        }

        [$settings, $branchSetting] = $this->requireBranchSatuSehat((int) $patient->tenant_id, $branch, true);

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

            throw $exception;
        }
    }

    /**
     * Daftarkan pasien baru ke MPI (draft form, belum wajib tersimpan lokal).
     */
    public function registerPatientIhsDraft(Patient $patient, Branch $branch): string
    {
        if (! filled($patient->nik)) {
            throw new IntegrationException('Isi NIK pasien terlebih dahulu sebelum mendaftarkan IHS.');
        }

        [$settings, $branchSetting] = $this->requireBranchSatuSehat((int) $patient->tenant_id, $branch, true);

        $log = $this->logger->queued(
            IntegrationProvider::SatuSehat,
            'patient.ihs.register',
            (int) $patient->tenant_id,
            null,
            [
                'branch_id' => $branch->id,
                'nik' => (string) $patient->nik,
                'organization_id' => $branchSetting->organization_id,
                'location_id' => $branchSetting->location_id,
            ],
        );

        $sync = new SatuSehatSync($settings, $branchSetting);

        try {
            $id = $sync->ensurePatientIhs($patient, persist: false);
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

    /**
     * Cari IHS di MPI SATUSEHAT berdasarkan NIK (tanpa mendaftarkan pasien baru).
     */
    public function searchPatientIhsByNik(int $tenantId, Branch $branch, string $nik): string
    {
        $nik = preg_replace('/\D+/', '', $nik) ?? '';

        if (strlen($nik) !== 16) {
            throw new IntegrationException('NIK harus 16 digit untuk mencari IHS.');
        }

        [$settings, $branchSetting] = $this->requireBranchSatuSehat($tenantId, $branch, false);

        $log = $this->logger->queued(
            IntegrationProvider::SatuSehat,
            'patient.ihs.search',
            $tenantId,
            null,
            [
                'branch_id' => $branch->id,
                'nik' => $nik,
                'organization_id' => $branchSetting->organization_id,
            ],
        );

        $sync = new SatuSehatSync($settings, $branchSetting);

        try {
            $id = $sync->findPatientIhsByNik($nik);
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

    /**
     * @return array{0: TenantIntegration, 1: BranchSatuSehatSetting}
     */
    protected function requireBranchSatuSehat(int $tenantId, Branch $branch, bool $needSync): array
    {
        $settings = TenantIntegration::forTenant($tenantId, IntegrationProvider::SatuSehat);
        $branchSetting = BranchSatuSehatSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branch->id)
            ->first();

        if (! $settings->enabled) {
            throw new IntegrationException('Modul SATUSEHAT belum diaktifkan untuk klinik ini.');
        }

        if ($needSync && ! $branchSetting?->readyToSync()) {
            throw new IntegrationException('Sinkronisasi SATUSEHAT cabang ini belum aktif. Uji koneksi dan pilih lokasi pelayanan terlebih dahulu.');
        }

        if (! $needSync && ! $branchSetting?->readyToPushLocation()) {
            throw new IntegrationException('SATUSEHAT cabang belum siap. Uji koneksi kredensial cabang terlebih dahulu.');
        }

        return [$settings, $branchSetting];
    }

    public function syncBranchLocation(Branch $branch): string
    {
        $tenantId = (int) $branch->tenant_id;
        $settings = TenantIntegration::forTenant($tenantId, IntegrationProvider::SatuSehat);
        $branchSetting = $this->resolveBranchSatuSehatSetting($branch);

        if (! $settings->enabled) {
            throw new IntegrationException('Modul SATUSEHAT belum aktif. Aktifkan di Integrasi → SATUSEHAT.');
        }

        if (! $branchSetting?->readyToPushLocation()) {
            throw new IntegrationException('Uji koneksi SATUSEHAT cabang ini dulu di Integrasi → SATUSEHAT, lalu sync lokasi dari sini.');
        }

        $log = $this->logger->queued(
            IntegrationProvider::SatuSehat,
            'branch.location',
            $tenantId,
            $branch,
            [
                'branch_id' => $branch->id,
                'organization_id' => $branchSetting->organization_id,
                'location_id' => $branchSetting->location_id,
            ],
        );

        $sync = new SatuSehatSync($settings, $branchSetting);

        try {
            $id = $sync->ensureBranchLocation($branch);
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

    /**
     * Sync Location setelah cabang dibuat. Tidak melempar jika belum siap / gagal soft.
     *
     * @return array{synced: bool, id?: string, skipped?: string, error?: string}
     */
    public function syncBranchLocationAfterCreate(Branch $branch): array
    {
        $settings = TenantIntegration::forTenant((int) $branch->tenant_id, IntegrationProvider::SatuSehat);

        if (! $settings->enabled) {
            return ['synced' => false, 'skipped' => 'Modul SATUSEHAT belum aktif.'];
        }

        if ($branch->latitude === null || $branch->longitude === null) {
            return ['synced' => false, 'skipped' => 'Lintang/bujur belum diisi; sync lokasi dilewati.'];
        }

        try {
            $this->resolveBranchSatuSehatSetting($branch, seedFromSibling: true);
            $id = $this->syncBranchLocation($branch);

            return ['synced' => true, 'id' => $id];
        } catch (IntegrationException $exception) {
            return ['synced' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Ambil pengaturan cabang; opsional salin kredensial dari cabang sibling yang sudah terhubung.
     */
    protected function resolveBranchSatuSehatSetting(Branch $branch, bool $seedFromSibling = false): ?BranchSatuSehatSetting
    {
        $setting = BranchSatuSehatSetting::withoutGlobalScopes()
            ->where('tenant_id', $branch->tenant_id)
            ->where('branch_id', $branch->id)
            ->first();

        if ($setting?->readyToPushLocation()) {
            return $setting;
        }

        if (! $seedFromSibling) {
            return $setting;
        }

        $source = BranchSatuSehatSetting::withoutGlobalScopes()
            ->where('tenant_id', $branch->tenant_id)
            ->where('branch_id', '!=', $branch->id)
            ->where('connection_ok', true)
            ->whereNotNull('organization_id')
            ->whereNotNull('client_id')
            ->whereNotNull('client_secret')
            ->orderByDesc('validated_at')
            ->orderByDesc('id')
            ->first();

        if (! $source || $source->secretIsUnreadable() || ! $source->hasSecret()) {
            return $setting;
        }

        $setting ??= new BranchSatuSehatSetting([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
        ]);

        $setting->fill([
            'environment' => $source->environment,
            'base_url' => $source->base_url,
            'token_url' => $source->token_url,
            'organization_id' => $source->organization_id,
            'organization_name' => $source->organization_name,
            'client_id' => $source->client_id,
            'connection_ok' => true,
            'validated_at' => $source->validated_at ?? now(),
            'sync_enabled' => false,
            'location_id' => null,
            'location_name' => null,
            'last_error' => null,
        ]);
        $setting->client_secret = $source->client_secret;
        $setting->save();

        return $setting->fresh();
    }

    /**
     * Desa/kelurahan dari Master Wilayah SATUSEHAT v2 (fallback kosong jika belum siap).
     *
     * @return array<string, string>
     */
    public function masterWilayahVillages(int $tenantId, int $branchId, string $districtCode): array
    {
        $settings = TenantIntegration::forTenant($tenantId, IntegrationProvider::SatuSehat);
        $branchSetting = BranchSatuSehatSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->first();

        // Master Wilayah cukup kredensial + uji koneksi (tidak butuh Location).
        if (! $settings->enabled || ! $branchSetting?->readyToPushLocation()) {
            return [];
        }

        $client = new SatuSehatClient($settings, $branchSetting);

        try {
            return $client->masterWilayahSubDistricts($districtCode);
        } catch (IntegrationException) {
            return [];
        }
    }

    public function lookupDoctorIhs(Doctor $doctor): string
    {
        if (filled($doctor->satusehat_id)) {
            return (string) $doctor->satusehat_id;
        }

        if (! filled($doctor->nik)) {
            throw new IntegrationException('Isi NIK dokter terlebih dahulu sebelum mengambil IHS.');
        }

        $tenantId = (int) $doctor->tenant_id;
        $settings = TenantIntegration::forTenant($tenantId, IntegrationProvider::SatuSehat);

        if (! $settings->enabled) {
            throw new IntegrationException('Modul SATUSEHAT belum diaktifkan untuk klinik ini.');
        }

        $branchSetting = $this->resolveDoctorBranchSetting($doctor);

        if (! $branchSetting?->readyToPushLocation()) {
            throw new IntegrationException('SATUSEHAT cabang belum siap. Uji koneksi kredensial cabang terlebih dahulu.');
        }

        $log = $this->logger->queued(
            IntegrationProvider::SatuSehat,
            'doctor.ihs',
            $tenantId,
            $doctor,
            [
                'doctor_id' => $doctor->id,
                'branch_id' => $branchSetting->branch_id,
                'organization_id' => $branchSetting->organization_id,
            ],
        );

        $sync = new SatuSehatSync($settings, $branchSetting);

        try {
            $id = $sync->ensurePractitionerIhs($doctor);
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

    protected function resolveDoctorBranchSetting(Doctor $doctor): ?BranchSatuSehatSetting
    {
        $doctor->loadMissing(['user.branches', 'user.branch']);

        $branchIds = collect();

        if ($doctor->user?->branch_id) {
            $branchIds->push((int) $doctor->user->branch_id);
        }

        foreach ($doctor->user?->branches ?? [] as $branch) {
            $branchIds->push((int) $branch->id);
        }

        $branchIds = $branchIds->unique()->filter()->values();

        $query = BranchSatuSehatSetting::withoutGlobalScopes()
            ->where('tenant_id', $doctor->tenant_id);

        if ($branchIds->isNotEmpty()) {
            $preferred = (clone $query)->whereIn('branch_id', $branchIds->all())->get()
                ->first(fn (BranchSatuSehatSetting $setting) => $setting->readyToPushLocation());

            if ($preferred) {
                return $preferred;
            }
        }

        return $query->get()->first(fn (BranchSatuSehatSetting $setting) => $setting->readyToPushLocation());
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

        // Manual retry diizinkan dari failed/partial; otomatis tidak mengantri ulang jika sudah synced.

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
