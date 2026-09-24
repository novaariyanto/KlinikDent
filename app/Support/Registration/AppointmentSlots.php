<?php

namespace App\Support\Registration;

use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\Support\ClinicSettings;
use Illuminate\Support\Carbon;

class AppointmentSlots
{
    public function capacity(DoctorSchedule $schedule, int $tenantId): int
    {
        $minutes = (int) abs(Carbon::parse($schedule->start_time)->diffInMinutes(Carbon::parse($schedule->end_time)));
        $slot = ClinicSettings::slotMinutes($tenantId);

        return max(1, intdiv(max($minutes, $slot), $slot));
    }

    public function booked(DoctorSchedule $schedule, string $date): int
    {
        return Queue::withoutGlobalScopes()
            ->where('doctor_schedule_id', $schedule->id)
            ->where('status', '!=', QueueStatus::Skipped->value)
            ->whereHas('visit', function ($visit) use ($date) {
                $visit->withoutGlobalScopes()
                    ->whereDate('visit_date', $date)
                    ->where('status', '!=', VisitStatus::Cancelled->value);
            })
            ->count();
    }

    public function remaining(DoctorSchedule $schedule, string $date, int $tenantId): int
    {
        return max(0, $this->capacity($schedule, $tenantId) - $this->booked($schedule, $date));
    }
}
