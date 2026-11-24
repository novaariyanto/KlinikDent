@extends('layouts.app')

@section('title', 'Tindakan')

@section('content')
    <x-page-header title="Tindakan" :breadcrumb="['Master Data' => route('management.clinic'), 'Tindakan' => route('management.procedures.index')]" />

    <x-alert />

    <x-card title="Daftar Tindakan">
        <x-slot:header-actions>
            @can('create', App\Models\Procedure::class)
                @php($catalogCount = count(\App\Support\Onboarding\DentalTariffCatalog::items()))
                <form method="POST" action="{{ route('management.procedures.import-catalog') }}" class="d-inline me-2" onsubmit="return confirm('Impor {{ $catalogCount }} tindakan dari master tarif KlinikDent? Kode yang sudah ada ikut diperbarui.')">
                    @csrf
                    <x-button type="submit" variant="soft-primary" icon="bx bx-upload">Impor master tarif</x-button>
                </form>
                <x-button href="{{ route('management.procedures.create') }}" icon="bx bx-plus">Tambah Tindakan</x-button>
            @endcan
        </x-slot:header-actions>

        <x-data-table
            id="procedures-table"
            :ajax="route('management.procedures.data')"
            :columns="$columns"
            :order="[[7, 'desc']]"
        />
    </x-card>
@endsection
