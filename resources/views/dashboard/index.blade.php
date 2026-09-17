@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Dashboard' => route('dashboard'), $title => null]" />

    @if ($registrationStats)
        <div class="row">
            @foreach ([
                ['label' => 'Kunjungan Hari Ini', 'value' => $registrationStats['visits_today'], 'icon' => 'bx bx-calendar-check', 'color' => 'primary'],
                ['label' => 'Antrean Menunggu', 'value' => $registrationStats['waiting'], 'icon' => 'bx bx-time-five', 'color' => 'warning'],
                ['label' => 'Sedang Dilayani', 'value' => $registrationStats['in_service'], 'icon' => 'bx bx-plus-medical', 'color' => 'info'],
                ['label' => 'Selesai', 'value' => $registrationStats['done'], 'icon' => 'bx bx-check-circle', 'color' => 'success'],
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
        <div class="mb-3">
            @can('create', App\Models\Visit::class)
                <x-button href="{{ route('registration.new') }}" icon="bx bx-plus">Pendaftaran Baru</x-button>
            @endcan
            <x-button href="{{ route('queue.today') }}" variant="secondary" icon="bx bx-list-ol">Antrean</x-button>
        </div>
    @else
        <div class="row">
            <div class="col-xl-8">
                <x-module-placeholder
                    :icon="$icon"
                    :title="$title"
                    :description="$description"
                />
            </div>
            <div class="col-xl-4">
                <x-card title="Akun">
                    <p class="mb-1"><strong>Nama:</strong> {{ auth()->user()->name }}</p>
                    <p class="mb-1"><strong>Email:</strong> {{ auth()->user()->email }}</p>
                    <p class="mb-0">
                        <strong>Role:</strong>
                        {{ auth()->user()->roles->pluck('name')->join(', ') ?: '-' }}
                    </p>
                </x-card>
            </div>
        </div>
    @endif
@endsection
