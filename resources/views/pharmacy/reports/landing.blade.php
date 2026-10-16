@extends('layouts.app')

@section('title', 'Laporan Farmasi')

@section('content')
    <x-page-header title="Laporan Farmasi" :breadcrumb="['Farmasi' => route('pharmacy.index'), 'Laporan' => null]" />
    <x-alert />
    @include('pharmacy.partials.reports-nav')

    <div class="row">
        <div class="col-md-6 col-xl-3">
            <x-card title="Stok">
                <p class="text-muted small mb-2">Batch usable (qty &gt; 0, belum kedaluwarsa).</p>
                <a href="{{ route('pharmacy.reports.stock') }}">Lihat laporan stok</a>
            </x-card>
        </div>
        <div class="col-md-6 col-xl-3">
            <x-card title="Obat Masuk">
                <p class="text-muted small mb-2">Penerimaan, penyesuaian masuk, dan mutasi masuk.</p>
                <a href="{{ route('pharmacy.reports.incoming') }}">Lihat obat masuk</a>
            </x-card>
        </div>
        <div class="col-md-6 col-xl-3">
            <x-card title="Obat Keluar">
                <p class="text-muted small mb-2">Penyerahan resep, retur, dan mutasi keluar.</p>
                <a href="{{ route('pharmacy.reports.outgoing') }}">Lihat obat keluar</a>
            </x-card>
        </div>
        <div class="col-md-6 col-xl-3">
            <x-card title="Expired">
                <p class="text-muted small mb-2">Batch kedaluwarsa atau hampir kedaluwarsa.</p>
                <a href="{{ route('pharmacy.reports.expired') }}">Lihat expired</a>
            </x-card>
        </div>
    </div>

    @canany(['pharmacy.view', 'stock.view'])
        <x-card title="Transaksi">
            <p class="text-muted small mb-2">Seluruh mutasi stok (masuk dan keluar) dengan filter tanggal.</p>
            <a href="{{ route('pharmacy.transactions') }}">Buka transaksi farmasi</a>
        </x-card>
    @endcanany
@endsection
