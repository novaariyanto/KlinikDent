@extends('layouts.app')

@section('title', 'Edit Ruangan')

@section('content')
    <x-page-header title="Edit Ruangan" :breadcrumb="['Ruangan' => route('management.rooms.index'), 'Edit' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Ruangan">
                <form method="POST" action="{{ route('management.rooms.update', $room) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('management.rooms.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('management.rooms.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
