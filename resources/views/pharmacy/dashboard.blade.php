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
@endsection
