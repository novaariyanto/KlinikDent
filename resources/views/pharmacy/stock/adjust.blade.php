@extends('layouts.app')

@section('title', 'Penyesuaian Stok')

@section('content')
    <x-page-header title="Penyesuaian Stok" :breadcrumb="['Stok' => route('pharmacy.stock.index'), 'Penyesuaian' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Penyesuaian Manual">
                <p class="text-muted">Gunakan angka positif untuk menambah stok, negatif untuk mengurangi. Stok tidak boleh minus.</p>
                <form method="POST" action="{{ route('pharmacy.stock.adjustments.store') }}">
                    @csrf
                    <x-select name="medicine_stock_id" label="Batch" :options="$options" :selected="old('medicine_stock_id')" required placeholder="Pilih batch" />
                    <x-input name="delta" type="number" label="Perubahan qty" :value="old('delta')" required />
                    <x-input name="notes" label="Alasan" :value="old('notes')" required />
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Simpan</x-button>
                        <x-button href="{{ route('pharmacy.stock.index') }}" variant="secondary">Batal</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
