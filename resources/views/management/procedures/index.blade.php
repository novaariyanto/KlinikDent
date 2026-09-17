@extends('layouts.app')

@section('title', 'Tindakan')

@section('content')
    <x-page-header title="Tindakan" :breadcrumb="['Master Data' => route('management.clinic'), 'Tindakan' => route('management.procedures.index')]" />

    <x-alert />

    <x-card title="Daftar Tindakan">
        <x-slot:header-actions>
            @can('create', App\Models\Procedure::class)
                <x-button href="{{ route('management.procedures.create') }}" icon="bx bx-plus">Tambah Tindakan</x-button>
            @endcan
        </x-slot:header-actions>

        <x-data-table
            id="procedures-table"
            :ajax="route('management.procedures.data')"
            :columns="$columns"
            :order="[[6, 'desc']]"
        />
    </x-card>
@endsection
