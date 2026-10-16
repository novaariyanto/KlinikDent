@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Farmasi' => route('pharmacy.index'), 'Laporan' => route('pharmacy.reports'), $title => null]" />
    <x-alert />
    @include('pharmacy.partials.reports-nav')
    <x-card :title="$title">
        <p class="text-muted">{{ $hint }}</p>
        <form method="GET" action="{{ url()->current() }}" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label" for="q">Cari obat / batch</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <x-button type="submit" icon="bx bx-search">Cari</x-button>
            </div>
        </form>
        <x-table>
            <thead>
                <tr>
                    <th>Obat</th>
                    <th>KFA</th>
                    <th>Kekuatan</th>
                    <th>Cabang</th>
                    <th>Batch</th>
                    <th>Expired</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stocks as $stock)
                    <tr class="{{ $stock->isExpired() ? 'table-danger' : ($stock->isExpiringSoon() ? 'table-warning' : '') }}">
                        <td>{{ $stock->medicine?->name }}</td>
                        <td>{{ $stock->medicine?->kfa_code ?: '—' }}</td>
                        <td>{{ $stock->medicine?->strengthLabel() ?: '—' }}</td>
                        <td>{{ $stock->branch?->name }}</td>
                        <td>{{ $stock->batch_number }}</td>
                        <td>{{ $stock->expired_date?->format('d M Y') }}</td>
                        <td>{{ $stock->quantity }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Belum ada stok.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($stocks->hasPages())
            <div class="mt-3">{{ $stocks->links() }}</div>
        @endif
    </x-card>
@endsection
