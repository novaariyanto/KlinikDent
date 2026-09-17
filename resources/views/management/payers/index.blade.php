@php
    $title = isset($payerType) && $payerType ? 'Penjamin '.$payerType->label() : 'Penjamin';
@endphp

@extends('layouts.app')

@section('title', $title)

@section('content')
    @php
        $breadcrumb = [$title => null];
        if (auth()->user()?->can('clinic.view')) {
            $breadcrumb = ['Master Data' => route('management.clinic')] + $breadcrumb;
        }
    @endphp
    <x-page-header :title="$title" :breadcrumb="$breadcrumb" />

    <x-alert />

    <x-card :title="'Daftar '.$title">
        <x-slot:header-actions>
            @can('create', App\Models\Payer::class)
                <x-button href="{{ route('management.payers.create') }}" icon="bx bx-plus">Tambah Penjamin</x-button>
            @endcan
        </x-slot:header-actions>

        <x-data-table
            id="payers-table"
            :ajax="route('management.payers.data', $payerType ? ['type' => $payerType->value] : [])"
            :columns="$columns"
            :order="[[4, 'desc']]"
        />
    </x-card>
@endsection
