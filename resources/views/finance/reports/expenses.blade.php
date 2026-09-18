@extends('layouts.app')

@section('title', 'Laporan Pengeluaran')

@section('content')
    <x-page-header title="Laporan Pengeluaran" :breadcrumb="['Keuangan' => route('finance.reports.expenses'), 'Pengeluaran' => null]" />
    <x-alert />
    <x-card title="Pengeluaran per kategori">
        <form method="GET">
            @include('finance.partials.period-filter')
        </form>
        <p class="text-muted">Total: <strong>{{ number_format((float) $total, 0, ',', '.') }}</strong></p>
        <x-table>
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th class="text-end">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['category'] }}</td>
                        <td class="text-end">{{ number_format((float) $row['amount'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted">Belum ada pengeluaran.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
@endsection
