@extends('layouts.app')

@section('title', 'Keuangan')

@section('content')
    <x-page-header title="Dashboard Keuangan" :breadcrumb="['Keuangan' => route('finance.index'), 'Ringkasan' => null]" />
    <x-alert />

    <x-card title="Periode">
        <form method="GET">
            @include('finance.partials.period-filter')
        </form>
    </x-card>

    <div class="row">
        @foreach ([
            ['label' => 'Pendapatan hari ini', 'value' => $metrics['today_revenue'], 'href' => route('finance.revenue.daily'), 'can' => 'revenue.view'],
            ['label' => 'Pendapatan periode', 'value' => $metrics['period_revenue'], 'href' => route('finance.revenue.monthly'), 'can' => 'revenue.view'],
            ['label' => 'Pengeluaran periode', 'value' => $metrics['period_expenses'], 'href' => route('finance.expenses'), 'can' => 'expense.view'],
            ['label' => 'Saldo kas & bank', 'value' => $metrics['cash_balance'], 'href' => route('finance.cash-bank'), 'can' => 'cash_bank.view'],
            ['label' => 'Piutang', 'value' => $metrics['receivables'], 'href' => route('finance.receivables'), 'can' => 'receivable.view'],
            ['label' => 'Hutang PO', 'value' => $metrics['payables'], 'href' => route('finance.payables'), 'can' => 'payable.view'],
        ] as $card)
            <div class="col-md-4">
                <x-card :title="$card['label']">
                    <h3 class="mb-2">{{ number_format((float) $card['value'], 0, ',', '.') }}</h3>
                    @can($card['can'])
                        <a href="{{ $card['href'] }}">Lihat detail</a>
                    @endcan
                </x-card>
            </div>
        @endforeach
    </div>

    <x-card title="Laba rugi periode">
        <p class="mb-1">Pendapatan: <strong>{{ number_format((float) $metrics['profit_loss']['revenue'], 0, ',', '.') }}</strong></p>
        <p class="mb-1">Pengeluaran: <strong>{{ number_format((float) $metrics['profit_loss']['expenses'], 0, ',', '.') }}</strong></p>
        <p class="mb-1">HPP: <strong>{{ number_format((float) $metrics['profit_loss']['hpp'], 0, ',', '.') }}</strong></p>
        <h4 class="mb-0">Laba/Rugi: {{ number_format((float) $metrics['profit_loss']['profit'], 0, ',', '.') }}</h4>
        @can('report.view')
            <a href="{{ route('finance.reports.profit-loss') }}">Laporan laba rugi</a>
        @endcan
    </x-card>
@endsection
