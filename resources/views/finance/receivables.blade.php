@extends('layouts.app')

@section('title', 'Piutang')

@section('content')
    <x-page-header title="Piutang" :breadcrumb="['Keuangan' => route('finance.receivables'), 'Piutang' => null]" />
    <x-alert />
    @foreach ($buckets as $bucket)
        <x-card :title="$bucket['label'].' — '.number_format((float) $bucket['total'], 0, ',', '.')">
            <x-table>
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Pasien</th>
                        <th>Penjamin</th>
                        <th>Sisa</th>
                        <th>Umur</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bucket['rows'] as $invoice)
                        <tr>
                            <td>{{ $invoice->number }}</td>
                            <td>{{ $invoice->patient?->name }}</td>
                            <td>{{ $invoice->payer?->name ?: '-' }}</td>
                            <td>{{ number_format((float) $invoice->remainingAmount(), 0, ',', '.') }}</td>
                            <td>{{ $invoice->created_at?->diffInDays(now()) }} hari</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">Tidak ada.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </x-card>
    @endforeach
@endsection
