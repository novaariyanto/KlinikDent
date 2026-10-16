@extends('layouts.app')

@section('title', $patient->name)

@section('content')
    <x-page-header title="{{ $patient->name }}" :breadcrumb="['Pasien' => route('registration.patients'), $patient->name => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-5">
            <x-card title="Data Pasien">
                <x-table>
                    <tbody>
                        <tr><th width="160">No. RM</th><td>{{ $patient->medical_record_number }}</td></tr>
                        <tr><th>Nama</th><td>{{ $patient->name }}</td></tr>
                        <tr><th>NIK</th><td>{{ $patient->nik ?: '-' }}</td></tr>
                        <tr>
                            <th>IHS</th>
                            <td>
                                @if (filled($patient->satusehat_id))
                                    <code>{{ $patient->satusehat_id }}</code>
                                @else
                                    <span class="text-muted">Belum ada</span>
                                @endif
                            </td>
                        </tr>
                        <tr><th>Tanggal Lahir</th><td>{{ $patient->dob?->format('d M Y') ?: '-' }}</td></tr>
                        <tr><th>Tempat Lahir</th><td>{{ $patient->birth_place ?: '-' }}</td></tr>
                        <tr><th>Jenis Kelamin</th><td>{{ $patient->gender?->label() ?: '-' }}</td></tr>
                        <tr><th>Status Perkawinan</th><td>{{ $patient->marital_status?->label() ?: '-' }}</td></tr>
                        <tr><th>Kewarganegaraan</th><td>{{ $patient->citizenship?->label() ?: '-' }}</td></tr>
                        <tr><th>No. KK</th><td>{{ $patient->family_card_number ?: '-' }}</td></tr>
                        <tr><th>Telepon</th><td>{{ $patient->phone ?: '-' }}</td></tr>
                        <tr><th>Alamat</th><td>{{ $patient->address ?: '-' }}</td></tr>
                        <tr><th>Kota</th><td>{{ $patient->address_city ?: ($wilayahLabels['city'] ?? '-') }}{{ $patient->address_postal_code ? ' '.$patient->address_postal_code : '' }}</td></tr>
                        <tr>
                            <th>Wilayah</th>
                            <td class="small">
                                @if ($patient->hasSatuSehatAddress())
                                    {{ $wilayahLabels['province'] ?? $patient->province_code }}
                                    · {{ $wilayahLabels['city'] ?? $patient->city_code }}
                                    · {{ $wilayahLabels['district'] ?? $patient->district_code }}
                                    · {{ $wilayahLabels['village'] ?? $patient->village_code }}
                                    · RT/RW {{ $patient->rt }}/{{ $patient->rw }}
                                @else
                                    <span class="text-muted">Belum lengkap untuk SATUSEHAT</span>
                                @endif
                            </td>
                        </tr>
                        <tr><th>Penjamin</th><td>{{ $patient->defaultPayer?->name ?: '-' }}</td></tr>
                        <tr>
                            <th>BPJS</th>
                            <td>
                                {{ $patient->bpjs_number ?: '-' }}
                                @if ($patient->bpjs_status)
                                    <span class="{{ $patient->bpjs_status->badgeClass() }}">{{ $patient->bpjs_status->label() }}</span>
                                @endif
                                @if ($patient->bpjs_checked_at)
                                    <small class="text-muted">dicek {{ $patient->bpjs_checked_at->format('d M Y H:i') }}</small>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </x-table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('update', $patient)
                        <x-button href="{{ route('registration.patients.edit', $patient) }}" icon="bx bx-edit">Edit</x-button>
                        @if (filled($patient->nik) && ! filled($patient->satusehat_id))
                            @if ($patient->hasSatuSehatAddress())
                                <form action="{{ route('registration.patients.satusehat', $patient) }}" method="POST">
                                    @csrf
                                    <x-button type="submit" variant="secondary" icon="bx bx-cloud-download">Ambil IHS</x-button>
                                </form>
                            @else
                                <x-button
                                    href="{{ route('registration.patients.edit', ['patient' => $patient, 'ihs' => 1]) }}"
                                    variant="secondary"
                                    icon="bx bx-cloud-download"
                                >Lengkapi & Ambil IHS</x-button>
                            @endif
                        @endif
                    @endcan
                    @can('bpjs.view')
                        <form action="{{ route('registration.patients.bpjs', $patient) }}" method="POST">
                            @csrf
                            <x-button type="submit" variant="info" icon="bx bx-search">Cek BPJS</x-button>
                        </form>
                    @endcan
                    @can('create', App\Models\Visit::class)
                        <x-button href="{{ route('registration.new', ['patient_id' => $patient->id]) }}" icon="bx bx-plus">Daftarkan</x-button>
                    @endcan
                    @can('patient_history.view')
                        <x-button href="{{ route('patients.history.show', $patient) }}" variant="light" icon="bx bx-history">Riwayat Medis</x-button>
                    @endcan
                    <x-button href="{{ route('registration.patients') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
        <div class="col-lg-7">
            <x-card title="Riwayat Kunjungan">
                <x-table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Antrean</th>
                            <th>Dokter</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($patient->visits as $visit)
                            <tr>
                                <td><a href="{{ route('registration.visits.show', $visit) }}">{{ $visit->visit_date?->format('d M Y') }}</a></td>
                                <td>{{ $visit->queue?->displayNumber() ?: '-' }}</td>
                                <td>{{ $visit->doctor?->name ?: '-' }}</td>
                                <td><span class="{{ $visit->status->badgeClass() }}">{{ $visit->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">Belum ada kunjungan.</td></tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>
        </div>
    </div>
@endsection
