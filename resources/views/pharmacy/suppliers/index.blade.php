@extends('layouts.app')

@section('title', 'Supplier')

@section('content')
    <x-page-header title="Supplier" :breadcrumb="['Farmasi' => route('pharmacy.purchases.suppliers'), 'Supplier' => null]" />
    <x-alert />
    <x-card title="Supplier Farmasi">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <form method="GET" class="row g-2 align-items-end flex-grow-1 me-3">
                <div class="col-md-6">
                    <label class="form-label" for="q">Cari</label>
                    <input id="q" type="text" name="q" value="{{ request('q') }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <x-button type="submit" icon="bx bx-search">Cari</x-button>
                </div>
            </form>
            @can('create', App\Models\Supplier::class)
                <x-button href="{{ route('pharmacy.suppliers.create') }}" icon="bx bx-plus">Tambah</x-button>
            @endcan
        </div>
        <x-table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Kontak</th>
                    <th>Alamat</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td>{{ $supplier->name }}</td>
                        <td>{{ $supplier->contact ?: '-' }}</td>
                        <td>{{ $supplier->address ?: '-' }}</td>
                        <td class="text-end text-nowrap">
                            @can('update', $supplier)
                                <x-button href="{{ route('pharmacy.suppliers.edit', $supplier) }}" variant="light">Ubah</x-button>
                                <form action="{{ route('pharmacy.suppliers.destroy', $supplier) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus supplier ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-light text-danger" type="submit">Hapus</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Belum ada supplier.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($suppliers->hasPages())
            <div class="mt-3">{{ $suppliers->links() }}</div>
        @endif
    </x-card>
@endsection
