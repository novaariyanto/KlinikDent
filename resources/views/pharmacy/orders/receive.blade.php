@extends('layouts.app')

@section('title', 'Terima '.$order->number)

@section('content')
    <x-page-header :title="'Terima '.$order->number" :breadcrumb="['PO' => route('pharmacy.purchases.orders'), 'Penerimaan' => null]" />
    <x-alert />
    <x-card title="Penerimaan Barang">
        <p class="text-muted">Isi nomor batch dan tanggal kedaluwarsa. Qty mengikuti PO ({{ $order->supplier?->name }} — {{ $order->branch?->name }}).</p>
        <form method="POST" action="{{ route('pharmacy.orders.receive.store', $order) }}">
            @csrf
            <x-table>
                <thead>
                    <tr>
                        <th>Obat</th>
                        <th>Qty</th>
                        <th>Batch</th>
                        <th>Expired</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->medicine?->name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>
                                <input class="form-control" name="items[{{ $item->id }}][batch_number]" value="{{ old('items.'.$item->id.'.batch_number') }}" required>
                            </td>
                            <td>
                                <input type="date" class="form-control" name="items[{{ $item->id }}][expired_date]" value="{{ old('items.'.$item->id.'.expired_date') }}" required>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
            <div class="mt-3 d-flex gap-2">
                <x-button type="submit" variant="success" icon="bx bx-package">Terima &amp; Tambah Stok</x-button>
                <x-button href="{{ route('pharmacy.orders.show', $order) }}" variant="secondary">Batal</x-button>
            </div>
        </form>
    </x-card>
@endsection
