@extends('layouts.app')

@section('title', 'Create User')

@section('content')
    <x-page-header title="Create User" :breadcrumb="['Users' => route('users.index'), 'Create' => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="User Information">
                <form method="POST" action="{{ route('users.store') }}" novalidate>
                    @csrf
                    @include('users.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Save</x-button>
                        <x-button href="{{ route('users.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
