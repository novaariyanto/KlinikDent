@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Laporan' => route('reports.personal'), $title => null]" />
    <x-alert />
    <x-card title="Kinerja {{ $doctor->name }}">
        <form method="GET">
            @include('finance.partials.period-filter')
        </form>
        <div class="row">
            <div class="col-md-3"><x-card title="Kunjungan"><h3 class="mb-0">{{ $counts['total'] }}</h3></x-card></div>
            <div class="col-md-3"><x-card title="Selesai"><h3 class="mb-0">{{ $counts['done'] }}</h3></x-card></div>
            <div class="col-md-3"><x-card title="Pendapatan"><h3 class="mb-0">{{ number_format((float) $revenue, 0, ',', '.') }}</h3></x-card></div>
        </div>
        <x-table>
            <thead>
                <tr>
                    <th>Tindakan</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($procedures as $row)
                    <tr>
                        <td>{{ $row->name }}</td>
                        <td class="text-end">{{ $row->qty }}</td>
                        <td class="text-end">{{ number_format((float) $row->amount, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">Belum ada tindakan.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
@endsection
