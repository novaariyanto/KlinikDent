@extends('layouts.app')

@section('title', 'Create Role')

@section('content')
    <x-page-header title="Create Role" :breadcrumb="['Roles' => route('roles.index'), 'Create' => null]" />

    <x-alert />

    <x-card title="Role Information">
        <form method="POST" action="{{ route('roles.store') }}" novalidate>
            @csrf
            @include('roles.partials.form')
            <div class="d-flex gap-2">
                <x-button type="submit" icon="bx bx-save">Save</x-button>
                <x-button href="{{ route('roles.index') }}" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
@endsection
