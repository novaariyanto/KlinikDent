@extends('layouts.app')

@section('title', 'Dokter & Tenaga Medis')

@section('content')
    <x-page-header title="Dokter & Tenaga Medis" :breadcrumb="['Tenaga Medis' => route('doctors.index')]" />
    <x-alert />
    <x-card title="Daftar Tenaga Medis">
        <x-slot:header-actions>
            @can('schedule.view')
                <x-button href="{{ route('doctors.schedule') }}" variant="secondary" icon="bx bx-calendar">Jadwal</x-button>
            @endcan
            @can('create', App\Models\Doctor::class)
                <x-button href="{{ route('doctors.create') }}" icon="bx bx-plus">Tambah Tenaga Medis</x-button>
            @endcan
        </x-slot:header-actions>
        <x-data-table id="doctors-table" :ajax="route('doctors.data')" :columns="$columns" :order="[[1, 'asc']]" />
    </x-card>
@endsection
