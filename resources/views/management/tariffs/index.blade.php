@extends('layouts.app')

@section('title', 'Tarif')

@section('content')
    <x-page-header title="Tarif" :breadcrumb="['Master Data' => route('management.clinic'), 'Tarif' => route('management.tariffs.index')]" />
    <x-alert />

    <x-card title="Daftar Tarif">
        <x-slot:header-actions>
            @can('create', App\Models\Tariff::class)
                <x-button href="{{ route('management.tariffs.create') }}" icon="bx bx-plus">Tambah Tarif</x-button>
            @endcan
        </x-slot:header-actions>
        <x-data-table id="tariffs-table" :ajax="route('management.tariffs.data')" :columns="$columns" :order="[[6, 'desc']]" />
    </x-card>
@endsection
