@extends('layouts.app')

@section('title', 'Edit Tenaga Medis')

@section('content')
    <x-page-header title="Edit {{ $doctor->displayName() }}" :breadcrumb="['Tenaga Medis' => route('doctors.index'), 'Edit' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Profil">
                <form method="POST" action="{{ route('doctors.update', $doctor) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('doctors.partials.profile-fields')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('doctors.show', $doctor) }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
