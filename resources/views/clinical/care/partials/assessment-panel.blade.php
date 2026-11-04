@php
    use App\Enums\AssessmentChange;
    use App\Enums\AssessmentKind;
    use App\Enums\AssessmentStatus;

    $context = $assessmentContext ?? [];
    $kind = $context['kind'] ?? $record?->assessment_kind;
    $status = $context['status'] ?? $record?->assessment_status ?? AssessmentStatus::Draft;
    $baseline = $context['baseline'] ?? null;
    $historyVisits = $context['history'] ?? collect();
    $isFollowUp = $kind === AssessmentKind::FollowUp;
    $review = is_array($record?->follow_up_review) ? $record->follow_up_review : [];
    $indicators = is_array($review['indicators'] ?? null) ? $review['indicators'] : [];
    $outcomes = is_array($review['diagnosis_outcomes'] ?? null) ? $review['diagnosis_outcomes'] : [];
    $procedureEvals = is_array($review['procedure_evals'] ?? null) ? $review['procedure_evals'] : [];
    $registration = $visit->id ? (new \App\Support\Clinical\ExaminationQueueRow($visit))->registrationNumber() : '-';
    $baseRecord = $baseline?->medicalRecord;
    $planLabels = [
        'continue' => 'Melanjutkan perawatan',
        'change' => 'Mengubah rencana perawatan',
        'additional' => 'Tindakan tambahan',
        'control' => 'Menjadwalkan kontrol',
        'end' => 'Mengakhiri perawatan',
        'refer' => 'Merujuk pasien',
    ];
    $outcomeLabels = [
        'unchanged' => 'Tetap',
        'changed' => 'Berubah',
        'added' => 'Tambahan',
        'resolved' => 'Terselesaikan',
    ];
    $canEditAssessment = ($writable ?? false) && auth()->user()?->can('medical_record.update');
@endphp

<div class="border rounded p-3 mb-3" id="asesmen" hidden>
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
        <div>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                @if ($kind)
                    <span class="{{ $kind->badgeClass() }}">{{ $kind->label() }}</span>
                @endif
                <span class="{{ $status instanceof AssessmentStatus ? $status->badgeClass() : 'badge bg-warning' }}">{{ $status instanceof AssessmentStatus ? $status->label() : 'Draft' }}</span>
            </div>
            <div class="text-muted small">
                No. kunjungan {{ $registration }}
                · {{ $visit->visit_date?->format('d M Y') ?: '-' }}
                · {{ $visit->doctor?->name ?: 'Dokter belum diisi' }}
                @if ($record?->assessment_completed_at)
                    · Selesai {{ $record->assessment_completed_at->format('d M Y H:i') }}
                @endif
            </div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
        <a class="btn btn-sm btn-light" href="{{ route('care.show', ['visit' => $visit, 'tab' => 'odontogram']) }}" data-care-section="odontogram">Odontogram</a>
        <a class="btn btn-sm btn-light" href="{{ route('care.show', ['visit' => $visit, 'tab' => 'tindakan']) }}" data-care-section="tindakan">Tindakan</a>
        <a class="btn btn-sm btn-light" href="{{ route('care.show', ['visit' => $visit, 'tab' => 'resep']) }}" data-care-section="resep">Resep</a>
        <a class="btn btn-sm btn-light" href="{{ route('care.show', ['visit' => $visit, 'tab' => 'rujukan']) }}" data-care-section="rujukan">Rujukan</a>
    </div>

    @if ($historyVisits->count() > 1)
        <div class="mt-3">
            <div class="fw-semibold mb-1">Riwayat asesmen episode</div>
            <ul class="mb-0">
                @foreach ($historyVisits as $item)
                    <li>
                        {{ $item->visit_date?->format('d M Y') ?: '-' }}
                        · {{ $item->medicalRecord?->assessment_kind?->label() ?: 'Belum dinilai' }}
                        · {{ $item->medicalRecord?->chief_complaint ?: 'Tanpa keluhan' }}
                        @if ((int) $item->id !== (int) $visit->id)
                            · <a href="{{ route('care.show', ['visit' => $item, 'tab' => 'soap']) }}">Lihat</a>
                        @else
                            · kunjungan ini
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

@if ($isFollowUp)
    <x-modal id="assessment-baseline-modal" title="Asesmen awal" size="modal-xl modal-dialog-scrollable" class="care-modal">
        @if (! $baseline || (int) $baseline->id === (int) $visit->id)
            <div class="alert alert-warning mb-0">Episode ini belum punya asesmen awal. Asesmen ulang tidak dapat difinalisasi sebelum asesmen awal tersimpan.</div>
        @else
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="text-muted small">
                    {{ $baseline->visit_date?->format('d M Y') ?: '-' }}
                    · {{ $baseline->doctor?->name ?: 'Dokter belum diisi' }}
                    · {{ $baseRecord?->assessment_kind?->label() ?: 'Asesmen awal' }}
                </div>
                <a href="{{ route('care.show', ['visit' => $baseline, 'tab' => 'soap', 'from' => request('from')]) }}" class="btn btn-sm btn-light">
                    Buka kunjungan awal
                </a>
            </div>
            @include('clinical.care.partials.soap-readonly', ['sheetVisit' => $baseline])
        @endif
    </x-modal>
@endif
