@extends('layouts.app')

@section('title', 'Detail Tarif')

@section('content')
    <x-page-header title="Detail Tarif" :breadcrumb="['Tarif' => route('management.tariffs.index'), 'Detail' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Tarif">
                <x-table>
                    <tbody>
                        <tr><th width="180">Tindakan</th><td>{{ $tariff->procedure?->name ?: '-' }}</td></tr>
                        <tr><th>Cabang</th><td>{{ $tariff->branch?->name ?: 'Semua cabang' }}</td></tr>
                        <tr><th>Penjamin</th><td>{{ $tariff->payer?->name ?: 'Default' }}</td></tr>
                        <tr><th>Harga</th><td>Rp {{ number_format((float) $tariff->price, 0, ',', '.') }}</td></tr>
                        <tr><th>Berlaku</th><td>{{ $tariff->effective_date?->format('d M Y') }}</td></tr>
                    </tbody>
                </x-table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('update', $tariff)
                        <x-button href="{{ route('management.tariffs.edit', $tariff) }}" icon="bx bx-edit">Edit</x-button>
                    @endcan
                    <x-button href="{{ route('management.tariffs.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
