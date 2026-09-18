@extends('layouts.app')

@section('title', 'Edit Jadwal')

@section('content')
    @php
        $from = $from ?? request('from', 'doctor');
        $back = $from === 'schedule'
            ? route('doctors.schedule')
            : route('doctors.show', $schedule->doctor_id);
        $backLabel = $from === 'schedule' ? 'Jadwal Dokter' : ($schedule->doctor?->displayName() ?: 'Tenaga Medis');
    @endphp
    <x-page-header title="Edit Jadwal {{ $schedule->doctor?->displayName() }}" :breadcrumb="[$backLabel => $back, 'Edit Jadwal' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Jadwal Praktik">
                <form method="POST" action="{{ route('doctors.schedules.update', $schedule) }}" novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="from" value="{{ $from }}">
                    @include('doctors.partials.schedule-form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ $back }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
