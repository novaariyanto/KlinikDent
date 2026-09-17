@extends('layouts.app')

@section('title', 'Tambah Layanan')

@section('content')
    <x-page-header title="Tambah Layanan" :breadcrumb="['Layanan' => route('management.services.index'), 'Tambah' => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Layanan">
                <form method="POST" action="{{ route('management.services.store') }}" novalidate>
                    @csrf
                    @include('management.services.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Save</x-button>
                        <x-button href="{{ route('management.services.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
