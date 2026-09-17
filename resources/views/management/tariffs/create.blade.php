@extends('layouts.app')

@section('title', 'Tambah Tarif')

@section('content')
    <x-page-header title="Tambah Tarif" :breadcrumb="['Tarif' => route('management.tariffs.index'), 'Tambah' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Tarif">
                <form method="POST" action="{{ route('management.tariffs.store') }}" novalidate>
                    @csrf
                    @include('management.tariffs.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Save</x-button>
                        <x-button href="{{ route('management.tariffs.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
