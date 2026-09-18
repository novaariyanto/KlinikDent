@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Laporan' => route('reports.index'), $title => null]" />
    <x-alert />
    <div class="row">
        @foreach (['total' => 'Total', 'waiting' => 'Menunggu', 'in_service' => 'Dilayani', 'done' => 'Selesai', 'cancelled' => 'Batal'] as $key => $label)
            <div class="col-md-4 col-xl">
                <x-card :title="$label">
                    <h3 class="mb-0">{{ $counts[$key] ?? 0 }}</h3>
                </x-card>
            </div>
        @endforeach
    </div>
    <x-card title="Per periode">
        <form method="GET">
            @include('finance.partials.period-filter')
        </form>
        <x-table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th class="text-end">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byDay as $day => $total)
                    <tr>
                        <td>{{ $day }}</td>
                        <td class="text-end">{{ $total }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted">Tidak ada kunjungan pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
    <x-card title="Daftar kunjungan">
        <x-table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Pasien</th>
                    <th>Dokter</th>
                    <th>Cabang</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($visits as $visit)
                    <tr>
                        <td>{{ $visit->visit_date?->format('d M Y') }}</td>
                        <td>{{ $visit->patient?->name }}</td>
                        <td>{{ $visit->doctor?->name ?: '-' }}</td>
                        <td>{{ $visit->branch?->name }}</td>
                        <td><span class="{{ $visit->status->badgeClass() }}">{{ $visit->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($visits->hasPages())
            <div class="mt-3">{{ $visits->links() }}</div>
        @endif
    </x-card>
@endsection
