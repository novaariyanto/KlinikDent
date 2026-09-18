@extends('layouts.app')

@section('title', 'Buka Shift')

@section('content')
    <x-page-header title="Buka Shift" :breadcrumb="['Kasir' => route('cashier.shifts.open'), 'Buka Shift' => null]" />
    <x-alert />
    @if ($current)
        <div class="alert alert-info">Shift sudah terbuka sejak {{ $current->opened_at?->format('d M Y H:i') }}. <a href="{{ route('cashier.shifts.transactions') }}">Lihat transaksi</a></div>
    @endif
    <div class="row">
        <div class="col-lg-6">
            <x-card title="Buka shift kasir">
                <form method="POST" action="{{ route('cashier.shifts.open.store') }}">
                    @csrf
                    <x-select name="branch_id" label="Cabang" :options="$branches" :selected="old('branch_id', $defaultBranchId)" required />
                    <x-input name="opening_balance" type="number" label="Saldo awal" :value="old('opening_balance', 0)" required />
                    <x-button type="submit" variant="success" icon="bx bx-log-in-circle">Buka Shift</x-button>
                </form>
            </x-card>
        </div>
    </div>
@endsection
