@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Laporan' => route('reports.index'), $title => null]" />
    <x-alert />
    <x-card title="Pasien">
        <form method="GET">
            @include('finance.partials.period-filter')
        </form>
        <div class="row">
            @foreach ([
                'visited' => 'Berkunjung',
                'new' => 'Pasien baru',
                'male' => 'Laki-laki',
                'female' => 'Perempuan',
            ] as $key => $label)
                <div class="col-md-3">
                    <x-card :title="$label">
                        <h3 class="mb-0">{{ $summary[$key] }}</h3>
                    </x-card>
                </div>
            @endforeach
        </div>
    </x-card>
@endsection
