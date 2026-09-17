@extends('layouts.app')

@section('title', 'Edit Klinik')

@section('content')
    <x-page-header title="Edit Klinik" :breadcrumb="['Semua Klinik' => route('saas.tenants.index'), 'Edit' => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Klinik">
                <form method="POST" action="{{ route('saas.tenants.update', $tenant) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('saas.tenants.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('saas.tenants.show', $tenant) }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
