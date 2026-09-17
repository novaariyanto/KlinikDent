@extends('layouts.app')

@section('title', 'Riwayat Pasien')

@section('content')
    <x-page-header title="Riwayat Pasien" :breadcrumb="['Pelayanan' => route('examinations.index'), 'Riwayat Pasien' => null]" />
    <x-alert />
    <x-card title="Cari Pasien">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" name="q" value="{{ $term }}" class="form-control" placeholder="Nama, No. RM, NIK, telepon">
            </div>
            <div class="col-md-2">
                <x-button type="submit" icon="bx bx-search">Cari</x-button>
            </div>
        </form>
        <x-table>
            <thead>
                <tr>
                    <th>No. RM</th>
                    <th>Nama</th>
                    <th>Kunjungan terakhir</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($patients as $patient)
                    <tr>
                        <td>{{ $patient->medical_record_number }}</td>
                        <td>{{ $patient->name }}</td>
                        <td>{{ $patient->visits->first()?->visit_date?->format('d M Y') ?: '-' }}</td>
                        <td class="text-end">
                            <x-button href="{{ route('patients.history.show', $patient) }}" variant="light" icon="bx bx-history">Riwayat</x-button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Tidak ada pasien dengan kunjungan.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($patients->hasPages())
            <div class="mt-3">{{ $patients->links() }}</div>
        @endif
    </x-card>
@endsection
