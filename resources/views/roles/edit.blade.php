@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
    <x-page-header title="Edit Role" :breadcrumb="['Roles' => route('roles.index'), 'Edit' => null]" />

    <x-alert />

    <x-card title="Role Information">
        <form method="POST" action="{{ route('roles.update', $role) }}" novalidate>
            @csrf
            @method('PUT')
            @include('roles.partials.form')
            <div class="d-flex gap-2">
                <x-button type="submit" icon="bx bx-save">Update</x-button>
                <x-button href="{{ route('roles.index') }}" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
@endsection
