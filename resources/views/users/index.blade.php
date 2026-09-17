@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <x-page-header title="Users" :breadcrumb="['Management' => null, 'Users' => route('users.index')]" />

    <x-alert />

    <x-card title="User List">
        <x-slot:header-actions>
            @can('users.create')
                <x-button href="{{ route('users.create') }}" icon="bx bx-plus">Add User</x-button>
            @endcan
        </x-slot:header-actions>

        <x-data-table
            id="users-table"
            :ajax="route('users.data')"
            :columns="$columns"
            :order="[[auth()->user()?->isPlatformAdmin() ? 7 : 5, 'desc']]"
        />
    </x-card>
@endsection
