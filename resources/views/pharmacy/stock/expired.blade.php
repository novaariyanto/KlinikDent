@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Farmasi' => route('pharmacy.stock.expired'), $title => null]" />
    <x-alert />
    <x-card :title="$title">
        <p class="text-muted">Batch yang sudah kedaluwarsa atau akan kedaluwarsa dalam {{ $days }} hari, masih punya stok.</p>
        <x-table>
            <thead>
                <tr>
                    <th>Obat</th>
                    <th>Cabang</th>
                    <th>Batch</th>
                    <th>Expired</th>
                    <th>Qty</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stocks as $stock)
                    <tr class="{{ $stock->isExpired() ? 'table-danger' : 'table-warning' }}">
                        <td>{{ $stock->medicine?->name }}</td>
                        <td>{{ $stock->branch?->name }}</td>
                        <td>{{ $stock->batch_number }}</td>
                        <td>{{ $stock->expired_date?->format('d M Y') }}</td>
                        <td>{{ $stock->quantity }}</td>
                        <td>{{ $stock->isExpired() ? 'Kedaluwarsa' : 'Hampir kedaluwarsa' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Tidak ada batch expired / hampir expired.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($stocks->hasPages())
            <div class="mt-3">{{ $stocks->links() }}</div>
        @endif
    </x-card>
@endsection
