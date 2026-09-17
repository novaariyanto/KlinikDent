@extends('layouts.app')

@section('title', 'Tambah Obat')

@section('content')
    <x-page-header title="Tambah Obat" :breadcrumb="['Obat' => route('management.medicines.index'), 'Tambah' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Obat">
                <form method="POST" action="{{ route('management.medicines.store') }}" novalidate>
                    @csrf
                    @include('management.medicines.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Save</x-button>
                        <x-button href="{{ route('management.medicines.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
