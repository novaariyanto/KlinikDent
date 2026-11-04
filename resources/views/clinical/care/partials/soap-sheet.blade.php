@php
    $sys = is_array($systemicHistory ?? null) ? $systemicHistory : [];
    $allergyStatus = (string) ($sys['allergy_status'] ?? '');
    $allergyCategory = (string) ($sys['allergy_category'] ?? '');
    $allergyDetail = trim((string) ($sys['allergy_detail'] ?? ''));
    $allergyBuckets = [
        'food' => 'Alergi Makanan',
        'environment' => 'Alergi Udara',
        'medication' => 'Alergi Obat',
        'biologic' => 'Alergi lainnya',
    ];
    $allergyLine = match ($allergyStatus) {
        'none_confirmed' => collect($allergyBuckets)->map(fn ($label) => $label.' : Tidak ada')->implode(', '),
        'unknown' => 'Tidak diketahui',
        'present' => collect($allergyBuckets)->map(function ($label, $key) use ($allergyCategory, $allergyDetail) {
            if ($allergyCategory === $key) {
                return $label.' : Ada'.($allergyDetail !== '' ? ' — '.$allergyDetail : '');
            }

            return $label.' : Tidak ada';
        })->implode(', '),
        default => 'Belum ditanyakan',
    };

    $exam = is_array($dentalExam ?? null) ? $dentalExam : [];
    $labelOf = function (mixed $value, string $group) use ($examOptions): ?string {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $options = $examOptions[$group] ?? [];

        return $options[$value] ?? $value;
    };
    $withNote = function (?string $value, mixed $note): ?string {
        $text = $value ?: '';
        if (filled($note)) {
            $text = trim($text.($text !== '' ? ' — ' : '').$note);
        }

        return $text !== '' ? $text : null;
    };
    $occlusion = ($exam['occlusion'] ?? null) === 'deep' ? 'steep' : ($exam['occlusion'] ?? null);
    $examLines = array_values(array_filter([
        ($text = $withNote($labelOf($exam['extraoral'] ?? null, 'finding'), $exam['extraoral_note'] ?? null)) ? 'Ekstraoral: '.$text : null,
        ($text = $withNote($labelOf($exam['intraoral'] ?? null, 'finding'), $exam['intraoral_note'] ?? null)) ? 'Intraoral: '.$text : null,
        ($text = $labelOf($occlusion, 'occlusion')) ? 'Occlusi: '.$text : null,
        ($text = $labelOf($exam['gingiva'] ?? null, 'gingiva')) ? 'Gingiva: '.$text : null,
        ($text = $labelOf($exam['mucosa'] ?? null, 'mucosa')) ? 'Mukosa: '.$text : null,
        ($text = $labelOf($exam['palate'] ?? null, 'palate')) ? 'Palatum: '.$text : null,
        ($text = $labelOf($exam['torus_palatinus'] ?? null, 'torus')) ? 'Torus palatinus: '.$text : null,
        ($text = $labelOf($exam['torus_mandibularis'] ?? null, 'torus_mandibularis')) ? 'Torus mandibularis: '.$text : null,
        ($text = $withNote($labelOf($exam['diastema'] ?? null, 'presence'), $exam['diastema_elements'] ?? null)) ? 'Diastema: '.$text : null,
        ($text = $withNote($labelOf($exam['supernumerary'] ?? null, 'presence'), $exam['supernumerary_teeth'] ?? null)) ? 'Supernumerary: '.$text : null,
        ($text = $withNote($labelOf($exam['anomaly'] ?? null, 'presence'), $exam['anomaly_description'] ?? null)) ? 'Anomali: '.$text : null,
        filled($exam['habits'] ?? null) ? 'Kebiasaan: '.$exam['habits'] : null,
        filled($record?->initial_examination) ? $record->initial_examination : null,
    ]));
    $ohis = is_array($exam['ohis'] ?? null) ? $exam['ohis'] : [];
    if (is_numeric($ohis['debris_index'] ?? null) && is_numeric($ohis['calculus_index'] ?? null)) {
        $examLines[] = 'OHIS: DI '.$ohis['debris_index'].' · CI '.$ohis['calculus_index'];
    }
    $examSummary = $examLines === [] ? null : implode('; ', $examLines);
    $canEditExam = $writable && auth()->user()?->can('examination.manage');

    $planStored = is_array($record?->plan_instructions) ? $record->plan_instructions : [];
    $planBits = collect($planStored['items'] ?? [])
        ->map(fn ($key) => $examOptions['instructions'][$key] ?? (is_string($key) ? $key : null))
        ->filter()
        ->values();
    if (filled($planStored['goal'] ?? null)) {
        $planBits->push('Tujuan: '.$planStored['goal']);
    }
    if (filled($planStored['extra'] ?? null)) {
        $planBits->push($planStored['extra']);
    }
    $planSummary = $planBits->isNotEmpty() ? $planBits->implode('; ') : null;

    $labSummary = collect($labOrders ?? [])->map(function ($order) {
        $label = (string) $order->work_type;

        return filled($order->tooth_numbers) ? $label.' ('.$order->tooth_numbers.')' : $label;
    })->implode('; ');
    $labSummary = $labSummary !== '' ? $labSummary : null;

    $icd9Lines = $visit->procedureRecords
        ->filter(fn ($item) => filled($item->icd9_code))
        ->map(fn ($item) => \App\Support\Clinical\Icd9Catalog::label((string) $item->icd9_code))
        ->unique()
        ->values();
    $procedureLines = $visit->procedureRecords
        ->reject(fn ($item) => str_contains(mb_strtolower((string) ($item->procedure?->name ?? '')), 'konsultasi'))
        ->map(function ($item) {
            $parts = array_filter([
                $item->procedure?->name ?: 'Tindakan',
                filled($item->tooth_number) ? 'gigi '.$item->tooth_number : null,
                (int) $item->quantity > 1 ? 'qty '.$item->quantity : null,
            ]);

            return implode(', ', $parts);
        });
    $therapyLines = $visit->prescriptions->flatMap(function ($prescription) {
        return $prescription->items->map(function ($item) {
            $bits = array_filter([$item->label(), $item->dosage, $item->frequency, $item->duration]);

            return implode(' ', $bits);
        });
    });
    $canEditDiagnosis = $writable && auth()->user()?->can('diagnosis.manage');
    $isFollowUp = ($assessmentContext['kind'] ?? $record?->assessment_kind) === \App\Enums\AssessmentKind::FollowUp;
    $soapDiagnosis = $visit->diagnoses->sortByDesc('id')->first();
@endphp

<section class="care-section" id="soap">
    <div class="care-section__body">
        @if ($isFollowUp)
            @include('clinical.care.partials.follow-up-evaluasi', ['evaluasiPart' => 'form'])
        @endif
        <div class="soap-sheet-wrap">
            <table class="soap-sheet">
                <tbody>
                    <tr class="soap-row soap-row--keluhan">
                        <th class="soap-sheet__group soap-sheet__group--s" rowspan="2">Subjektif</th>
                        <th class="soap-sheet__label">Anamnesia / Keluhan</th>
                        <td class="soap-sheet__value">
                            <span class="soap-sheet__colon">:</span>
                            <div class="soap-sheet__fill">
                                @include('clinical.care.partials.soap', ['embedded' => true, 'followUp' => $isFollowUp])
                                @if ($isFollowUp)
                                    @include('clinical.care.partials.follow-up-evaluasi', ['evaluasiPart' => 'subjektif'])
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr class="soap-row soap-row--alergi">
                        <th class="soap-sheet__label">Riwayat Alergi</th>
                        <td class="soap-sheet__value">
                            <span class="soap-sheet__colon">:</span>
                            <div class="soap-sheet__fill">
                                <span data-allergy-summary>{{ $allergyLine }}</span>
                                @if ($isFollowUp)
                                    <span class="text-muted small ms-1">Ubah hanya jika ada perubahan.</span>
                                @endif
                                <button type="button" class="btn btn-sm btn-soft-primary ms-2" data-bs-toggle="modal" data-bs-target="#soap-allergy-modal" aria-label="Ubah riwayat alergi" title="Ubah"><i class="bx bx-edit"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr class="soap-row soap-row--vital">
                        <th class="soap-sheet__group soap-sheet__group--o" rowspan="3">Objektif</th>
                        <th class="soap-sheet__label">Vital Sign</th>
                        <td class="soap-sheet__value">
                            <span class="soap-sheet__colon">:</span>
                            <div class="soap-sheet__fill">
                                @include('clinical.care.partials.screening', ['embedded' => true, 'screeningPart' => 'vitals'])
                            </div>
                        </td>
                    </tr>
                    <tr class="soap-row soap-row--fisik">
                        <th class="soap-sheet__label">Pemeriksaan Fisik</th>
                        <td class="soap-sheet__value">
                            <span class="soap-sheet__colon">:</span>
                            <div class="soap-sheet__fill">
                                <span data-exam-summary @if (filled($record?->initial_examination)) data-exam-extra="{{ $record->initial_examination }}" @endif>{{ $examSummary ?: '—' }}</span>
                                @if ($canEditExam)
                                    <button type="button" class="btn btn-sm btn-soft-primary ms-2" data-bs-toggle="modal" data-bs-target="#pemeriksaan-modal" aria-label="Ubah pemeriksaan fisik" title="Ubah"><i class="bx bx-edit"></i></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr class="soap-row soap-row--penunjang">
                        <th class="soap-sheet__label">Penunjang</th>
                        <td class="soap-sheet__value">
                            <span class="soap-sheet__colon">:</span>
                            <div class="soap-sheet__fill">
                                <span data-lab-summary>{{ $labSummary ?: '—' }}</span>
                                <button type="button" class="btn btn-sm btn-soft-primary ms-2" data-bs-toggle="modal" data-bs-target="#soap-lab-modal" aria-label="Ubah penunjang" title="Ubah"><i class="bx bx-edit"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr class="soap-row soap-row--icd10">
                        <th class="soap-sheet__group soap-sheet__group--a" rowspan="2">Asesmen</th>
                        <th class="soap-sheet__label">Diagnosa-ICD-10</th>
                        <td class="soap-sheet__value">
                            <span class="soap-sheet__colon">:</span>
                            <div class="soap-sheet__fill">
                                <div class="soap-icd" id="soap-icd" data-store="{{ route('care.diagnosis.store', $visit) }}" data-search="{{ route('care.diagnosis.search', $visit) }}">
                                    @if ($canEditDiagnosis)
                                        <div class="soap-icd__one">
                                        <select class="form-select" data-icd-one data-search="{{ route('care.diagnosis.search', $visit) }}" data-store="{{ route('care.diagnosis.store', $visit) }}" @if ($soapDiagnosis) data-update="{{ route('care.diagnosis.update', [$visit, $soapDiagnosis]) }}" data-destroy="{{ route('care.diagnosis.destroy', [$visit, $soapDiagnosis]) }}" @endif>
                                            <option value=""></option>
                                            @if ($soapDiagnosis && filled($soapDiagnosis->code))
                                                <option value="{{ $soapDiagnosis->code }}" selected data-description="{{ $soapDiagnosis->description }}">{{ $soapDiagnosis->code }} — {{ $soapDiagnosis->description }}</option>
                                            @elseif ($soapDiagnosis)
                                                <option value="id:{{ $soapDiagnosis->id }}" selected data-description="{{ $soapDiagnosis->description }}">{{ $soapDiagnosis->description }}</option>
                                            @endif
                                        </select>
                                        <button type="button" class="btn btn-sm btn-soft-secondary" data-icd-reset @unless ($soapDiagnosis) hidden @endunless>Reset</button>
                                        </div>
                                    @else
                                        <span>{{ $soapDiagnosis ? trim((filled($soapDiagnosis->code) ? $soapDiagnosis->code.' — ' : '').$soapDiagnosis->description) : '—' }}</span>
                                    @endif
                                </div>
                                @if ($isFollowUp)
                                    @include('clinical.care.partials.follow-up-evaluasi', ['evaluasiPart' => 'asesmen'])
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr class="soap-row soap-row--icd9">
                        <th class="soap-sheet__label">Diagnosa-ICD-9-CM</th>
                        <td class="soap-sheet__value">
                            <span class="soap-sheet__colon">:</span>
                            <div class="soap-sheet__fill">
                                @forelse ($icd9Lines as $line)
                                    <div>{{ $line }}</div>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                                <a class="soap-sheet__jump" href="{{ route('care.show', ['visit' => $visit, 'tab' => 'tindakan']) }}" data-care-section="tindakan">Isi kode di tab Tindakan</a>
                            </div>
                        </td>
                    </tr>
                    <tr class="soap-row soap-row--rencana">
                        <th class="soap-sheet__group soap-sheet__group--p" rowspan="4">Planning</th>
                        <th class="soap-sheet__label">Rencana Asuhan</th>
                        <td class="soap-sheet__value">
                            <span class="soap-sheet__colon">:</span>
                            <div class="soap-sheet__fill">
                                <span data-plan-summary>{{ $planSummary ?: '—' }}</span>
                                <button type="button" class="btn btn-sm btn-soft-primary ms-2" data-bs-toggle="modal" data-bs-target="#soap-plan-modal" aria-label="Ubah rencana asuhan" title="Ubah"><i class="bx bx-edit"></i></button>
                                @if ($isFollowUp)
                                    @include('clinical.care.partials.follow-up-evaluasi', ['evaluasiPart' => 'rencana-ringkas'])
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr class="soap-row soap-row--catatan">
                        <th class="soap-sheet__label">Catatan Khusus</th>
                        <td class="soap-sheet__value">
                            <span class="soap-sheet__colon">:</span>
                            <div class="soap-sheet__fill">
                                @if ($writable && (auth()->user()?->can('medical_record.update') || auth()->user()?->can('care_note.manage')))
                                    <form action="{{ route('care.notes.update', $visit) }}" method="POST" data-autosave>
                                        @csrf
                                        @method('PUT')
                                        <textarea name="care_notes" id="care_notes" rows="2" class="form-control" placeholder="Catatan khusus" aria-label="Catatan khusus">{{ old('care_notes', $record?->care_notes) }}</textarea>
                                    </form>
                                @else
                                    <span>{{ filled($record?->care_notes) ? $record->care_notes : '—' }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr class="soap-row soap-row--terapi">
                        <th class="soap-sheet__label">Terapi / Resep</th>
                        <td class="soap-sheet__value">
                            <span class="soap-sheet__colon">:</span>
                            <div class="soap-sheet__fill">
                                @forelse ($therapyLines as $line)
                                    <div>{{ $line }}</div>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                                @can('prescription.view')
                                    <a class="soap-sheet__jump" href="{{ route('care.show', ['visit' => $visit, 'tab' => 'resep']) }}" data-care-section="resep">Kelola di tab Resep</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    <tr class="soap-row soap-row--tindakan">
                        <th class="soap-sheet__label">Tindakan</th>
                        <td class="soap-sheet__value">
                            <span class="soap-sheet__colon">:</span>
                            <div class="soap-sheet__fill">
                                @forelse ($procedureLines as $line)
                                    <div>{{ $line }}</div>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                                <a class="soap-sheet__jump" href="{{ route('care.show', ['visit' => $visit, 'tab' => 'tindakan']) }}" data-care-section="tindakan">Kelola di tab Tindakan</a>
                                @if ($isFollowUp)
                                    @include('clinical.care.partials.follow-up-evaluasi', ['evaluasiPart' => 'tindakan'])
                                @endif
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

@include('clinical.care.partials.dental-exam', ['modalOnly' => true])

<x-modal id="soap-allergy-modal" title="Riwayat alergi" size="modal-xl modal-dialog-scrollable" class="care-modal">
    @include('clinical.care.partials.screening', ['embedded' => true, 'screeningPart' => 'systemic'])
</x-modal>

<x-modal id="soap-lab-modal" title="Penunjang" size="modal-xl modal-dialog-scrollable" class="care-modal">
    @include('clinical.care.partials.plan-lab', ['embedded' => true])
</x-modal>

<x-modal id="soap-plan-modal" title="{{ $isFollowUp ? 'Asesmen ulang' : 'Rencana asuhan' }}" size="modal-xl modal-dialog-scrollable" class="care-modal">
    @unless ($isFollowUp)
        @include('clinical.care.partials.plan-notes', ['embedded' => true, 'notesPart' => 'plan'])
    @endunless
    @if ($isFollowUp)
        @include('clinical.care.partials.follow-up-evaluasi', ['evaluasiPart' => 'rencana'])
    @endif
</x-modal>
