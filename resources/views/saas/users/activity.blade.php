@extends('layouts.app')

@section('title', 'Aktivitas User')

@section('content')
    <x-page-header title="Aktivitas User" :breadcrumb="['Users' => null, 'Aktivitas User' => route('saas.users.activity')]" />
    <x-alert />
    <x-card title="Log pengguna">
        <x-data-table
            id="users-activity-table"
            :ajax="route('saas.users.activity.data')"
            :columns="$columns"
            :order="[[1, 'desc']]"
        />
    </x-card>
@endsection
