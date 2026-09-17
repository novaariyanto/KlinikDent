@extends('layouts.app')

@section('title', 'Edit Penjamin')

@section('content')
    <x-page-header title="Edit Penjamin" :breadcrumb="['Penjamin' => route('management.payers.index'), 'Edit' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Penjamin">
                <form method="POST" action="{{ route('management.payers.update', $payer) }}" novalidate>
                    @csrf
                    @method('PUT')
                    @include('management.payers.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('management.payers.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
