@extends('layouts.app')

@section('title', 'Detail Resep')

@section('content')
    <x-page-header title="Resep #{{ $prescription->id }}" :breadcrumb="['Resep Masuk' => route('pharmacy.prescriptions.incoming'), 'Detail' => null]" />
    <x-alert />
    <x-card title="Resep">
        <p class="mb-1"><strong>Pasien:</strong> {{ $prescription->visit?->patient?->name }}</p>
        <p class="mb-1"><strong>Dokter:</strong> {{ $prescription->doctor?->name ?: '-' }}</p>
        <p class="mb-3">
            <strong>Status:</strong>
            <span class="{{ $prescription->status->badgeClass() }}">{{ $prescription->status->label() }}</span>
        </p>
        <x-table>
            <thead>
                <tr>
                    <th>Obat</th>
                    <th>Dosis</th>
                    <th>Frekuensi</th>
                    <th>Durasi</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prescription->items as $item)
                    <tr>
                        <td>{{ $item->medicine?->name }}</td>
                        <td>{{ $item->dosage ?: '-' }}</td>
                        <td>{{ $item->frequency ?: '-' }}</td>
                        <td>{{ $item->duration ?: '-' }}</td>
                        <td>{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-3">
            <x-button href="{{ route('pharmacy.prescriptions.incoming') }}" variant="secondary">Kembali</x-button>
        </div>
    </x-card>
@endsection
