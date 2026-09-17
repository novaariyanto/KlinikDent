@extends('layouts.app')

@section('title', 'Tambah Penjamin')

@section('content')
    <x-page-header title="Tambah Penjamin" :breadcrumb="['Penjamin' => route('management.payers.index'), 'Tambah' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Penjamin">
                <form method="POST" action="{{ route('management.payers.store') }}" novalidate>
                    @csrf
                    @include('management.payers.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Save</x-button>
                        <x-button href="{{ route('management.payers.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
