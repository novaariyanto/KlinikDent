@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
    <x-page-header title="Audit Logs" :breadcrumb="['System' => null, 'Audit Logs' => route('saas.system.audit')]" />
    <x-alert />
    <x-card title="Aktivitas platform">
        @include('saas.partials.activity-rows', ['logs' => $logs])
        {{ $logs->links() }}
    </x-card>
@endsection
