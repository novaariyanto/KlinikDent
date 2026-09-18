@extends('layouts.app')

@section('title', 'Jadwal Dokter')

@section('content')
    <x-page-header title="Jadwal Dokter" :breadcrumb="['Tenaga Medis' => route('doctors.index'), 'Jadwal' => null]" />
    <x-alert />
    <x-card title="Jadwal Praktik Mingguan">
        <x-slot:header-actions>
            @can('create', App\Models\DoctorSchedule::class)
                <x-button href="{{ route('doctors.schedule.create') }}" icon="bx bx-plus">Tambah Jadwal</x-button>
            @endcan
            <x-button href="{{ route('doctors.schedule.today') }}" variant="secondary" icon="bx bx-time-five">Hari Ini</x-button>
            @can('doctor.view')
                <x-button href="{{ route('doctors.index') }}" variant="secondary">Daftar Tenaga Medis</x-button>
            @endcan
        </x-slot:header-actions>

        @foreach ($weekdays as $day)
            @php $items = $grouped->get($day->value, collect()); @endphp
            <div class="d-flex align-items-center justify-content-between mt-3">
                <h6 class="text-uppercase text-muted mb-0">{{ $day->label() }}</h6>
                @can('create', App\Models\DoctorSchedule::class)
                    <a href="{{ route('doctors.schedule.create', ['weekday' => $day->value]) }}" class="btn btn-sm btn-soft-primary">Tambah</a>
                @endcan
            </div>
            @if ($items->isEmpty())
                <p class="text-muted small mt-2">Tidak ada jadwal.</p>
            @else
                <x-table>
                    <thead>
                        <tr>
                            <th>Tenaga Medis</th>
                            <th>Jam</th>
                            <th>Cabang</th>
                            <th>Poli</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $schedule)
                            <tr>
                                <td>
                                    @can('doctor.view')
                                        <a href="{{ route('doctors.show', $schedule->doctor) }}">{{ $schedule->doctor?->displayName() }}</a>
                                    @else
                                        {{ $schedule->doctor?->displayName() }}
                                    @endcan
                                </td>
                                <td>{{ $schedule->timeRange() }}</td>
                                <td>{{ $schedule->branch?->name ?: '-' }}</td>
                                <td>{{ $schedule->room?->name ?: '-' }}</td>
                                <td>
                                    <span class="{{ $schedule->is_active ? 'badge bg-success' : 'badge bg-danger' }}">
                                        {{ $schedule->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    @include('doctors.schedule.partials.actions', ['schedule' => $schedule, 'from' => 'schedule'])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        @endforeach
    </x-card>
@endsection
