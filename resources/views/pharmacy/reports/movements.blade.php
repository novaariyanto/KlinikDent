@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Farmasi' => route('pharmacy.transactions'), $title => null]" />
    <x-alert />
    <x-card :title="$title">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label" for="date_from">Dari</label>
                <input id="date_from" type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="date_to">Sampai</label>
                <input id="date_to" type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <x-button type="submit" icon="bx bx-filter-alt">Filter</x-button>
            </div>
        </form>
        <x-table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Tipe</th>
                    <th>Obat</th>
                    <th>Batch</th>
                    <th>Cabang</th>
                    <th>Qty</th>
                    <th>User</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td>{{ $movement->created_at?->format('d M Y H:i') }}</td>
                        <td><span class="{{ $movement->type->badgeClass() }}">{{ $movement->type->label() }}</span></td>
                        <td>{{ $movement->stock?->medicine?->name }}</td>
                        <td>{{ $movement->stock?->batch_number }}</td>
                        <td>{{ $movement->stock?->branch?->name }}</td>
                        <td>{{ $movement->quantity }}</td>
                        <td>{{ $movement->user?->name ?: '-' }}</td>
                        <td>{{ $movement->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">Belum ada transaksi.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($movements->hasPages())
            <div class="mt-3">{{ $movements->links() }}</div>
        @endif
    </x-card>
@endsection
