@extends('layouts.app')

@section('title', 'Edit Tarif')

@section('content')
    <x-page-header title="Edit Tarif" :breadcrumb="['Tarif' => route('management.tariffs.index'), 'Edit' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Tarif">
                <form method="POST" action="{{ route('management.tariffs.update', $tariff) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('management.tariffs.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('management.tariffs.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
