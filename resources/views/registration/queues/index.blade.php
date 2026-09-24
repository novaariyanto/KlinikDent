@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Antrean' => route('queue.today'), $title => null]" />
    <x-alert />
    @include('registration.queues.partials.nav')
    <x-card :title="$title">
        <x-data-table
            id="queues-table"
            :ajax="route('queue.data', ['filter' => $filter])"
            :columns="$columns"
            :order="[[1, 'asc']]"
        />
    </x-card>
@endsection
