@extends('layouts.app')

@section('title', 'Tambah Pasien')

@section('content')
    <x-page-header title="Tambah Pasien" :breadcrumb="['Pasien' => route('registration.patients'), 'Tambah' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Pasien">
                <form method="POST" action="{{ route('registration.patients.store') }}" novalidate>
                    @csrf
                    @include('registration.patients.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Save</x-button>
                        <x-button href="{{ route('registration.patients') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
