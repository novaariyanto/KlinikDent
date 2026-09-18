@extends('layouts.app')

@section('title', 'Tambah Jadwal')

@section('content')
    <x-page-header title="Tambah Jadwal" :breadcrumb="['Jadwal Dokter' => route('doctors.schedule'), 'Tambah' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Jadwal Praktik">
                <form method="POST" action="{{ route('doctors.schedule.store') }}" novalidate>
                    @csrf
                    <input type="hidden" name="from" value="schedule">
                    @php
                        $doctorOptions = collect($doctors ?? [])->mapWithKeys(fn ($doctor) => [$doctor->id => $doctor->displayName().($doctor->roleLabel() !== '-' ? ' ('.$doctor->roleLabel().')' : '')])->all();
                    @endphp
                    <div class="row">
                        <div class="col-md-6">
                            <x-select name="doctor_id" label="Tenaga Medis" :options="$doctorOptions" :selected="old('doctor_id', $selectedDoctorId)" placeholder="Pilih tenaga medis" required />
                        </div>
                    </div>
                    @include('doctors.partials.schedule-form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-plus">Tambah Jadwal</x-button>
                        <x-button href="{{ route('doctors.schedule') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
