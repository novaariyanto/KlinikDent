@extends('layouts.app')

@section('title', 'Kunjungan')

@section('content')
    <x-page-header title="Kunjungan {{ $visit->patient?->name }}" :breadcrumb="['Kunjungan' => route('registration.history'), 'Detail' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Detail Kunjungan">
                <x-table>
                    <tbody>
                        <tr><th width="180">No. RM</th><td>{{ $visit->patient?->medical_record_number }}</td></tr>
                        <tr><th>Pasien</th><td><a href="{{ route('registration.patients.show', $visit->patient) }}">{{ $visit->patient?->name }}</a></td></tr>
                        <tr><th>Tanggal</th><td>{{ $visit->visit_date?->format('d M Y') }}</td></tr>
                        <tr><th>Cabang</th><td>{{ $visit->branch?->name }}</td></tr>
                        <tr><th>Nomor Antrean</th><td class="fs-4 fw-semibold">{{ $visit->queue?->displayNumber() ?: '-' }}</td></tr>
                        <tr><th>Dokter</th><td>{{ $visit->doctor?->name ?: '-' }}</td></tr>
                        <tr><th>Poli</th><td>{{ $visit->room?->name ?: '-' }}</td></tr>
                        <tr><th>Penjamin</th><td>{{ $visit->payer?->name }}</td></tr>
                        <tr>
                            <th>Status Kunjungan</th>
                            <td><span class="{{ $visit->status->badgeClass() }}">{{ $visit->status->label() }}</span></td>
                        </tr>
                        <tr>
                            <th>Status Antrean</th>
                            <td>
                                @if ($visit->queue)
                                    <span class="{{ $visit->queue->status->badgeClass() }}">{{ $visit->queue->status->label() }}</span>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </x-table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    @if (App\Support\Clinical\CareTabs::canAccessCare(auth()->user()))
                        <x-button href="{{ route('care.show', $visit) }}" icon="bx bx-plus-medical">Buka Pelayanan</x-button>
                    @endif
                    @can('cancel', $visit)
                        <form action="{{ route('registration.visits.cancel', $visit) }}" method="POST" onsubmit="return confirm('Batalkan kunjungan ini?')">
                            @csrf
                            <x-button type="submit" variant="danger" icon="bx bx-x">Batalkan</x-button>
                        </form>
                    @endcan
                    <x-button href="{{ route('registration.history') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
