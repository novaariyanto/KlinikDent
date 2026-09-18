<?php

namespace App\Support\Doctors;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\Weekday;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Doctor
    {
        return DB::transaction(function () use ($actor, $data) {
            $tenantId = $actor->isPlatformAdmin()
                ? (int) ($data['tenant_id'] ?? 0)
                : (int) $actor->tenant_id;

            if ($tenantId < 1) {
                throw ValidationException::withMessages([
                    'user_id' => 'Klinik tidak ditemukan.',
                ]);
            }

            $user = $this->resolveUser($actor, $tenantId, $data);

            $doctor = Doctor::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'sip' => $data['sip'] ?? null,
                'str' => $data['str'] ?? null,
                'specialization' => $data['specialization'] ?? null,
                'phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            activity_log('created', $doctor, $data, 'Tenaga medis '.$doctor->displayName().' ditambahkan.', 'doctors');

            return $doctor;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Doctor $doctor, array $data): Doctor
    {
        $doctor->update([
            'sip' => $data['sip'] ?? null,
            'str' => $data['str'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? $doctor->is_active),
        ]);

        if (! empty($data['name']) && $doctor->user) {
            $doctor->user->update(['name' => $data['name']]);
        }

        activity_log('updated', $doctor, $data, 'Profil '.$doctor->displayName().' diubah.', 'doctors');

        return $doctor->fresh(['user.roles', 'user.branch', 'user.branches']) ?? $doctor;
    }

    public function delete(Doctor $doctor): void
    {
        $name = $doctor->displayName();
        activity_log('deleted', $doctor, ['name' => $name], 'Profil tenaga medis '.$name.' dihapus.', 'doctors');
        $doctor->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addSchedule(Doctor $doctor, array $data): DoctorSchedule
    {
        $this->assertNoOverlap($doctor, $data);

        $schedule = $doctor->schedules()->create([
            'tenant_id' => $doctor->tenant_id,
            'branch_id' => $data['branch_id'],
            'room_id' => $data['room_id'] ?? null,
            'weekday' => $data['weekday'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        activity_log('created', $schedule, $data, 'Jadwal '.$doctor->displayName().' ditambahkan.', 'doctors');

        return $schedule;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSchedule(DoctorSchedule $schedule, array $data): DoctorSchedule
    {
        $this->assertNoOverlap($schedule->doctor, $data, $schedule->id);

        $schedule->update([
            'branch_id' => $data['branch_id'],
            'room_id' => $data['room_id'] ?? null,
            'weekday' => $data['weekday'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'is_active' => (bool) ($data['is_active'] ?? $schedule->is_active),
        ]);

        activity_log('updated', $schedule, $data, 'Jadwal '.$schedule->doctor?->displayName().' diubah.', 'doctors');

        return $schedule;
    }

    public function deleteSchedule(DoctorSchedule $schedule): void
    {
        activity_log('deleted', $schedule, [], 'Jadwal tenaga medis dihapus.', 'doctors');
        $schedule->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveUser(User $actor, int $tenantId, array $data): User
    {
        if (($data['source'] ?? 'new') === 'existing') {
            $user = User::withoutGlobalScopes()->with('doctorProfile')->findOrFail((int) $data['user_id']);

            if ((int) $user->tenant_id !== $tenantId) {
                throw ValidationException::withMessages([
                    'user_id' => 'Pengguna bukan milik klinik ini.',
                ]);
            }

            if (! $user->isMedicalStaff()) {
                throw ValidationException::withMessages([
                    'user_id' => 'Pengguna harus berperan sebagai tenaga medis.',
                ]);
            }

            if ($user->doctorProfile) {
                throw ValidationException::withMessages([
                    'user_id' => 'Pengguna ini sudah punya profil tenaga medis.',
                ]);
            }

            return $user;
        }

        $branchIds = array_values(array_unique(array_map('intval', $data['branch_ids'] ?? [])));
        $role = (string) ($data['role'] ?? RoleName::Dentist->value);

        if (! in_array($role, RoleName::medicalStaffValues(), true)) {
            throw ValidationException::withMessages([
                'role' => 'Role tenaga medis tidak valid.',
            ]);
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => UserStatus::Active,
            'tenant_id' => $tenantId,
            'branch_id' => $branchIds[0] ?? $actor->branch_id,
        ]);

        $user->syncRoles([$role]);
        $user->syncAssignedBranches($branchIds);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function assertNoOverlap(Doctor $doctor, array $data, ?int $ignoreId = null): void
    {
        $exists = DoctorSchedule::query()
            ->where('doctor_id', $doctor->id)
            ->where('branch_id', $data['branch_id'])
            ->where('weekday', $data['weekday'] instanceof Weekday ? $data['weekday']->value : $data['weekday'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'start_time' => 'Jadwal bentrok dengan jam praktik yang sudah ada di cabang yang sama.',
            ]);
        }

        if (! empty($data['room_id'])) {
            $room = Room::withoutGlobalScopes()->find((int) $data['room_id']);

            if (! $room || (int) $room->branch_id !== (int) $data['branch_id']) {
                throw ValidationException::withMessages([
                    'room_id' => 'Ruangan harus berada di cabang yang dipilih.',
                ]);
            }
        }
    }
}
