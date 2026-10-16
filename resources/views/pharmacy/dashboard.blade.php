@extends('layouts.app')

@section('title', 'Farmasi')

@section('content')
    <x-page-header title="Dashboard Farmasi" :breadcrumb="['Farmasi' => route('pharmacy.index'), 'Ringkasan' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <x-card title="Resep Masuk">
                <h3 class="mb-2">{{ $incoming }}</h3>
                <a href="{{ route('pharmacy.prescriptions.incoming') }}">Lihat resep</a>
            </x-card>
        </div>
        <div class="col-md-6 col-xl-3">
            <x-card title="Stok Menipis">
                <h3 class="mb-2">{{ $lowStock }}</h3>
                <a href="{{ route('pharmacy.stock.index') }}">Lihat stok</a>
            </x-card>
        </div>
        <div class="col-md-6 col-xl-3">
            <x-card title="Expired / Hampir">
                <h3 class="mb-2">{{ $expired + $expiring }}</h3>
                <a href="{{ route('pharmacy.stock.expired') }}">Lihat expired</a>
            </x-card>
        </div>
        <div class="col-md-6 col-xl-3">
            <x-card title="PO Menunggu">
                <h3 class="mb-2">{{ $pendingOrders }}</h3>
                <a href="{{ route('pharmacy.purchases.receipts') }}">Penerimaan</a>
            </x-card>
        </div>
    </div>

    @canany(['report.view', 'pharmacy.view', 'stock.view'])
        <x-card title="Laporan Farmasi">
            <p class="text-muted small mb-3">Ringkasan dan detail mutasi stok untuk cabang yang Anda kelola.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('pharmacy.reports') }}" class="btn btn-sm btn-soft-primary">Ringkasan laporan</a>
                <a href="{{ route('pharmacy.reports.stock') }}" class="btn btn-sm btn-soft-secondary">Stok</a>
                <a href="{{ route('pharmacy.reports.incoming') }}" class="btn btn-sm btn-soft-secondary">Obat masuk</a>
                <a href="{{ route('pharmacy.reports.outgoing') }}" class="btn btn-sm btn-soft-secondary">Obat keluar</a>
                <a href="{{ route('pharmacy.reports.expired') }}" class="btn btn-sm btn-soft-secondary">Expired</a>
                <a href="{{ route('pharmacy.transactions') }}" class="btn btn-sm btn-soft-secondary">Transaksi</a>
            </div>
        </x-card>
    @endcanany
@endsection
