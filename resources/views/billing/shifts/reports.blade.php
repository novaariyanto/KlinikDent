@extends('layouts.app')

@section('title', 'Laporan Kasir')

@section('content')
    <x-page-header title="Laporan Kasir" :breadcrumb="['Kasir' => route('cashier.reports'), 'Laporan' => null]" />
    <x-alert />
    <x-card title="Rekap shift">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <x-button type="submit">Filter</x-button>
            </div>
        </form>
        <x-table>
            <thead>
                <tr>
                    <th>Kasir</th>
                    <th>Cabang</th>
                    <th>Buka</th>
                    <th>Tutup</th>
                    <th>Sistem</th>
                    <th>Fisik</th>
                    <th>Selisih</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shifts as $shift)
                    <tr class="{{ $shift->variance !== null && (float) $shift->variance != 0.0 ? 'table-warning' : '' }}">
                        <td>{{ $shift->cashier?->name }}</td>
                        <td>{{ $shift->branch?->name }}</td>
                        <td>{{ $shift->opened_at?->format('d M Y H:i') }}</td>
                        <td>{{ $shift->closed_at?->format('d M Y H:i') ?: '-' }}</td>
                        <td>{{ $shift->system_balance !== null ? number_format((float) $shift->system_balance, 0, ',', '.') : '-' }}</td>
                        <td>{{ $shift->closing_balance !== null ? number_format((float) $shift->closing_balance, 0, ',', '.') : '-' }}</td>
                        <td>{{ $shift->variance !== null ? number_format((float) $shift->variance, 0, ',', '.') : '-' }}</td>
                        <td><span class="{{ $shift->status->badgeClass() }}">{{ $shift->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">Belum ada shift.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($shifts->hasPages())
            <div class="mt-3">{{ $shifts->links() }}</div>
        @endif
    </x-card>
@endsection
