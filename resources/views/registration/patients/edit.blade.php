@extends('layouts.app')

@section('title', 'Edit Pasien')

@section('content')
    <x-page-header title="Edit Pasien" :breadcrumb="['Pasien' => route('registration.patients'), 'Edit' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Pasien">
                <form method="POST" action="{{ route('registration.patients.update', $patient) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('registration.patients.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('registration.patients.show', $patient) }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
