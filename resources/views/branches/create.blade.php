@extends('layouts.app')

@section('title', 'Tambah Cabang')

@section('content')
    <x-page-header title="Tambah Cabang" :breadcrumb="['Cabang' => route('branches.index'), 'Tambah' => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Cabang">
                <form method="POST" action="{{ route('branches.store') }}" novalidate>
                    @csrf
                    @include('branches.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Save</x-button>
                        <x-button href="{{ route('branches.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
