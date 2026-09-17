@extends('layouts.app')

@section('title', 'Klinik Baru')

@section('content')
    <x-page-header title="Klinik Baru" :breadcrumb="['Semua Klinik' => route('saas.tenants.index'), 'Klinik Baru' => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Klinik">
                <form method="POST" action="{{ route('saas.tenants.store') }}" novalidate>
                    @csrf
                    @include('saas.tenants.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Save</x-button>
                        <x-button href="{{ route('saas.tenants.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
