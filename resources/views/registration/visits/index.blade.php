@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Pendaftaran' => route('registration.index'), $title => null]" />
    <x-alert />
    <x-card :title="$title">
        <x-slot:header-actions>
            @can('create', App\Models\Visit::class)
                <x-button href="{{ route('registration.new') }}" icon="bx bx-plus">Pendaftaran Baru</x-button>
            @endcan
        </x-slot:header-actions>
        <x-data-table
            id="visits-table"
            :ajax="route('registration.visits.data', $todayOnly ? ['today' => 1] : [])"
            :columns="$columns"
            :order="[[1, 'desc']]"
        />
    </x-card>
@endsection
