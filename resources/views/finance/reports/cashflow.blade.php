@extends('layouts.app')

@section('title', 'Arus Kas')

@section('content')
    <x-page-header title="Laporan Arus Kas" :breadcrumb="['Keuangan' => route('finance.reports.cashflow'), 'Arus Kas' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-md-4">
            <x-card title="Masuk">
                <h3 class="text-success mb-0">{{ number_format((float) $in, 0, ',', '.') }}</h3>
            </x-card>
        </div>
        <div class="col-md-4">
            <x-card title="Keluar">
                <h3 class="text-danger mb-0">{{ number_format((float) $out, 0, ',', '.') }}</h3>
            </x-card>
        </div>
        <div class="col-md-4">
            <x-card title="Bersih">
                <h3 class="mb-0">{{ number_format((float) $net, 0, ',', '.') }}</h3>
            </x-card>
        </div>
    </div>
    <x-card title="Mutasi">
        <form method="GET">
            @include('finance.partials.period-filter')
        </form>
        <x-table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Akun</th>
                    <th>Tipe</th>
                    <th>Keterangan</th>
                    <th class="text-end">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($mutations as $mutation)
                    <tr>
                        <td>{{ $mutation->mutated_at?->format('d M Y H:i') }}</td>
                        <td>{{ $mutation->account?->name }}</td>
                        <td><span class="{{ $mutation->type->badgeClass() }}">{{ $mutation->type->label() }}</span></td>
                        <td>{{ $mutation->notes ?: '-' }}</td>
                        <td class="text-end">{{ number_format((float) $mutation->signedAmount(), 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Belum ada mutasi.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($mutations->hasPages())
            <div class="mt-3">{{ $mutations->links() }}</div>
        @endif
    </x-card>
@endsection
