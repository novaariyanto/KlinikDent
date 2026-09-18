@extends('layouts.app')

@section('title', 'Aktivitas User')

@section('content')
    <x-page-header title="Aktivitas User" :breadcrumb="['Users' => null, 'Aktivitas User' => route('saas.users.activity')]" />
    <x-alert />
    <x-card title="Log pengguna">
        @include('saas.partials.activity-rows', ['logs' => $logs])
        {{ $logs->links() }}
    </x-card>
@endsection
