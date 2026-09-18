@extends('layouts.app')

@section('title', 'Purchase Order Baru')

@section('content')
    <x-page-header title="Purchase Order Baru" :breadcrumb="['PO' => route('pharmacy.purchases.orders'), 'Baru' => null]" />
    <x-alert />
    <form method="POST" action="{{ route('pharmacy.orders.store') }}">
        @csrf
        <div class="row">
            <div class="col-lg-4">
                <x-card title="Header">
                    <x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="old('supplier_id')" required />
                    <x-select name="branch_id" label="Cabang" :options="$branches" :selected="old('branch_id', $defaultBranchId)" required />
                    <x-input name="order_date" type="date" label="Tanggal order" :value="old('order_date', now()->toDateString())" required />
                </x-card>
            </div>
            <div class="col-lg-8">
                <x-card title="Item">
                    <x-table>
                        <thead>
                            <tr>
                                <th>Obat</th>
                                <th style="width:120px">Qty</th>
                                <th style="width:160px">Harga satuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for ($i = 0; $i < 6; $i++)
                                <tr>
                                    <td>
                                        <select name="items[{{ $i }}][medicine_id]" class="form-select">
                                            <option value="">—</option>
                                            @foreach ($medicines as $medicine)
                                                <option
                                                    value="{{ $medicine->id }}"
                                                    data-price="{{ $medicine->base_price }}"
                                                    @selected((string) old("items.$i.medicine_id") === (string) $medicine->id)
                                                >
                                                    {{ $medicine->name }} ({{ $medicine->unit }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity") }}" class="form-control">
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.01" name="items[{{ $i }}][unit_price]" value="{{ old("items.$i.unit_price") }}" class="form-control">
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </x-table>
                    <p class="text-muted mb-0 mt-2">Baris kosong diabaikan. Isi minimal satu obat.</p>
                </x-card>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <x-button type="submit" icon="bx bx-save">Simpan Draft</x-button>
            <x-button href="{{ route('pharmacy.purchases.orders') }}" variant="secondary">Batal</x-button>
        </div>
    </form>
@endsection
