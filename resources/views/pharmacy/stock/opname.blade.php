@extends('layouts.app')

@section('title', 'Opname Stok')

@section('content')
    <x-page-header title="Opname Stok" :breadcrumb="['Farmasi' => route('pharmacy.stock.index'), 'Opname' => null]" />
    <x-alert />
    @include('pharmacy.partials.nav')
    <x-card title="Hitung fisik">
        <form method="POST" action="{{ route('pharmacy.stock.opname.store') }}">
            @csrf
            <x-input name="notes" label="Catatan" />
            <x-table>
                <thead>
                    <tr><th>Obat</th><th>KFA</th><th>Kekuatan</th><th>Batch</th><th>Sistem</th><th>Fisik</th></tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stock)
                        <tr>
                            <td>{{ $stock->medicine?->name }}</td>
                            <td>
                                @if (filled($stock->medicine?->kfa_code))
                                    {{ $stock->medicine->kfa_code }}
                                @else
                                    <span class="badge bg-soft-warning text-warning">Belum KFA</span>
                                @endif
                            </td>
                            <td>{{ $stock->medicine?->strengthLabel() ?: '—' }}</td>
                            <td>{{ $stock->batch_number }}</td>
                            <td>{{ $stock->quantity }}</td>
                            <td><input class="form-control" type="number" min="0" name="physical[{{ $stock->id }}]" value="{{ $stock->quantity }}"></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">Tidak ada stok.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
            <x-button type="submit" class="mt-3">Posting opname</x-button>
        </form>
    </x-card>
@endsection
