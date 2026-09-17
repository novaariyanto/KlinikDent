@extends('layouts.app')

@section('title', 'Edit Cabang')

@section('content')
    <x-page-header title="Edit Cabang" :breadcrumb="['Cabang' => route('branches.index'), 'Edit' => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Cabang">
                <form method="POST" action="{{ route('branches.update', $branch) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('branches.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('branches.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
