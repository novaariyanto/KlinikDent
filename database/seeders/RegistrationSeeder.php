<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Models\Branch;
use App\Models\DoctorSchedule;
use App\Models\Payer;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Registration\VisitRegistrar;
use Illuminate\Database\Seeder;

class RegistrationSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(VisitRegistrar::class);

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($registrar) {
            $branch = Branch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->first();
            $payer = Payer::query()->where('tenant_id', $tenant->id)->orderBy('id')->first();
            $actor = User::query()->where('tenant_id', $tenant->id)->whereNotNull('branch_id')->orderBy('id')->first();
            $dentist = User::query()->doctors()->where('tenant_id', $tenant->id)->first();
            $room = $branch
                ? Room::withoutGlobalScopes()->where('branch_id', $branch->id)->orderBy('id')->first()
                : null;

            if ($dentist && $branch) {
                $scheduledRoomId = DoctorSchedule::withoutGlobalScopes()
                    ->where('branch_id', $branch->id)
                    ->whereNotNull('room_id')
                    ->whereHas('doctor', fn ($doctor) => $doctor->where('user_id', $dentist->id))
                    ->value('room_id');

                if ($scheduledRoomId) {
                    $room = Room::withoutGlobalScopes()->find($scheduledRoomId) ?: $room;
                }
            }

            if (! $branch || ! $payer || ! $actor) {
                return;
            }

            $registrar->register($actor, [
                'branch_id' => $branch->id,
                'payer_id' => $payer->id,
                'doctor_id' => $dentist?->id,
                'room_id' => $room?->id,
                'name' => 'Siti Rahma',
                'nik' => null,
                'dob' => '1990-05-12',
                'gender' => Gender::Female->value,
                'phone' => '081234000001',
                'address' => 'Jl. Melati No. 1',
            ]);

            $registrar->register($actor, [
                'branch_id' => $branch->id,
                'payer_id' => $payer->id,
                'doctor_id' => $dentist?->id,
                'room_id' => $room?->id,
                'name' => 'Budi Santoso',
                'phone' => '081234000002',
                'gender' => Gender::Male->value,
            ]);
        });
    }
}
