@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Keuangan' => route('finance.index'), $title => null]" />
    <x-alert />

    <x-card :title="$title">
        <form method="GET" class="mb-3">
            @include('finance.partials.period-filter')
        </form>
        <p class="text-muted">Total: <strong>{{ number_format((float) $total, 0, ',', '.') }}</strong></p>
        <x-table>
            <thead>
                <tr>
                    @if ($mode === 'doctors')
                        <th>Dokter</th>
                    @else
                        <th>{{ $mode === 'monthly' ? 'Bulan' : 'Tanggal' }}</th>
                    @endif
                    <th class="text-end">Pendapatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $key => $row)
                    <tr>
                        @if ($mode === 'doctors')
                            <td>{{ $row['doctor'] }}</td>
                            <td class="text-end">{{ number_format((float) $row['amount'], 0, ',', '.') }}</td>
                        @else
                            <td>{{ $key }}</td>
                            <td class="text-end">{{ number_format((float) $row, 0, ',', '.') }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted">Belum ada pendapatan pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
@endsection
