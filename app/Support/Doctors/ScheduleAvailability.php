<?php

namespace App\Support\Doctors;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ScheduleAvailability
{
    /**
     * @return Collection<int, array{id: int, name: string, time: string, room_id: int|null}>
     */
    public function doctorsFor(int $branchId, string $date, ?int $roomId = null): Collection
    {
        $schedules = DoctorSchedule::query()
            ->active()
            ->forBranch($branchId)
            ->onDate($date)
            ->forRoom($roomId)
            ->whereHas('doctor', fn ($doctor) => $doctor->active())
            ->with(['doctor.user'])
            ->orderBy('start_time')
            ->get()
            ->filter(fn (DoctorSchedule $schedule) => $schedule->doctor?->user);

        return $schedules
            ->groupBy(fn (DoctorSchedule $schedule) => $schedule->doctor?->user_id)
            ->map(function (Collection $items) {
                /** @var DoctorSchedule $first */
                $first = $items->first();
                $doctor = $first->doctor;

                return [
                    'id' => (int) $doctor?->user_id,
                    'name' => $doctor?->displayName() ?: 'Dokter',
                    'time' => $items->map(fn (DoctorSchedule $schedule) => $schedule->timeRange())->unique()->implode(', '),
                    'room_id' => $first->room_id ? (int) $first->room_id : null,
                ];
            })
            ->values();
    }

    public function defaultRoomId(int $userId, int $branchId, string $date): ?int
    {
        $doctor = Doctor::query()->active()->where('user_id', $userId)->first();

        if (! $doctor) {
            return null;
        }

        $roomId = $doctor->schedules()
            ->active()
            ->forBranch($branchId)
            ->onDate($date)
            ->whereNotNull('room_id')
            ->orderBy('start_time')
            ->value('room_id');

        return $roomId ? (int) $roomId : null;
    }

    public function assertDoctorCanServe(int $userId, int $branchId, string $date, ?int $roomId): void
    {
        if (! $roomId) {
            return;
        }

        $scheduledIds = $this->doctorsFor($branchId, $date, $roomId)->pluck('id');

        if ($scheduledIds->isEmpty() || $scheduledIds->contains($userId)) {
            return;
        }

        throw ValidationException::withMessages([
            'doctor_id' => 'Dokter tidak praktik di poli ini pada tanggal tersebut.',
        ]);
    }
}
