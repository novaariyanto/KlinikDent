@extends('layouts.app')

@section('title', 'Detail Resep')

@section('content')
    <x-page-header title="Resep #{{ $prescription->id }}" :breadcrumb="['Resep Masuk' => route('pharmacy.prescriptions.incoming'), 'Detail' => null]" />
    <x-alert />
    @include('clinical.pharmacy.partials.nav', ['current' => $prescription->isSent() ? 'pharmacy.prescriptions.incoming' : 'pharmacy.prescriptions.completed'])
    <x-card title="Resep">
        <p class="mb-1"><strong>Pasien:</strong> {{ $prescription->visit?->patient?->name }} <span class="text-muted">{{ $prescription->visit?->patient?->medical_record_number }}</span></p>
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
                    <th>Aturan pakai</th>
                    <th>Qty</th>
                    @if ($prescription->isSent())
                        <th>Stok cabang</th>
                        <th>Batch FEFO</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($prescription->items as $item)
                    @php
                        $lines = $availability[$item->id] ?? [];
                        $ready = $item->isCompound() ? null : ($lines[0] ?? null);
                    @endphp
                    <tr class="{{ $ready && ($ready['tracked'] ?? false) && ! $ready['enough'] ? 'table-danger' : '' }}">
                        <td>
                            <x-medicine-meta :medicine="$item->medicine" :name="$item->label()" :show-missing-kfa="! $item->isCompound()" />
                            @if ($item->isCompound())
                                <span class="badge bg-light text-dark">Racikan</span>
                                @if ($item->medication_type)
                                    <div class="text-muted font-size-12">Tipe {{ $item->medication_type->value }}</div>
                                @endif
                            @endif
                        </td>
                        <td>{{ $item->dosage ?: '-' }}</td>
                        <td>{{ $item->frequency ?: '-' }}</td>
                        <td>{{ $item->duration ?: '-' }}</td>
                        <td>{{ $item->quantity }}@if (! $item->isCompound() && filled($ready['unit'] ?? null)) {{ $ready['unit'] }}@endif</td>
                        @if ($prescription->isSent())
                            @if ($item->isCompound())
                                <td colspan="2" class="text-muted">Stok dihitung per bahan</td>
                            @else
                                @include('clinical.pharmacy.partials.stock-cells', ['line' => $ready])
                            @endif
                        @endif
                    </tr>
                    @if ($item->isCompound())
                        @foreach ($item->ingredients as $index => $ingredient)
                            @php($line = $lines[$index] ?? null)
                            <tr class="{{ $line && ($line['tracked'] ?? false) && ! $line['enough'] ? 'table-danger' : '' }}">
                                <td class="ps-4">
                                    <x-medicine-meta :medicine="$ingredient->medicine" :name="$ingredient->name" show-missing-kfa />
                                    @if ($ingredient->strength_value && $ingredient->strength_unit)
                                        <div class="text-muted font-size-12">d.t.d {{ rtrim(rtrim(number_format((float) $ingredient->strength_value, 4, '.', ''), '0'), '.') }} {{ $ingredient->strength_unit }}</div>
                                    @endif
                                </td>
                                <td colspan="3" class="text-muted">{{ $ingredient->unit ?: 'Bahan racikan' }}</td>
                                <td>{{ $ingredient->quantity }}</td>
                                @if ($prescription->isSent())
                                    @include('clinical.pharmacy.partials.stock-cells', ['line' => $line])
                                @endif
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-3 d-flex gap-2">
            <x-button href="{{ route($prescription->isSent() ? 'pharmacy.prescriptions.incoming' : 'pharmacy.prescriptions.completed') }}" variant="secondary">Kembali</x-button>
            @if ($prescription->isSent() && auth()->user()?->can('prescription.update') && (auth()->user()?->can('pharmacy.manage') || auth()->user()?->can('stock.manage')))
                @if ($stockShort)
                    <x-button type="button" variant="success" disabled>Stok tidak cukup</x-button>
                @else
                    <form action="{{ route('pharmacy.prescriptions.fulfill', $prescription) }}" method="POST" onsubmit="return confirm('Serahkan resep dan potong stok FEFO?')">
                        @csrf
                        <x-button type="submit" variant="success" icon="bx bx-check">Proses &amp; Serahkan</x-button>
                    </form>
                @endif
            @endif
        </div>
    </x-card>
@endsection
