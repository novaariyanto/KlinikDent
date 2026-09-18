@extends('layouts.app')

@section('title', 'Laporan Piutang')

@section('content')
    <x-page-header title="Laporan Piutang" :breadcrumb="['Keuangan' => route('finance.reports.receivables'), 'Piutang' => null]" />
    <x-alert />
    <x-card title="Total piutang terbuka">
        <form method="GET" class="mb-3">
            @include('finance.partials.period-filter')
        </form>
        <h3 class="mb-0">{{ number_format((float) $total, 0, ',', '.') }}</h3>
        <p class="text-muted mt-2 mb-0">Piutang dihitung dari tagihan belum lunas, bukan filter tanggal terbit.</p>
        <a href="{{ route('finance.receivables') }}">Lihat rincian umur piutang</a>
    </x-card>
@endsection
