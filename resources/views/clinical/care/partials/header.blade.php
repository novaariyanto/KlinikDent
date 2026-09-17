@php
    $patient = $visit->patient;
    $visitTime = $visit->queue?->called_at ?? $visit->created_at;
@endphp

<section class="patient-banner" id="identitas">
    <div class="patient-banner__main">
        <div class="patient-banner__identity">
            <div class="patient-banner__name">{{ $patient?->name }}</div>
            <div class="patient-banner__rm">No. RM {{ $patient?->medical_record_number }}</div>
        </div>
        <span class="{{ $visit->status->badgeClass() }} patient-banner__status">{{ $visit->status->label() }}</span>
    </div>
    <div class="patient-banner__meta">
        <div><span>NIK</span>{{ $patient?->nik ?: '-' }}</div>
        <div><span>Jenis kelamin</span>{{ $patient?->gender?->label() ?: '-' }}</div>
        <div><span>Umur</span>{{ $patient?->ageLabel() }}</div>
        <div><span>Kunjungan</span>{{ $visit->visit_date?->format('d M Y') }}{{ $visitTime ? ' · '.$visitTime->format('H:i') : '' }}</div>
        <div><span>Dokter</span>{{ $visit->doctor?->name ?: '-' }}</div>
        <div><span>Poli</span>{{ $visit->room?->name ?: '-' }}</div>
    </div>
    <div class="patient-banner__actions">
        @can('patient_history.view')
            <x-button href="{{ route('patients.history.show', $visit->patient) }}" variant="light" icon="bx bx-history">Riwayat</x-button>
        @endcan
        <x-button href="{{ route('queue.today') }}" variant="secondary">Antrean</x-button>
    </div>
</section>
