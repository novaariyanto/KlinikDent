@extends('layouts.app')

@section('title', 'Layanan')

@section('content')
    <x-page-header title="Layanan" :breadcrumb="['Master Data' => route('management.clinic'), 'Layanan' => route('management.services.index')]" />

    <x-alert />

    <x-card title="Daftar Layanan">
        <x-slot:header-actions>
            @can('create', App\Models\Service::class)
                <x-button href="{{ route('management.services.create') }}" icon="bx bx-plus">Tambah Layanan</x-button>
            @endcan
        </x-slot:header-actions>

        <x-data-table
            id="services-table"
            :ajax="route('management.services.data')"
            :columns="$columns"
            :order="[[4, 'desc']]"
        />
    </x-card>
@endsection
