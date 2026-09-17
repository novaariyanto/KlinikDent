@extends('layouts.app')

@section('title', 'Pendaftaran')

@section('content')
    <x-page-header title="Pendaftaran" :breadcrumb="['Pendaftaran' => route('registration.index')]" />
    <x-alert />

    <div class="row">
        @foreach ([
            ['label' => 'Kunjungan Hari Ini', 'value' => $stats['visits_today'], 'icon' => 'bx bx-calendar-check', 'color' => 'primary'],
            ['label' => 'Menunggu', 'value' => $stats['waiting'], 'icon' => 'bx bx-time-five', 'color' => 'warning'],
            ['label' => 'Dilayani', 'value' => $stats['in_service'], 'icon' => 'bx bx-plus-medical', 'color' => 'info'],
            ['label' => 'Selesai', 'value' => $stats['done'], 'icon' => 'bx bx-check-circle', 'color' => 'success'],
        ] as $stat)
            <div class="col-md-6 col-xl-3">
                <div class="card mini-stats-wid">
                    <div class="card-body">
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <p class="text-muted fw-medium">{{ $stat['label'] }}</p>
                                <h4 class="mb-0">{{ $stat['value'] }}</h4>
                            </div>
                            <div class="avatar-sm rounded-circle bg-{{ $stat['color'] }} align-self-center mini-stat-icon">
                                <span class="avatar-title rounded-circle bg-{{ $stat['color'] }}"><i class="{{ $stat['icon'] }} font-size-24"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-4">
            <x-card title="Aksi Cepat">
                <div class="d-grid gap-2">
                    @can('create', App\Models\Visit::class)
                        <x-button href="{{ route('registration.new') }}" icon="bx bx-plus">Pendaftaran Baru</x-button>
                    @endcan
                    @can('viewAny', App\Models\Patient::class)
                        <x-button href="{{ route('registration.patients') }}" variant="secondary" icon="bx bx-user">Data Pasien</x-button>
                    @endcan
                    @can('viewAny', App\Models\Queue::class)
                        <x-button href="{{ route('queue.today') }}" variant="info" icon="bx bx-list-ol">Antrean Hari Ini</x-button>
                    @endcan
                </div>
            </x-card>
        </div>
        <div class="col-lg-8">
            <x-card title="Kunjungan Terbaru Hari Ini">
                <x-table>
                    <thead>
                        <tr>
                            <th>Antrean</th>
                            <th>Pasien</th>
                            <th>Dokter</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent as $visit)
                            <tr>
                                <td>{{ $visit->queue?->displayNumber() ?: '-' }}</td>
                                <td><a href="{{ route('registration.visits.show', $visit) }}">{{ $visit->patient?->name }}</a></td>
                                <td>{{ $visit->doctor?->name ?: '-' }}</td>
                                <td><span class="{{ $visit->status->badgeClass() }}">{{ $visit->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">Belum ada kunjungan hari ini.</td></tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>
        </div>
    </div>
@endsection
