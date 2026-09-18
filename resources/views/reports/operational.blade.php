@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Laporan' => route('reports.index'), $title => null]" />
    <x-alert />
    <x-card title="Operasional">
        <form method="GET">
            @include('finance.partials.period-filter')
        </form>
        <div class="row">
            @foreach ([
                'today' => 'Kunjungan hari ini',
                'total' => 'Kunjungan periode',
                'done' => 'Selesai',
                'queue_waiting' => 'Antrean menunggu',
                'open_invoices' => 'Tagihan terbuka',
                'pending_rx' => 'Resep menunggu',
                'referrals' => 'Rujukan',
            ] as $key => $label)
                <div class="col-md-4 col-xl-3">
                    <x-card :title="$label">
                        <h3 class="mb-0">{{ $ops[$key] }}</h3>
                    </x-card>
                </div>
            @endforeach
        </div>
    </x-card>
@endsection
