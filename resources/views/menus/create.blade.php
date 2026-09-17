@extends('layouts.app')

@section('title', 'Create Menu')

@section('content')
    <x-page-header title="Create Menu" :breadcrumb="['Menus' => route('menus.index'), 'Create' => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Menu Information">
                <form method="POST" action="{{ route('menus.store') }}" novalidate>
                    @csrf
                    @include('menus.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Save</x-button>
                        <x-button href="{{ route('menus.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
