@extends('layouts.app')

@section('title', 'Laporan')

@section('content')
    <x-page-header title="Laporan Klinik" :breadcrumb="['Laporan' => route('reports.index'), 'Ringkasan' => null]" />
    <x-alert />
    <div class="row">
        @foreach ($items as $item)
            <div class="col-md-4">
                <x-card :title="$item['title']">
                    <p class="text-muted">{{ $item['desc'] }}</p>
                    <a href="{{ route($item['route']) }}">Buka laporan</a>
                </x-card>
            </div>
        @endforeach
    </div>
@endsection
