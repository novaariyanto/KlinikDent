@extends('layouts.app')

@section('title', 'Riwayat '.$patient->name)

@php
    $initials = collect(preg_split('/\s+/', trim($patient->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $address = collect([$patient->address, $patient->address_city])->filter(fn ($v) => filled($v))->implode(', ');
    $tabUrl = fn (string $key) => route('patients.history.show', ['patient' => $patient, 'tab' => $key]);
    $infoLabels = [
        'allergy' => ['Alergi', 'bx bx-error-circle'],
        'systemic' => ['Penyakit sistemik', 'bx bx-heart'],
        'medication' => ['Obat dikonsumsi', 'bx bx-capsule'],
        'special' => ['Catatan khusus', 'bx bx-note'],
    ];
@endphp

@section('content')
    <x-page-header :title="'Riwayat '.$patient->name" :breadcrumb="['Riwayat Pasien' => route('patients.history'), $patient->name => null]" />
    <x-alert />

    <div class="card ph-head">
        <div class="card-body">
            <div class="ph-head__main">
                <div class="ph-avatar" aria-hidden="true">{{ $initials ?: '?' }}</div>
                <div class="ph-identity">
                    <div class="ph-identity__name">{{ $patient->name }}</div>
                    <div class="ph-identity__meta">
                        <span>RM <strong>{{ $patient->medical_record_number }}</strong></span>
                        <span>{{ $patient->gender?->label() ?? 'Jenis kelamin belum ada data' }}</span>
                        <span>{{ $patient->dob ? $patient->ageLabel() : 'Usia belum ada data' }}</span>
                        <span>Lahir {{ $patient->dob?->format('d M Y') ?? 'belum ada data' }}</span>
                    </div>
                    <div class="ph-identity__meta">
                        <span><i class="bx bx-phone"></i> {{ $patient->phone ?: 'Belum ada data' }}</span>
                        <span><i class="bx bx-map"></i> {{ $address !== '' ? $address : 'Alamat belum ada data' }}</span>
                    </div>
                </div>
                <div class="ph-actions">
                    @can('registration.view')
                        <x-button href="{{ route('registration.patients.show', $patient) }}" variant="outline-secondary" icon="bx bx-arrow-back">Data Pasien</x-button>
                    @endcan
                    @can('create', \App\Models\Visit::class)
                        <x-button href="{{ route('registration.new', ['patient_id' => $patient->id]) }}" variant="primary" icon="bx bx-plus">Kunjungan Baru</x-button>
                    @endcan
                </div>
            </div>

            <div class="ph-stats">
                <div class="ph-stat"><span class="ph-stat__value">{{ $stats['visits'] }}</span><span class="ph-stat__label">Kunjungan</span></div>
                <div class="ph-stat"><span class="ph-stat__value">{{ $stats['diagnoses'] }}</span><span class="ph-stat__label">Diagnosis</span></div>
                <div class="ph-stat"><span class="ph-stat__value">{{ $stats['procedures'] }}</span><span class="ph-stat__label">Tindakan</span></div>
                <div class="ph-stat"><span class="ph-stat__value">{{ $stats['letters'] }}</span><span class="ph-stat__label">Surat / dokumen medis</span></div>
                <div class="ph-stat ph-stat--wide">
                    <span class="ph-stat__value ph-stat__value--sm">{{ $stats['first_visit']?->format('d M Y') ?? '-' }} — {{ $stats['last_visit']?->format('d M Y') ?? '-' }}</span>
                    <span class="ph-stat__label">Kunjungan pertama — terakhir</span>
                </div>
            </div>

            <div class="ph-info">
                @foreach ($infoLabels as $key => [$label, $icon])
                    @php $item = $info[$key]; @endphp
                    <div class="ph-info__item ph-info__item--{{ $item['state'] }}">
                        <div class="ph-info__label"><i class="{{ $icon }}"></i> {{ $label }}</div>
                        @if ($item['value'])
                            <div class="ph-info__value">{{ $item['value'] }}</div>
                            <div class="ph-info__date">Dicatat {{ $item['date']?->format('d M Y') ?? '-' }}</div>
                        @else
                            <div class="ph-info__value ph-info__value--empty">Belum ada data</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="ph-layout">
        <nav class="ph-nav" aria-label="Menu riwayat pasien">
            <div class="nav flex-column nav-pills">
                @foreach ($tabs as $key => $meta)
                    <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ $tabUrl($key) }}" @if ($tab === $key) aria-current="page" @endif>
                        <i class="{{ $meta['icon'] }}"></i><span>{{ $meta['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

        <div class="ph-tab-body">
            @include('clinical.history.tabs.'.$tab)
        </div>
    </div>
@endsection
