@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <x-page-header title="Edit User" :breadcrumb="['Users' => route('users.index'), 'Edit' => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="User Information">
                <form method="POST" action="{{ route('users.update', $user) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('users.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('users.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
