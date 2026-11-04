@extends('layouts.app')

@section('title', 'Histori Pembebasan Jasa Dokter')

@section('content')
    <x-page-header title="Histori Pembebasan & Diskon Tindakan" :breadcrumb="['Keuangan' => route('finance.index'), 'Histori Pembebasan' => null]" />
    <x-alert />

    <div class="row mb-3">
        <div class="col-md-4">
            <x-card>
                <p class="text-muted mb-1">Jasa dokter dibebaskan</p>
                <h3 class="mb-0">Rp {{ number_format((float) $summary['waiver'], 0, ',', '.') }}</h3>
            </x-card>
        </div>
        <div class="col-md-4">
            <x-card>
                <p class="text-muted mb-1">Diskon tindakan</p>
                <h3 class="mb-0">Rp {{ number_format((float) $summary['discount'], 0, ',', '.') }}</h3>
            </x-card>
        </div>
        <div class="col-md-4">
            <x-card>
                <p class="text-muted mb-1">Menunggu persetujuan</p>
                <h3 class="mb-0">{{ $summary['pending'] }}</h3>
            </x-card>
        </div>
    </div>

    <x-card title="Riwayat penyesuaian">
        <form method="GET">
            @include('finance.partials.period-filter')
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <x-select
                        name="doctor_user_id"
                        label="Dokter"
                        :options="$doctors->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()"
                        :selected="request('doctor_user_id')"
                        placeholder="Semua dokter"
                    />
                </div>
                <div class="col-md-3">
                    <x-select
                        name="adjustment_type"
                        label="Jenis"
                        :options="$adjustmentTypes"
                        :selected="request('adjustment_type')"
                        placeholder="Semua jenis"
                    />
                </div>
                <div class="col-md-3">
                    <x-select
                        name="status"
                        label="Status"
                        :options="$statuses"
                        :selected="request('status')"
                        placeholder="Semua status"
                    />
                </div>
                <div class="col-md-3">
                    <x-button type="submit" icon="bx bx-search">Terapkan</x-button>
                </div>
            </div>
        </form>

        <x-table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pasien / Tindakan</th>
                    <th>Dokter</th>
                    <th>Jenis</th>
                    <th class="text-end">Sebelum</th>
                    <th class="text-end">Sesudah</th>
                    <th>Alasan</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->waived_at?->format('d M Y H:i') ?: $log->created_at?->format('d M Y H:i') }}</td>
                        <td>
                            <div>{{ $log->visit?->patient?->name ?: '-' }}</div>
                            <div class="text-muted small">{{ $log->procedureRecord?->procedure?->name ?: '-' }}</div>
                        </td>
                        <td>
                            <div>{{ $log->doctor?->name ?: '-' }}</div>
                            <div class="text-muted small">oleh {{ $log->actor?->name ?: '-' }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $log->adjustment_type?->badgeClass() }}">{{ $log->adjustment_type?->label() }}</span>
                        </td>
                        <td class="text-end">{{ number_format((float) $log->doctor_fee_before, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format((float) $log->doctor_fee_after, 0, ',', '.') }}</td>
                        <td>{{ $log->reason ?: '-' }}</td>
                        <td>
                            <span class="badge {{ $log->status?->badgeClass() }}">{{ $log->status?->label() }}</span>
                        </td>
                        <td class="text-end">
                            @can('approve', $log)
                                <form method="POST" action="{{ route('finance.doctor-fee-waivers.logs.approve', $log) }}" onsubmit="return confirm('Setujui pembebasan ini?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">Setujui</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">Belum ada histori pembebasan atau diskon tindakan.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        <div class="mt-3">{{ $logs->links() }}</div>
    </x-card>
@endsection
