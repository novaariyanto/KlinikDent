@extends('layouts.app')

@section('title', 'Laba Rugi')

@section('content')
    <x-page-header title="Laporan Laba Rugi" :breadcrumb="['Keuangan' => route('finance.reports.profit-loss'), 'Laba Rugi' => null]" />
    <x-alert />
    <x-card title="Laba rugi">
        <form method="GET" class="mb-3">
            @include('finance.partials.period-filter')
        </form>
        <x-table>
            <tbody>
                <tr>
                    <th>Pendapatan (pembayaran)</th>
                    <td class="text-end">{{ number_format((float) $pl['revenue'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Pengeluaran</th>
                    <td class="text-end">{{ number_format((float) $pl['expenses'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>HPP (obat keluar × harga dasar)</th>
                    <td class="text-end">{{ number_format((float) $pl['hpp'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Laba / Rugi</th>
                    <td class="text-end"><strong>{{ number_format((float) $pl['profit'], 0, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </x-table>
    </x-card>
@endsection
