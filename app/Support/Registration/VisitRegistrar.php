<?php

namespace App\Support\Registration;

use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Room;
use App\Models\User;
use App\Models\Visit;
use App\Support\Doctors\ScheduleAvailability;
use App\Support\Integrations\IntegrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitRegistrar
{
    public bool $reusedExistingPatient = false;

    public function __construct(
        protected DocumentSequenceGenerator $sequences,
        protected ScheduleAvailability $schedules,
        protected IntegrationService $integrations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(User $actor, array $data): Visit
    {
        $this->reusedExistingPatient = false;

        $visit = DB::transaction(function () use ($actor, $data) {
            $tenantId = (int) $actor->tenant_id;
            $branch = $this->resolveBranch($actor, $data['branch_id'] ?? null);
            $patient = $this->resolvePatient($actor, $tenantId, $branch, $data);

            $visitDate = $data['visit_date'] ?? now()->toDateString();
            $doctorId = $data['doctor_id'] ?? null;
            $roomId = $this->resolveRoomId($tenantId, $branch, $data['room_id'] ?? null);

            if ($doctorId) {
                $this->schedules->assertDoctorCanServe((int) $doctorId, (int) $branch->id, (string) $visitDate, $roomId);

                if (! $roomId) {
                    $roomId = $this->schedules->defaultRoomId((int) $doctorId, (int) $branch->id, (string) $visitDate);
                }
            }

            $visit = Visit::query()->create([
                'tenant_id' => $tenantId,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctorId,
                'room_id' => $roomId,
                'payer_id' => $data['payer_id'],
                'status' => VisitStatus::Waiting,
                'visit_date' => $visitDate,
            ]);

            $this->attachQueue($visit, $branch, isset($data['doctor_schedule_id']) ? (int) $data['doctor_schedule_id'] : null);

            return $visit->load(['patient', 'queue', 'doctor', 'room', 'payer', 'branch']);
        });

        if ($visit->patient && $visit->branch) {
            $this->integrations->lookupPatientIhs($visit->patient->fresh(), $visit->branch);
            $visit->unsetRelation('patient');
            $visit->load('patient');
        }

        return $visit;
    }

    /**
     * Janji dari rumah. Tidak membuat akun pengguna.
     *
     * @param  array<string, mixed>  $data
     */
    public function registerGuest(\App\Models\Tenant $tenant, array $data): Visit
    {
        $visit = DB::transaction(function () use ($tenant, $data) {
            $branch = Branch::withoutGlobalScopes()->find($data['branch_id'] ?? null);

            if (! $branch || (int) $branch->tenant_id !== (int) $tenant->id || ! $branch->is_active) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Cabang tidak valid.',
                ]);
            }

            $schedule = \App\Models\DoctorSchedule::withoutGlobalScopes()
                ->whereKey($data['doctor_schedule_id'] ?? null)
                ->where('tenant_id', $tenant->id)
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->first();

            $doctor = $schedule
                ? \App\Models\Doctor::withoutGlobalScopes()->find($schedule->doctor_id)
                : null;

            if (! $schedule || ! $doctor?->user_id) {
                throw ValidationException::withMessages([
                    'doctor_schedule_id' => 'Sesi dokter tidak ditemukan.',
                ]);
            }

            $visitDate = (string) ($data['visit_date'] ?? now()->toDateString());

            if (\App\Enums\Weekday::fromDate($visitDate)->value !== ($schedule->weekday instanceof \App\Enums\Weekday ? $schedule->weekday->value : (int) $schedule->weekday)) {
                throw ValidationException::withMessages([
                    'visit_date' => 'Sesi dokter tidak praktik pada tanggal itu.',
                ]);
            }

            $patient = $this->matchGuestPatient($tenant, $branch, $data);

            $visit = Visit::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->user_id,
                'room_id' => $schedule->room_id,
                'payer_id' => $data['payer_id'],
                'status' => VisitStatus::Waiting,
                'visit_date' => $visitDate,
            ]);

            $this->attachQueue($visit, $branch, (int) $schedule->id);

            return $visit->load([
                'patient' => fn ($query) => $query->withoutGlobalScopes(),
                'queue' => fn ($query) => $query->withoutGlobalScopes(),
                'doctor' => fn ($query) => $query->withoutGlobalScopes(),
                'room' => fn ($query) => $query->withoutGlobalScopes(),
                'branch' => fn ($query) => $query->withoutGlobalScopes(),
            ]);
        });

        if ($visit->patient && $visit->branch) {
            $this->integrations->lookupPatientIhs($visit->patient->fresh(), $visit->branch);
            $visit->unsetRelation('patient');
            $visit->load([
                'patient' => fn ($query) => $query->withoutGlobalScopes(),
            ]);
        }

        return $visit;
    }

    protected function attachQueue(Visit $visit, Branch $branch, ?int $scheduleId = null): void
    {
        $doctorUserId = $visit->doctor_id ? (int) $visit->doctor_id : null;

        if (! $scheduleId && $doctorUserId) {
            $scheduleId = $this->schedules->scheduleIdFor($doctorUserId, (int) $branch->id, (string) $visit->visit_date?->toDateString());
        }

        Queue::withoutGlobalScopes()->create([
            'visit_id' => $visit->id,
            'doctor_schedule_id' => $scheduleId,
            'doctor_user_id' => $doctorUserId,
            'queue_number' => $this->sequences->nextQueueNumber($branch, $visit->visit_date?->toDateString(), $doctorUserId),
            'status' => QueueStatus::Waiting,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function matchGuestPatient(\App\Models\Tenant $tenant, Branch $branch, array $data): Patient
    {
        $nik = filled($data['nik'] ?? null) ? (string) $data['nik'] : null;
        $phone = filled($data['phone'] ?? null) ? (string) $data['phone'] : null;

        $patient = null;

        if ($nik) {
            $patient = Patient::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('nik', $nik)->first();
        }

        if (! $patient && $phone) {
            $patient = Patient::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('phone', $phone)->first();
        }

        if ($patient) {
            return $patient;
        }

        return Patient::withoutGlobalScopes()->create($this->patientAttributes($data, [
            'tenant_id' => $tenant->id,
            'medical_record_number' => $this->sequences->nextMedicalRecord($tenant, $branch),
            'name' => $data['name'],
            'nik' => $nik,
            'phone' => $phone,
            'default_payer_id' => $data['payer_id'] ?? null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolvePatient(User $actor, int $tenantId, Branch $branch, array $data): Patient
    {
        if (! empty($data['patient_id'])) {
            $patient = Patient::query()->findOrFail($data['patient_id']);

            if ((int) $patient->tenant_id !== $tenantId) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Pasien tidak ditemukan pada klinik ini.',
                ]);
            }

            $this->syncPatientProfile($patient, $data);

            return $patient->fresh();
        }

        $tenant = $branch->tenant ?? $actor->tenant;

        if (! $tenant) {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang tidak valid.',
            ]);
        }

        $nik = filled($data['nik'] ?? null) ? (string) $data['nik'] : null;

        if ($nik) {
            $existing = Patient::query()->where('nik', $nik)->first();

            if ($existing) {
                $this->reusedExistingPatient = true;
                $this->syncPatientProfile($existing, $data);

                return $existing->fresh();
            }
        }

        return Patient::query()->create($this->patientAttributes($data, [
            'tenant_id' => $tenantId,
            'medical_record_number' => $this->sequences->nextMedicalRecord($tenant, $branch),
            'name' => $data['name'],
            'nik' => $nik,
            'default_payer_id' => $data['default_payer_id'] ?? $data['payer_id'] ?? null,
        ]));
    }

    /**
     * Simpan IHS / alamat KTP dari form pendaftaran sebelum lookup SATUSEHAT.
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncPatientProfile(Patient $patient, array $data): void
    {
        $fill = [];

        if (! filled($patient->satusehat_id) && filled($data['satusehat_id'] ?? null)) {
            $fill['satusehat_id'] = $data['satusehat_id'];
        }

        $profileFields = [
            'name', 'dob', 'gender', 'phone', 'address', 'address_city', 'address_postal_code',
            'province_code', 'city_code', 'district_code', 'village_code', 'rt', 'rw',
        ];

        foreach ($profileFields as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                continue;
            }

            if (blank($patient->{$field}) || in_array($field, [
                'province_code', 'city_code', 'district_code', 'village_code', 'rt', 'rw', 'address',
            ], true)) {
                $fill[$field] = $data[$field];
            }
        }

        if ($fill !== []) {
            $patient->forceFill($fill)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    protected function patientAttributes(array $data, array $base): array
    {
        $fields = [
            'dob', 'gender', 'phone', 'address', 'address_city', 'address_postal_code',
            'province_code', 'city_code', 'district_code', 'village_code', 'rt', 'rw',
            'birth_place', 'marital_status', 'citizenship', 'family_card_number',
            'satusehat_id',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $base[$field] = $data[$field];
            }
        }

        return $base;
    }

    protected function resolveRoomId(int $tenantId, Branch $branch, mixed $roomId): ?int
    {
        if (! $roomId) {
            return null;
        }

        $room = Room::query()->find($roomId);

        if (! $room || (int) $room->tenantId() !== $tenantId || (int) $room->branch_id !== (int) $branch->id) {
            throw ValidationException::withMessages([
                'room_id' => 'Ruangan tidak valid untuk cabang ini.',
            ]);
        }

        return (int) $room->id;
    }

    protected function resolveBranch(User $actor, mixed $branchId): Branch
    {
        $id = $branchId ?: $actor->branch_id;

        $branch = Branch::query()->find($id);

        if (! $branch || (int) $branch->tenant_id !== (int) $actor->tenant_id) {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang tidak valid.',
            ]);
        }

        if (! $actor->canAccessBranch((int) $branch->id)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Anda hanya dapat mendaftar pada cabang yang ditugaskan.',
            ]);
        }

        return $branch;
    }
}
