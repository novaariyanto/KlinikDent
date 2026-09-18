@extends('layouts.app')

@section('title', 'Jadwal Saya')

@section('content')
    <x-page-header title="Jadwal Saya" :breadcrumb="['Jadwal Saya' => null]" />
    <x-alert />
    <x-card title="{{ $doctor?->displayName() ?: 'Jadwal praktik' }}">
        @unless ($doctor)
            <p class="text-muted mb-0">Profil tenaga medis belum dibuat. Hubungi owner/manager klinik.</p>
        @else
            @foreach ($weekdays as $day)
                @php $items = $schedules->get($day->value, collect()); @endphp
                <h6 class="text-uppercase text-muted mt-3">{{ $day->label() }}</h6>
                @if ($items->isEmpty())
                    <p class="text-muted small">Libur / tidak ada jadwal.</p>
                @else
                    <ul class="list-unstyled mb-0">
                        @foreach ($items as $schedule)
                            <li class="mb-1">
                                <strong>{{ $schedule->timeRange() }}</strong>
                                — {{ $schedule->branch?->name ?: '-' }}
                                @if ($schedule->room)
                                    ({{ $schedule->room->name }})
                                @endif
                                @unless ($schedule->is_active)
                                    <span class="badge bg-danger">Nonaktif</span>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
        @endunless
    </x-card>
@endsection
