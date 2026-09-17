@extends('layouts.app')

@section('title', 'Tambah Tindakan')

@section('content')
    <x-page-header title="Tambah Tindakan" :breadcrumb="['Tindakan' => route('management.procedures.index'), 'Tambah' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Tindakan">
                <form method="POST" action="{{ route('management.procedures.store') }}" novalidate>
                    @csrf
                    @include('management.procedures.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Save</x-button>
                        <x-button href="{{ route('management.procedures.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
