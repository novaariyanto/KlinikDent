@extends('layouts.app')

@php
    $isToday = $filters['date_from'] === $filters['date_to'] && $filters['date_from'] === now()->toDateString();
    $cardTitle = $meta['title'].($isToday ? ' — Kunjungan Hari Ini' : ' — Daftar Kunjungan');
@endphp

@section('title', $meta['title'])

@section('content')
    <x-page-header :title="$meta['title']" :breadcrumb="['Pelayanan' => route('examinations.index'), $meta['title'] => null]" />
    <x-alert />
    <x-card :title="$cardTitle">
        <form method="GET" action="{{ url()->current() }}" class="row g-2 align-items-end mb-3">
            <div class="col-xl-3 col-md-6">
                <label for="filter-q" class="form-label">Nama / No. RM</label>
                <input
                    id="filter-q"
                    type="text"
                    name="q"
                    value="{{ $filters['q'] }}"
                    class="form-control"
                    placeholder="Cari nama atau No. RM"
                >
            </div>
            <div class="col-xl-2 col-md-6">
                <label for="filter-date-from" class="form-label">Tanggal dari</label>
                <input id="filter-date-from" type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control">
            </div>
            <div class="col-xl-2 col-md-6">
                <label for="filter-date-to" class="form-label">Tanggal sampai</label>
                <input id="filter-date-to" type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control">
            </div>
            <div class="col-xl-3 col-md-6">
                <label for="filter-status" class="form-label">Status</label>
                <select id="filter-status" name="status" class="form-select w-100">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected((string) $filters['status'] === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-6">
                <x-button type="submit" icon="bx bx-filter-alt">Filter</x-button>
                <x-button href="{{ url()->current() }}" variant="light">Reset</x-button>
            </div>
        </form>
        <x-table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Antrean</th>
                    <th>Pasien</th>
                    <th>Dokter</th>
                    <th>Poli</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($visits as $visit)
                    <tr>
                        <td>{{ $visit->visit_date?->format('d M Y') ?: '-' }}</td>
                        <td>{{ $visit->queue?->displayNumber() ?: '-' }}</td>
                        <td>
                            <div class="fw-semibold">{{ $visit->patient?->name }}</div>
                            <div class="text-muted font-size-13">{{ $visit->patient?->medical_record_number }}</div>
                        </td>
                        <td>{{ $visit->doctor?->name ?: '-' }}</td>
                        <td>{{ $visit->room?->name ?: '-' }}</td>
                        <td><span class="{{ $visit->status->badgeClass() }}">{{ $visit->status->label() }}</span></td>
                        <td>
                            <div class="d-flex flex-wrap justify-content-end gap-1">
                                @if ($visit->queue)
                                    @can('call', $visit->queue)
                                        <form action="{{ route('queue.call', $visit->queue) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-soft-warning" type="submit">
                                                <i class="bx bx-bell me-1"></i>Panggil
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                                @if (App\Support\Clinical\CareTabs::canAccessCare(auth()->user()))
                                    <x-button href="{{ route('care.show', ['visit' => $visit, 'tab' => $meta['tab']]) }}" variant="primary" class="btn-sm" icon="bx bx-plus-medical">Buka Pelayanan</x-button>
                                @endif
                                @if ($visit->queue && $visit->status === \App\Enums\VisitStatus::InService)
                                    @can('complete', $visit->queue)
                                        <form action="{{ route('queue.complete', $visit->queue) }}" method="POST" class="d-inline" onsubmit="return confirm('Selesaikan pemeriksaan pasien ini?')">
                                            @csrf
                                            <button class="btn btn-sm btn-soft-success" type="submit">
                                                <i class="bx bx-check me-1"></i>Selesai
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-muted">
                            @if ($isToday && blank($filters['status']) && blank($filters['q']))
                                Belum ada kunjungan hari ini. Pasien yang sudah didaftarkan akan muncul di sini untuk dipanggil dan dilayani.
                            @else
                                Tidak ada kunjungan untuk filter yang dipilih.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($visits->hasPages())
            <div class="mt-3">{{ $visits->links() }}</div>
        @endif
    </x-card>
@endsection
