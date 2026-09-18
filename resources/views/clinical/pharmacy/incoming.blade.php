@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Farmasi' => route('pharmacy.prescriptions.incoming'), $title => null]" />
    <x-alert />
    <x-card :title="$title">
        <p class="text-muted">{{ $hint }}</p>
        <x-table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pasien</th>
                    <th>Dokter</th>
                    <th>Cabang</th>
                    <th>Item</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prescriptions as $prescription)
                    <tr>
                        <td>{{ $prescription->updated_at?->format('d M Y H:i') }}</td>
                        <td>{{ $prescription->visit?->patient?->name }}</td>
                        <td>{{ $prescription->doctor?->name ?: $prescription->visit?->doctor?->name }}</td>
                        <td>{{ $prescription->visit?->branch?->name ?: '-' }}</td>
                        <td>{{ $prescription->items->count() }}</td>
                        <td><span class="{{ $prescription->status->badgeClass() }}">{{ $prescription->status->label() }}</span></td>
                        <td class="text-end">
                            <x-button href="{{ route('pharmacy.prescriptions.show', $prescription) }}" variant="light">
                                {{ $prescription->isSent() ? 'Proses' : 'Lihat' }}
                            </x-button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($prescriptions->hasPages())
            <div class="mt-3">{{ $prescriptions->links() }}</div>
        @endif
    </x-card>
@endsection
