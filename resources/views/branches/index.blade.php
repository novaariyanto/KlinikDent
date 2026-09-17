@extends('layouts.app')

@section('title', 'Cabang')

@section('content')
    <x-page-header title="Cabang" :breadcrumb="['Klinik' => null, 'Cabang' => route('branches.index')]" />

    <x-alert />

    <x-card title="Daftar Cabang">
        <x-slot:header-actions>
            @can('branch.manage')
                <x-button href="{{ route('branches.create') }}" icon="bx bx-plus">Tambah Cabang</x-button>
            @endcan
        </x-slot:header-actions>

        <x-data-table
            id="branches-table"
            :ajax="route('branches.data')"
            :columns="$columns"
            :order="[[auth()->user()?->isPlatformAdmin() ? 6 : 5, 'desc']]"
        />
    </x-card>
@endsection
