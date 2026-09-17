@extends('layouts.app')

@section('title', 'Resep Masuk')

@section('content')
    <x-page-header title="Resep Masuk" :breadcrumb="['Farmasi' => route('pharmacy.prescriptions.incoming'), 'Resep Masuk' => null]" />
    <x-alert />
    <x-card title="Resep Terkirim">
        <p class="text-muted">Resep yang sudah dikirim dokter. Pemenuhan stok akan dikerjakan pada modul farmasi berikutnya.</p>
        <x-table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pasien</th>
                    <th>Dokter</th>
                    <th>Item</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prescriptions as $prescription)
                    <tr>
                        <td>{{ $prescription->updated_at?->format('d M Y H:i') }}</td>
                        <td>{{ $prescription->visit?->patient?->name }}</td>
                        <td>{{ $prescription->doctor?->name ?: $prescription->visit?->doctor?->name }}</td>
                        <td>{{ $prescription->items->count() }}</td>
                        <td class="text-end">
                            <x-button href="{{ route('pharmacy.prescriptions.show', $prescription) }}" variant="light">Lihat</x-button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Belum ada resep masuk.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($prescriptions->hasPages())
            <div class="mt-3">{{ $prescriptions->links() }}</div>
        @endif
    </x-card>
@endsection
