@extends('layouts.app')

@section('title', 'Tampil tindakan')

@php
    $back = route('care.show', array_filter([
        'visit' => $visit,
        'tab' => 'tindakan',
        'from' => request('from') === 'pelayanan' ? 'pelayanan' : null,
    ]));
    $historyUrl = route('care.procedure.history', array_filter([
        'visit' => $visit,
        'from' => request('from') === 'pelayanan' ? 'pelayanan' : null,
    ]));
    $showQuery = array_filter(['from' => request('from') === 'pelayanan' ? 'pelayanan' : null]);
    $item = $procedureRecord;
@endphp

@section('content')
    <x-page-header title="Tampil tindakan" :breadcrumb="['Pelayanan' => route('examinations.initial'), ($visit->patient?->name ?: 'Kunjungan') => $back, 'Tampil tindakan' => null]" />
    <x-alert />

    <x-card :title="$item->procedure?->name ?: 'Tindakan'">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small">Tanggal kunjungan</div>
                <div class="fw-semibold">{{ $item->visit?->visit_date?->format('d M Y') ?: '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Dokter</div>
                <div class="fw-semibold">{{ $item->visit?->doctor?->name ?: '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Gigi</div>
                <div class="fw-semibold">{{ $item->tooth_number ?: '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Qty</div>
                <div class="fw-semibold">{{ $item->quantity }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">ICD-9</div>
                <div class="fw-semibold">{{ $item->icd9_code ?: '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Status tagihan</div>
                <div class="fw-semibold">{{ $item->billing_status->label() }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Tarif pasien</div>
                <div class="fw-semibold">Rp {{ number_format((float) $item->price_at_time, 0, ',', '.') }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Penyesuaian</div>
                <div>
                    <span class="badge {{ $item->adjustment_type?->badgeClass() ?? 'bg-secondary' }}">{{ $item->adjustmentLabel() }}</span>
                    @if ($item->waiver_reason)
                        <div class="text-muted small">{{ $item->waiver_reason }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <x-button href="{{ $back }}" variant="secondary" icon="bx bx-arrow-back">Kembali ke tindakan</x-button>
            <x-button href="{{ $historyUrl }}" variant="light" icon="bx bx-history">Riwayat tindakan</x-button>
        </div>
    </x-card>

    <x-card title="Riwayat tindakan yang sama">
        <x-table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Gigi</th>
                    <th>Qty</th>
                    <th>Dokter</th>
                    <th class="text-end">Tampilkan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $row)
                    <tr>
                        <td class="text-nowrap">{{ $row->visit?->visit_date?->format('d M Y') ?: '—' }}</td>
                        <td>{{ $row->tooth_number ?: '—' }}</td>
                        <td>{{ $row->quantity }}</td>
                        <td>{{ $row->visit?->doctor?->name ?: '—' }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-soft-primary" href="{{ route('care.procedure.show', array_merge(['visit' => $visit, 'procedure_record' => $row], $showQuery)) }}">Tampilkan</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted">Belum ada riwayat tindakan yang sama.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
@endsection
