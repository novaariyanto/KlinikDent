@extends('layouts.app')

@section('title', 'Retur Stok')

@section('content')
    <x-page-header title="Retur Stok" :breadcrumb="['Farmasi' => route('pharmacy.stock.index'), 'Retur' => null]" />
    <x-alert />
    @include('pharmacy.partials.nav')
    <x-card title="Retur supplier atau pasien">
        <form method="POST" action="{{ route('pharmacy.stock.returns.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="medicine_stock_id">Batch</label>
                <select class="form-select" name="medicine_stock_id" id="medicine_stock_id" required>
                    @foreach ($stocks as $stock)
                        <option value="{{ $stock->id }}">{{ $stock->medicine?->catalogLabel(withUnit: false) ?? 'Obat' }} · {{ $stock->batch_number }} · qty {{ $stock->quantity }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="direction">Arah</label>
                <select class="form-select" name="direction" id="direction" required>
                    <option value="supplier">Retur ke supplier (stok berkurang)</option>
                    <option value="patient">Retur dari pasien (stok bertambah)</option>
                </select>
            </div>
            <x-input name="quantity" type="number" label="Jumlah" min="1" required />
            <x-input name="notes" label="Alasan" required />
            <x-button type="submit">Simpan retur</x-button>
        </form>
    </x-card>
@endsection
