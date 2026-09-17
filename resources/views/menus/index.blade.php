@extends('layouts.app')

@section('title', 'Menus')

@section('content')
    <x-page-header title="Menus" :breadcrumb="['Management' => null, 'Menus' => route('menus.index')]" />

    <x-alert />

    <x-card title="Menu List">
        <x-slot:header-actions>
            @can('menus.create')
                <x-button href="{{ route('menus.create') }}" icon="bx bx-plus">Add Menu</x-button>
            @endcan
        </x-slot:header-actions>

        <x-data-table
            id="menus-table"
            :ajax="route('menus.data')"
            :columns="$columns"
            :order="[[7, 'asc']]"
        />
    </x-card>
@endsection
