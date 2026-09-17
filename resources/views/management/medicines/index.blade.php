@extends('layouts.app')

@section('title', 'Obat')

@section('content')
    @php
        $breadcrumb = ['Obat' => route('management.medicines.index')];
        if (auth()->user()?->can('clinic.view')) {
            $breadcrumb = ['Master Data' => route('management.clinic')] + $breadcrumb;
        }
    @endphp
    <x-page-header title="Obat" :breadcrumb="$breadcrumb" />
    <x-alert />
    <x-card title="Daftar Obat">
        <x-slot:header-actions>
            @can('create', App\Models\Medicine::class)
                <x-button href="{{ route('management.medicines.create') }}" icon="bx bx-plus">Tambah Obat</x-button>
            @endcan
        </x-slot:header-actions>
        <x-data-table id="medicines-table" :ajax="route('management.medicines.data')" :columns="$columns" :order="[[6, 'desc']]" />
    </x-card>
@endsection
