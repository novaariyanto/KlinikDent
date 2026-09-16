@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
    <x-page-header title="Roles & Permissions" :breadcrumb="['Management' => null, 'Roles' => route('roles.index')]" />

    <x-alert />

    <x-card title="Role List">
        <x-slot:header-actions>
            @can('roles.create')
                <x-button href="{{ route('roles.create') }}" icon="bx bx-plus">Add Role</x-button>
            @endcan
        </x-slot:header-actions>

        <x-data-table
            id="roles-table"
            :ajax="route('roles.data')"
            :columns="$columns"
            :order="[[3, 'desc']]"
        />
    </x-card>
@endsection
