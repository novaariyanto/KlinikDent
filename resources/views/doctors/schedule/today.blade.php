@extends('layouts.app')

@section('title', 'Jadwal Hari Ini')

@section('content')
    <x-page-header title="Jadwal Hari Ini — {{ $weekday->label() }}" :breadcrumb="['Jadwal' => route('doctors.schedule.today')]" />
    <x-alert />
    <x-card title="Tenaga medis yang praktik hari ini">
        <x-slot:header-actions>
            @can('create', App\Models\DoctorSchedule::class)
                <x-button href="{{ route('doctors.schedule.create', ['weekday' => $weekday->value]) }}" icon="bx bx-plus">Tambah Jadwal</x-button>
            @endcan
            @can('schedule.view')
                <x-button href="{{ route('doctors.schedule') }}" variant="secondary">Mingguan</x-button>
            @endcan
        </x-slot:header-actions>
        <x-table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Peran</th>
                    <th>Jam</th>
                    <th>Cabang</th>
                    <th>Poli</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($schedules as $schedule)
                    <tr>
                        <td>{{ $schedule->doctor?->displayName() }}</td>
                        <td>{{ $schedule->doctor?->roleLabel() }}</td>
                        <td>{{ $schedule->timeRange() }}</td>
                        <td>{{ $schedule->branch?->name ?: '-' }}</td>
                        <td>{{ $schedule->room?->name ?: '-' }}</td>
                        <td class="text-end text-nowrap">
                            @include('doctors.schedule.partials.actions', ['schedule' => $schedule, 'from' => 'schedule'])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-muted">Tidak ada jadwal praktik hari ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
@endsection
