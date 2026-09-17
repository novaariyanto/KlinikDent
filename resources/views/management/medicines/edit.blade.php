@extends('layouts.app')

@section('title', 'Edit Obat')

@section('content')
    <x-page-header title="Edit Obat" :breadcrumb="['Obat' => route('management.medicines.index'), 'Edit' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Obat">
                <form method="POST" action="{{ route('management.medicines.update', $medicine) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('management.medicines.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('management.medicines.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
