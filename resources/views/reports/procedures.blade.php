@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Laporan' => route('reports.index'), $title => null]" />
    <x-alert />
    <x-card title="Tindakan">
        <form method="GET">
            @include('finance.partials.period-filter')
        </form>
        <p class="text-muted">Qty {{ $totalQty }} · Nilai {{ number_format((float) $totalAmount, 0, ',', '.') }}</p>
        <x-table>
            <thead>
                <tr>
                    <th>Tindakan</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
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
