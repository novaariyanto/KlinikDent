@extends('layouts.app')

@section('title', 'Ruangan')

@section('content')
    <x-page-header title="Ruangan" :breadcrumb="['Master Data' => route('management.clinic'), 'Ruangan' => route('management.rooms.index')]" />
    <x-alert />
    <x-card title="Daftar Ruangan">
        <x-slot:header-actions>
            @can('create', App\Models\Room::class)
                <x-button href="{{ route('management.rooms.create') }}" icon="bx bx-plus">Tambah Ruangan</x-button>
            @endcan
        </x-slot:header-actions>
        <x-data-table id="rooms-table" :ajax="route('management.rooms.data')" :columns="$columns" :order="[[5, 'desc']]" />
    </x-card>
@endsection
