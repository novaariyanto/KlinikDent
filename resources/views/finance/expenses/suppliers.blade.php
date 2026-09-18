@extends('layouts.app')

@section('title', 'Supplier')

@section('content')
    <x-page-header title="Supplier" :breadcrumb="['Keuangan' => route('finance.expenses.suppliers'), 'Supplier' => null]" />
    <x-alert />
    <x-card title="Supplier pengeluaran">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-6">
                <label class="form-label" for="q">Cari</label>
                <input id="q" type="text" name="q" value="{{ request('q') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <x-button type="submit" icon="bx bx-search">Cari</x-button>
            </div>
        </form>
        <x-table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Kontak</th>
                    <th>Alamat</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td>{{ $supplier->name }}</td>
                        <td>{{ $supplier->contact ?: '-' }}</td>
                        <td>{{ $supplier->address ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">Belum ada supplier.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($suppliers->hasPages())
            <div class="mt-3">{{ $suppliers->links() }}</div>
        @endif
    </x-card>
@endsection
