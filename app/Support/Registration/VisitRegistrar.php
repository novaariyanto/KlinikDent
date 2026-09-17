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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitRegistrar
{
    public function __construct(protected DocumentSequenceGenerator $sequences) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(User $actor, array $data): Visit
    {
        return DB::transaction(function () use ($actor, $data) {
            $tenantId = (int) $actor->tenant_id;
            $branch = $this->resolveBranch($actor, $data['branch_id'] ?? null);
            $patient = $this->resolvePatient($actor, $tenantId, $branch, $data);

            $visit = Visit::query()->create([
                'tenant_id' => $tenantId,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'doctor_id' => $data['doctor_id'] ?? null,
                'room_id' => $this->resolveRoomId($tenantId, $branch, $data['room_id'] ?? null),
                'payer_id' => $data['payer_id'],
                'status' => VisitStatus::Waiting,
                'visit_date' => $data['visit_date'] ?? now()->toDateString(),
            ]);

            Queue::query()->create([
                'visit_id' => $visit->id,
                'queue_number' => $this->sequences->nextQueueNumber($branch, $visit->visit_date?->toDateString()),
                'status' => QueueStatus::Waiting,
            ]);

            return $visit->load(['patient', 'queue', 'doctor', 'room', 'payer', 'branch']);
        });
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

            return $patient;
        }

        $tenant = $branch->tenant ?? $actor->tenant;

        if (! $tenant) {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang tidak valid.',
            ]);
        }

        return Patient::query()->create([
            'tenant_id' => $tenantId,
            'medical_record_number' => $this->sequences->nextMedicalRecord($tenant, $branch),
            'name' => $data['name'],
            'nik' => $data['nik'] ?? null,
            'dob' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'default_payer_id' => $data['default_payer_id'] ?? $data['payer_id'] ?? null,
        ]);
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

        if ($actor->branch_id && ! $actor->can('branch.manage') && (int) $branch->id !== (int) $actor->branch_id) {
            throw ValidationException::withMessages([
                'branch_id' => 'Anda hanya dapat mendaftar pada cabang Anda.',
            ]);
        }

        return $branch;
    }
}
