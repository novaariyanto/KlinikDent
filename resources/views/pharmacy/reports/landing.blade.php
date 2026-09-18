@extends('layouts.app')

@section('title', 'Laporan Farmasi')

@section('content')
    <x-page-header title="Laporan Farmasi" :breadcrumb="['Laporan' => route('reports.pharmacy'), 'Farmasi' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <x-card title="Stok"><a href="{{ route('pharmacy.reports.stock') }}">Lihat laporan stok</a></x-card>
        </div>
        <div class="col-md-6 col-xl-3">
            <x-card title="Obat Masuk"><a href="{{ route('pharmacy.reports.incoming') }}">Lihat obat masuk</a></x-card>
        </div>
        <div class="col-md-6 col-xl-3">
            <x-card title="Obat Keluar"><a href="{{ route('pharmacy.reports.outgoing') }}">Lihat obat keluar</a></x-card>
        </div>
        <div class="col-md-6 col-xl-3">
            <x-card title="Expired"><a href="{{ route('pharmacy.reports.expired') }}">Lihat expired</a></x-card>
        </div>
    </div>
@endsection
