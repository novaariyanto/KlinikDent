@extends('layouts.app')

@section('title', 'Tambah Tenaga Medis')

@section('content')
    <x-page-header title="Tambah Tenaga Medis" :breadcrumb="['Tenaga Medis' => route('doctors.index'), 'Tambah' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Tenaga Medis">
                <form method="POST" action="{{ route('doctors.store') }}" novalidate>
                    @csrf
                    @include('doctors.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Save</x-button>
                        <x-button href="{{ route('doctors.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
