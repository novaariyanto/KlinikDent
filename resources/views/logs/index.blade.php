@extends('layouts.app')

@section('title', 'Activity Logs')

@section('content')
    <x-page-header title="Activity Logs" :breadcrumb="['System' => null, 'Activity Logs' => route('logs.index')]" />

    <x-alert />

    <x-card title="Activity Log">
        <x-slot:header-actions>
            @can('logs.delete')
                <form action="{{ route('logs.clear') }}" method="POST"
                    onsubmit="return confirm('Clear all activity logs? This cannot be undone.')">
                    @csrf
                    <x-button type="submit" variant="danger" icon="bx bx-trash">Clear All</x-button>
                </form>
            @endcan
        </x-slot:header-actions>

        <x-data-table
            id="logs-table"
            :ajax="route('logs.data')"
            :columns="$columns"
            :order="[[1, 'desc']]"
        />
    </x-card>
@endsection
