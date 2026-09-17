@extends('layouts.app')

@section('title', 'Riwayat '.$patient->name)

@section('content')
    <x-page-header :title="'Riwayat '.$patient->name" :breadcrumb="['Riwayat Pasien' => route('patients.history'), $patient->name => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-4">
            <x-card title="Pasien">
                <p class="mb-1"><strong>No. RM:</strong> {{ $patient->medical_record_number }}</p>
                <p class="mb-1"><strong>Nama:</strong> {{ $patient->name }}</p>
                <p class="mb-0"><strong>Telepon:</strong> {{ $patient->phone ?: '-' }}</p>
            </x-card>
        </div>
        <div class="col-lg-8">
            @forelse ($visits as $visit)
                <x-card :title="$visit->visit_date?->format('d M Y').' — '.($visit->doctor?->name ?: 'Tanpa dokter')">
                    <p class="mb-2">
                        <span class="{{ $visit->status->badgeClass() }}">{{ $visit->status->label() }}</span>
                        @if ($visit->queue)
                            <span class="ms-1 text-muted">Antrean {{ $visit->queue->displayNumber() }}</span>
                        @endif
                    </p>
                    <p class="mb-1"><strong>Keluhan:</strong> {{ $visit->medicalRecord?->chief_complaint ?: '-' }}</p>
                    <p class="mb-1"><strong>Diagnosis:</strong> {{ $visit->diagnoses->map(fn ($item) => $item->code.' '.$item->description)->join('; ') ?: '-' }}</p>
                    <p class="mb-1"><strong>Tindakan:</strong> {{ $visit->procedureRecords->pluck('procedure.name')->filter()->join(', ') ?: '-' }}</p>
                    <p class="mb-3"><strong>Resep:</strong> {{ $visit->prescriptions->flatMap->items->pluck('medicine.name')->filter()->unique()->join(', ') ?: '-' }}</p>
                    @can('view', $visit)
                        <x-button href="{{ route('care.show', $visit) }}" variant="primary" icon="bx bx-plus-medical">Buka Pelayanan</x-button>
                    @endcan
                </x-card>
            @empty
                <x-card><p class="text-muted mb-0">Belum ada kunjungan.</p></x-card>
            @endforelse
        </div>
    </div>
@endsection
