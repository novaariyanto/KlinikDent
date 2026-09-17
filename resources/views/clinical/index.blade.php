@extends('layouts.app')

@section('title', $meta['title'])

@section('content')
    <x-page-header :title="$meta['title']" :breadcrumb="['Pelayanan' => route('examinations.index'), $meta['title'] => null]" />
    <x-alert />
    <x-card :title="$meta['title'].' — Kunjungan Hari Ini'">
        <x-table>
            <thead>
                <tr>
                    <th>Antrean</th>
                    <th>Pasien</th>
                    <th>Dokter</th>
                    <th>Poli</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($visits as $visit)
                    <tr>
                        <td>{{ $visit->queue?->displayNumber() ?: '-' }}</td>
                        <td>
                            <div class="fw-semibold">{{ $visit->patient?->name }}</div>
                            <div class="text-muted font-size-13">{{ $visit->patient?->medical_record_number }}</div>
                        </td>
                        <td>{{ $visit->doctor?->name ?: '-' }}</td>
                        <td>{{ $visit->room?->name ?: '-' }}</td>
                        <td><span class="{{ $visit->status->badgeClass() }}">{{ $visit->status->label() }}</span></td>
                        <td class="text-end">
                            <x-button href="{{ route('care.show', ['visit' => $visit, 'tab' => $meta['tab']]) }}" variant="primary" icon="bx bx-plus-medical">Buka Pelayanan</x-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-muted">Belum ada kunjungan hari ini. Buka antrean untuk memanggil pasien.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($visits->hasPages())
            <div class="mt-3">{{ $visits->links() }}</div>
        @endif
    </x-card>
@endsection
