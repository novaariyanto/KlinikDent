@extends('layouts.app')

@section('title', 'Detail Resep')

@section('content')
    <x-page-header title="Resep #{{ $prescription->id }}" :breadcrumb="['Resep Masuk' => route('pharmacy.prescriptions.incoming'), 'Detail' => null]" />
    <x-alert />
    <x-card title="Resep">
        <p class="mb-1"><strong>Pasien:</strong> {{ $prescription->visit?->patient?->name }}</p>
        <p class="mb-1"><strong>Cabang:</strong> {{ $prescription->visit?->branch?->name ?: '-' }}</p>
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
                    @if ($prescription->isSent())
                        <th>Stok cabang</th>
                        <th>Batch FEFO</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($prescription->items as $item)
                    @php $info = $availability[$item->id] ?? null; @endphp
                    <tr class="{{ $info && ! $info['enough'] ? 'table-danger' : '' }}">
                        <td>{{ $item->medicine?->name }}</td>
                        <td>{{ $item->dosage ?: '-' }}</td>
                        <td>{{ $item->frequency ?: '-' }}</td>
                        <td>{{ $item->duration ?: '-' }}</td>
                        <td>{{ $item->quantity }}</td>
                        @if ($prescription->isSent())
                            <td>
                                {{ $info['available'] ?? 0 }}
                                @if ($info && ! $info['enough'])
                                    <div class="text-danger font-size-13">Stok tidak cukup</div>
                                @endif
                            </td>
                            <td>
                                @forelse ($info['allocations'] ?? [] as $allocation)
                                    <div>{{ $allocation['stock']->batch_number }} ({{ $allocation['quantity'] }}) — exp {{ $allocation['stock']->expired_date?->format('d M Y') }}</div>
                                @empty
                                    <span class="text-muted">Tidak ada batch usable</span>
                                @endforelse
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-3 d-flex gap-2">
            <x-button href="{{ route('pharmacy.prescriptions.incoming') }}" variant="secondary">Kembali</x-button>
            @if ($prescription->isSent() && auth()->user()?->can('prescription.update'))
                <form action="{{ route('pharmacy.prescriptions.fulfill', $prescription) }}" method="POST" onsubmit="return confirm('Serahkan resep dan potong stok FEFO?')">
                    @csrf
                    <x-button type="submit" variant="success" icon="bx bx-check">Proses &amp; Serahkan</x-button>
                </form>
            @endif
        </div>
    </x-card>
@endsection
