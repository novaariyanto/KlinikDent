@extends('layouts.app')

@section('title', 'Tutup Shift')

@section('content')
    <x-page-header title="Tutup Shift" :breadcrumb="['Kasir' => route('cashier.shifts.close'), 'Tutup' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-6">
            <x-card title="Rekonsiliasi">
                <p class="mb-1">Saldo awal: <strong>{{ number_format((float) $shift->opening_balance, 0, ',', '.') }}</strong></p>
                <p class="mb-1">Tunai masuk: <strong>{{ number_format((float) $cashIn, 0, ',', '.') }}</strong></p>
                <p class="mb-3">Saldo sistem: <strong>{{ number_format((float) $system, 0, ',', '.') }}</strong></p>
                <p class="text-muted">Isi saldo fisik. Selisih akan dicatat, tidak dipaksa cocok.</p>
                <form method="POST" action="{{ route('cashier.shifts.close.store') }}">
                    @csrf
                    <x-input name="closing_balance" type="number" label="Saldo fisik dihitung" :value="old('closing_balance', $system)" required />
                    <x-input name="close_notes" label="Catatan" :value="old('close_notes')" />
                    <x-button type="submit" variant="danger">Tutup Shift</x-button>
                </form>
            </x-card>
        </div>
    </div>
@endsection
