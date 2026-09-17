@extends('layouts.app')

@section('title', 'Pasien')

@section('content')
    <x-page-header title="Pasien" :breadcrumb="['Pendaftaran' => route('registration.index'), 'Pasien' => route('registration.patients')]" />
    <x-alert />
    <x-card title="Daftar Pasien">
        <x-slot:header-actions>
            @can('create', App\Models\Patient::class)
                <x-button href="{{ route('registration.patients.create') }}" icon="bx bx-plus">Tambah Pasien</x-button>
            @endcan
        </x-slot:header-actions>
        <x-data-table id="patients-table" :ajax="route('registration.patients.data')" :columns="$columns" :order="[[6, 'desc']]" />
    </x-card>
@endsection
