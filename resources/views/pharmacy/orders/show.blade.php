@extends('layouts.app')

@section('title', $order->number)

@section('content')
    <x-page-header :title="$order->number" :breadcrumb="['PO' => route('pharmacy.purchases.orders'), $order->number => null]" />
    <x-alert />
    <x-card title="Purchase Order">
        <p class="mb-1"><strong>Supplier:</strong> {{ $order->supplier?->name }}</p>
        <p class="mb-1"><strong>Cabang:</strong> {{ $order->branch?->name }}</p>
        <p class="mb-1"><strong>Tanggal:</strong> {{ $order->order_date?->format('d M Y') }}</p>
        <p class="mb-3">
            <strong>Status:</strong>
            <span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
        </p>
        <x-table>
            <thead>
                <tr>
                    <th>Obat</th>
                    <th>Qty</th>
                    <th>Harga</th>
                    <th>Batch</th>
                    <th>Expired</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->medicine?->name }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                        <td>{{ $item->batch_number ?: '-' }}</td>
                        <td>{{ $item->expired_date?->format('d M Y') ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-3 d-flex gap-2">
            <x-button href="{{ route('pharmacy.purchases.orders') }}" variant="secondary">Kembali</x-button>
            @if ($order->isDraft() && auth()->user()?->can('update', $order))
                <form action="{{ route('pharmacy.orders.submit', $order) }}" method="POST">
                    @csrf
                    <x-button type="submit" variant="info">Tandai Dipesan</x-button>
                </form>
            @endif
            @if ($order->isOrdered() && auth()->user()?->can('update', $order))
                <x-button href="{{ route('pharmacy.orders.receive', $order) }}" variant="success">Terima Barang</x-button>
            @endif
        </div>
    </x-card>
@endsection
