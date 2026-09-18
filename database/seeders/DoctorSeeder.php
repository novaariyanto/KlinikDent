<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\RoomType;
use App\Enums\Weekday;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->withoutGlobalScopes()
            ->role(RoleName::medicalStaffValues())
            ->whereNotNull('tenant_id')
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $doctor = Doctor::withoutGlobalScopes()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'tenant_id' => $user->tenant_id,
                    'sip' => $user->hasRole(RoleName::Dentist) ? 'SIP/'.$user->id.'/'.now()->year : null,
                    'str' => $user->hasRole(RoleName::Dentist) ? 'STR/'.$user->id : null,
                    'specialization' => $user->hasRole(RoleName::Dentist) ? 'Dokter Gigi Umum' : null,
                    'is_active' => true,
                ]
            );

            if (! $user->branch_id) {
                continue;
            }

            $rooms = Room::withoutGlobalScopes()
                ->where('branch_id', $user->branch_id)
                ->where('type', RoomType::Poli)
                ->orderBy('name')
                ->get();

            $preferred = $user->hasRole(RoleName::Dentist) ? 'Poli Gigi 1' : 'Poli Umum';
            $room = $rooms->firstWhere('name', $preferred)
                ?? $rooms->firstWhere('name', 'Poli Gigi')
                ?? $rooms->first();

            foreach ([Weekday::Monday, Weekday::Tuesday, Weekday::Wednesday, Weekday::Thursday, Weekday::Friday] as $day) {
                DoctorSchedule::withoutGlobalScopes()->updateOrCreate(
                    [
                        'doctor_id' => $doctor->id,
                        'branch_id' => $user->branch_id,
                        'weekday' => $day->value,
                        'start_time' => '08:00:00',
                    ],
                    [
                        'tenant_id' => $user->tenant_id,
                        'room_id' => $room?->id,
                        'end_time' => '16:00:00',
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
