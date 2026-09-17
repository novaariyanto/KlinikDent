@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Antrean' => route('queue.today'), $title => null]" />
    <x-alert />
    <x-card :title="$title">
        <x-slot:header-actions>
            @can('viewAny', App\Models\Queue::class)
                <x-button href="{{ route('queue.monitor') }}" variant="info" icon="bx bx-tv">Monitor</x-button>
            @endcan
        </x-slot:header-actions>
        <x-data-table
            id="queues-table"
            :ajax="route('queue.data', ['filter' => $filter])"
            :columns="$columns"
            :order="[[1, 'asc']]"
        />
    </x-card>
@endsection
