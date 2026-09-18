@extends('layouts.app')

@section('title', 'Kategori Pengeluaran')

@section('content')
    <x-page-header title="Kategori Pengeluaran" :breadcrumb="['Keuangan' => route('finance.expenses.categories'), 'Kategori' => null]" />
    <x-alert />

    @can('create', App\Models\ExpenseCategory::class)
        <x-card title="Tambah kategori">
            <form method="POST" action="{{ route('finance.expenses.categories.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-6">
                    <x-input name="name" label="Nama" :value="old('name')" required />
                </div>
                <div class="col-md-3 mb-3">
                    <x-button type="submit" icon="bx bx-plus">Simpan</x-button>
                </div>
            </form>
        </x-card>
    @endcan

    <x-card title="Daftar kategori">
        <x-table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Dipakai</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>
                            @can('update', $category)
                                <form method="POST" action="{{ route('finance.expenses.categories.update', $category) }}" class="d-flex gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="name" value="{{ old('name', $category->name) }}" class="form-control form-control-sm" required>
                                    <x-button type="submit" variant="light">Ubah</x-button>
                                </form>
                            @else
                                {{ $category->name }}
                            @endcan
                        </td>
                        <td>{{ $category->expenses_count }}</td>
                        <td class="text-end">
                            @can('delete', $category)
                                <form method="POST" action="{{ route('finance.expenses.categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('Hapus kategori ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-light text-danger" type="submit">Hapus</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">Belum ada kategori.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($categories->hasPages())
            <div class="mt-3">{{ $categories->links() }}</div>
        @endif
    </x-card>
@endsection
