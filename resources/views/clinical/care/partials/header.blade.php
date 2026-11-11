@php
    $patient = $visit->patient;
    $visitTime = $visit->queue?->called_at ?? $visit->created_at;
    $recordFinal = $recordFinal ?? false;
    $recordLocked = $recordLocked ?? false;
    $fromPelayanan = request('from') === 'pelayanan';
    $invoice = $visit->currentInvoice();
    $prescription = $visit->currentPharmacyPrescription();
    $canPharmacy = auth()->user()?->can('prescription.view') && auth()->user()?->can('pharmacy.view');
    $isFollowUp = ($assessmentContext['kind'] ?? $record?->assessment_kind) === \App\Enums\AssessmentKind::FollowUp;
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
        <div class="patient-banner__hub">
            <a href="{{ route('examinations.initial') }}" class="btn btn-sm btn-light">
                <i class="bx bx-left-arrow-alt me-1"></i>Kembali
            </a>
            @if ($prescription && $canPharmacy)
                <a href="{{ route('pharmacy.prescriptions.show', ['prescription' => $prescription, 'from' => 'pelayanan']) }}" class="btn btn-sm btn-info">
                    <i class="bx bx-package me-1"></i>Obat
                </a>
            @endif
            @can('viewAny', App\Models\Invoice::class)
                @if ($invoice)
                    <a href="{{ route('billing.invoices.show', ['invoice' => $invoice, 'from' => 'pelayanan']) }}" class="btn btn-sm btn-warning">
                        <i class="bx bx-receipt me-1"></i>Tagihan
                    </a>
                @elseif (auth()->user()?->can('create', App\Models\Invoice::class))
                    <form action="{{ route('billing.invoices.generate') }}" method="POST" class="d-inline-flex">
                        @csrf
                        <input type="hidden" name="visit_id" value="{{ $visit->id }}">
                        <input type="hidden" name="from" value="pelayanan">
                        <button class="btn btn-sm btn-warning" type="submit">
                            <i class="bx bx-receipt me-1"></i>Tagihan
                        </button>
                    </form>
                @endif
            @endcan
        </div>
        <div class="patient-banner__tools">
            @can('patient_history.view')
                <a href="{{ route('patients.history.show', $visit->patient) }}" class="btn btn-sm btn-secondary">
                    <i class="bx bx-history me-1"></i>Riwayat
                </a>
            @endcan
            @if ($isFollowUp)
                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#assessment-baseline-modal">
                    <i class="bx bx-file me-1"></i>Asesmen awal
                </button>
            @endif
            @can('medical_record.view')
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#sick-letter-modal">
                    <i class="bx bx-envelope me-1"></i>Surat
                </button>
            @endcan
            @if ($visit->status->isOpen() && auth()->user()?->isDentist() && auth()->user()?->can('queue.manage'))
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#care-complete-modal">
                    <i class="bx bx-check me-1"></i>Simpan &amp; Selesaikan Kunjungan
                </button>
            @endif
            @can('satusehat.view')
                @if ($visit->status === \App\Enums\VisitStatus::Done)
                    <form action="{{ route('care.satusehat.send', $visit) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-info">
                            <i class="bx bx-cloud-upload me-1"></i>
                            Kirim SATUSEHAT
                            @if ($visit->satusehat_status)
                                ({{ $visit->satusehat_status->label() }})
                            @endif
                        </button>
                    </form>
                @endif
            @endcan
            @unless ($fromPelayanan)
                <a href="{{ route('queue.today') }}" class="btn btn-sm btn-light">
                    <i class="bx bx-list-ol me-1"></i>Antrean
                </a>
            @endunless
        </div>
    </div>
    @if ($recordLocked)
        <p class="mt-2 mb-0 text-warning">Rekam medis terkunci.</p>
    @elseif ($recordFinal)
        <p class="mt-2 mb-0">Rekam medis final. Koreksi isi sampai {{ $correctUntil?->format('d M Y H:i') }}.</p>
    @endif
</section>

@can('medical_record.view')
    <x-modal id="sick-letter-modal" title="Buat surat" size="modal-lg" class="care-modal">
        @include('clinical.care.partials.plan-letters', ['embedded' => true])
    </x-modal>
@endcan
