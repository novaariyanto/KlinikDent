@extends('layouts.app')

@section('title', $title ?? 'Laporan Pendapatan')

@section('content')
    <x-page-header :title="$title ?? 'Laporan Pendapatan'" :breadcrumb="['Laporan' => route('reports.index'), 'Pendapatan' => null]" />
    <x-alert />
    <x-card title="Ringkasan">
        <form method="GET">
            @include('finance.partials.period-filter')
        </form>
        <p class="text-muted">Total: <strong>{{ number_format((float) $total, 0, ',', '.') }}</strong></p>
        <div class="row">
            <div class="col-md-6">
                <h5>Per hari</h5>
                <x-table>
                    <thead><tr><th>Tanggal</th><th class="text-end">Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($byDay as $day => $amount)
                            <tr>
                                <td>{{ $day }}</td>
                                <td class="text-end">{{ number_format((float) $amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">Belum ada pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </x-table>
            </div>
            <div class="col-md-6">
                <h5>Per metode</h5>
                <x-table>
                    <thead><tr><th>Metode</th><th class="text-end">Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($byMethod as $row)
                            <tr>
                                <td>{{ $row['method'] }}</td>
                                <td class="text-end">{{ number_format((float) $row['amount'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">Belum ada.</td></tr>
                        @endforelse
                    </tbody>
                </x-table>
                <h5 class="mt-3">Per dokter</h5>
                <x-table>
                    <thead><tr><th>Dokter</th><th class="text-end">Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($byDoctor as $row)
                            <tr>
                                <td>{{ $row['doctor'] }}</td>
                                <td class="text-end">{{ number_format((float) $row['amount'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">Belum ada.</td></tr>
                        @endforelse
                    </tbody>
                </x-table>
            </div>
        </div>
    </x-card>
@endsection
