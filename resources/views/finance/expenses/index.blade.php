@extends('layouts.app')

@section('title', 'Pengeluaran')

@section('content')
    <x-page-header title="Pengeluaran" :breadcrumb="['Keuangan' => route('finance.expenses'), 'Pengeluaran' => null]" />
    <x-alert />

    @can('create', App\Models\Expense::class)
        <x-card title="Catat pengeluaran">
            <form method="POST" action="{{ route('finance.expenses.store') }}" class="row g-2">
                @csrf
                @if ($branches->isNotEmpty())
                    <div class="col-md-3">
                        <x-select name="branch_id" label="Cabang" :options="$branches->mapWithKeys(fn ($branch) => [$branch->id => $branch->name])->all()" :selected="old('branch_id', auth()->user()?->branch_id)" required />
                    </div>
                @endif
                <div class="col-md-3">
                    <x-select name="category_id" label="Kategori" :options="$categories->mapWithKeys(fn ($category) => [$category->id => $category->name])->all()" :selected="old('category_id')" required />
                </div>
                <div class="col-md-3">
                    <x-select name="cash_account_id" label="Akun kas" :options="$accounts->mapWithKeys(fn ($account) => [$account->id => $account->name.' ('.number_format((float) $account->balance, 0, ',', '.').')'])->all()" :selected="old('cash_account_id')" required />
                </div>
                <div class="col-md-3">
                    <x-select name="supplier_id" label="Supplier" :options="$suppliers->mapWithKeys(fn ($supplier) => [$supplier->id => $supplier->name])->all()" :selected="old('supplier_id')" placeholder="Opsional" />
                </div>
                <div class="col-md-3">
                    <x-input name="amount" label="Nominal" type="number" :value="old('amount')" required />
                </div>
                <div class="col-md-3">
                    <x-input name="expense_date" label="Tanggal" type="date" :value="old('expense_date', now()->toDateString())" required />
                </div>
                <div class="col-md-6">
                    <x-input name="description" label="Keterangan" :value="old('description')" required />
                </div>
                <div class="col-12">
                    <x-button type="submit" icon="bx bx-plus">Simpan</x-button>
                </div>
            </form>
        </x-card>
    @endcan

    <x-card title="Daftar pengeluaran">
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
                <x-button type="submit" icon="bx bx-search">Filter</x-button>
            </div>
        </form>
        <x-table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Kategori</th>
                    <th>Keterangan</th>
                    <th>Akun</th>
                    <th class="text-end">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td>{{ $expense->expense_date?->format('d M Y') }}</td>
                        <td>{{ $expense->category?->name }}</td>
                        <td>{{ $expense->description }}</td>
                        <td>{{ $expense->cashAccount?->name }}</td>
                        <td class="text-end">{{ number_format((float) $expense->amount, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Belum ada pengeluaran.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($expenses->hasPages())
            <div class="mt-3">{{ $expenses->links() }}</div>
        @endif
    </x-card>
@endsection
