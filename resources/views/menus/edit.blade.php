@extends('layouts.app')

@section('title', 'Edit Menu')

@section('content')
    <x-page-header title="Edit Menu" :breadcrumb="['Menus' => route('menus.index'), 'Edit' => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Menu Information">
                <form method="POST" action="{{ route('menus.update', $menu) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('menus.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('menus.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
