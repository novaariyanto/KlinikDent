@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Farmasi' => route('pharmacy.purchases.orders'), $title => null]" />
    <x-alert />
    <x-card :title="$title">
        <p class="text-muted">{{ $hint }}</p>
        <div class="d-flex justify-content-between align-items-end mb-3">
            <form method="GET" class="row g-2 align-items-end flex-grow-1 me-3">
                <div class="col-md-5">
                    <label class="form-label" for="q">Cari nomor / supplier</label>
                    <input id="q" type="text" name="q" value="{{ request('q') }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <x-button type="submit" icon="bx bx-search">Cari</x-button>
                </div>
            </form>
            @if ($showCreate)
                @can('create', App\Models\PurchaseOrder::class)
                    <x-button href="{{ route('pharmacy.orders.create') }}" icon="bx bx-plus">PO Baru</x-button>
                @endcan
            @endif
        </div>
        <x-table>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Tanggal</th>
                    <th>Supplier</th>
                    <th>Cabang</th>
                    <th>Item</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>{{ $order->number }}</td>
                        <td>{{ $order->order_date?->format('d M Y') }}</td>
                        <td>{{ $order->supplier?->name }}</td>
                        <td>{{ $order->branch?->name }}</td>
                        <td>{{ $order->items->count() }}</td>
                        <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                        <td class="text-end">
                            <x-button href="{{ route('pharmacy.orders.show', $order) }}" variant="light">Lihat</x-button>
                            @if ($order->isOrdered() && auth()->user()?->can('update', $order))
                                <x-button href="{{ route('pharmacy.orders.receive', $order) }}" variant="success">Terima</x-button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Belum ada purchase order.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($orders->hasPages())
            <div class="mt-3">{{ $orders->links() }}</div>
        @endif
    </x-card>
@endsection
