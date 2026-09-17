@extends('layouts.app')

@section('title', 'Semua Klinik')

@section('content')
    <x-page-header title="Semua Klinik" :breadcrumb="['Tenant / Klinik' => null, 'Semua Klinik' => route('saas.tenants.index')]" />

    <x-alert />

    <x-card title="Daftar Klinik">
        <x-slot:header-actions>
            @can('tenant.create')
                <x-button href="{{ route('saas.tenants.create') }}" icon="bx bx-plus">Klinik Baru</x-button>
            @endcan
        </x-slot:header-actions>

        <x-data-table
            id="tenants-table"
            :ajax="route('saas.tenants.data')"
            :columns="$columns"
            :order="[[6, 'desc']]"
        />
    </x-card>
@endsection
