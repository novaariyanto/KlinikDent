@extends('layouts.app')

@section('title', 'Laporan Keuangan')

@section('content')
    <x-page-header title="Laporan Keuangan" :breadcrumb="['Laporan' => route('reports.finance'), 'Keuangan' => null]" />
    <x-alert />
    <x-card title="Laba rugi periode">
        <form method="GET">
            @include('finance.partials.period-filter')
        </form>
        <p class="mb-1">Pendapatan: <strong>{{ number_format((float) $pl['revenue'], 0, ',', '.') }}</strong></p>
        <p class="mb-1">Pengeluaran: <strong>{{ number_format((float) $pl['expenses'], 0, ',', '.') }}</strong></p>
        <p class="mb-1">HPP: <strong>{{ number_format((float) $pl['hpp'], 0, ',', '.') }}</strong></p>
        <h4>Laba/Rugi: {{ number_format((float) $pl['profit'], 0, ',', '.') }}</h4>
        <div class="mt-3">
            <a href="{{ route('finance.reports.profit-loss', request()->query()) }}">Laba rugi rinci</a>
            · <a href="{{ route('finance.reports.cashflow', request()->query()) }}">Arus kas</a>
            · <a href="{{ route('finance.reports.receivables') }}">Piutang</a>
            · <a href="{{ route('finance.reports.expenses', request()->query()) }}">Pengeluaran</a>
        </div>
    </x-card>
@endsection
