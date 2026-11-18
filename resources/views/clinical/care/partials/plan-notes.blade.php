@php
    $storedPlan = is_array($record?->plan_instructions) ? $record->plan_instructions : [];
    $postedPlan = old('plan_instructions');
    $instructions = is_array($postedPlan) ? $postedPlan : $storedPlan;
    $instructionItems = is_array($instructions['items'] ?? null) ? $instructions['items'] : [];
    $instructionExtra = $instructions === []
        ? (string) ($record?->clinical_notes ?? '')
        : (string) ($instructions['extra'] ?? '');
    $planStatus = (string) ($instructions['status'] ?? '');
    $canEditPlan = $writable && auth()->user()?->can('medical_record.update');
    $canEditNotes = $writable && auth()->user()?->can('care_note.manage');
    $statusLabel = $examOptions['care_plan_statuses'][$planStatus] ?? null;
    $checkedLabels = collect($instructionItems)
        ->map(fn ($key) => $examOptions['instructions'][$key] ?? (is_string($key) ? $key : null))
        ->filter()
        ->values();
    $embedded = $embedded ?? false;
    $notesPart = $notesPart ?? 'all';
    $showPlan = in_array($notesPart, ['all', 'plan'], true);
    $showNotes = in_array($notesPart, ['all', 'notes'], true);
@endphp
<section class="care-section{{ $embedded ? ' care-section--embedded' : '' }}" id="{{ $showNotes && ! $showPlan ? 'catatan-khusus' : 'instruksi' }}">
    @unless ($embedded)
        <div class="care-section__head d-flex justify-content-between align-items-start gap-2">
            <div>
                <h5 class="care-section__title">Instruksi</h5>
                <p class="care-section__hint">Centang instruksi yang diberikan. Teks tambahan wajib jika memilih Instruksi lainnya.</p>
            </div>
            @can('medical_record.view')
                <a href="{{ route('care.instructions.pdf', $visit) }}" class="btn btn-sm btn-soft-secondary" target="_blank">
                    <i class="bx bx-printer me-1"></i>Cetak PDF
                </a>
            @endcan
        </div>
    @endunless
    <div class="care-section__body">
        @if ($showPlan)
        @can('medical_record.view')
            @if ($embedded)
                <div class="d-flex justify-content-end mb-2">
                    <a href="{{ route('care.instructions.pdf', $visit) }}" class="btn btn-sm btn-soft-secondary" target="_blank">
                        <i class="bx bx-printer me-1"></i>Cetak PDF
                    </a>
                </div>
            @endif
            <div>
                @if ($canEditPlan)
                    <form action="{{ route('care.record.update', $visit) }}" method="POST" data-autosave data-plan-form class="care-form">
                        @csrf
                        @method('PUT')
                        <div class="soap-plan">
                            <div class="soap-plan__block">
                                <span class="soap-plan__label">Instruksi pasien</span>
                                <div class="instruksi-grid">
                                    @foreach ($examOptions['instructions'] as $value => $label)
                                        <label class="exam-choice exam-choice--check">
                                            <input type="checkbox" name="plan_instructions[items][]" value="{{ $value }}" @checked(in_array($value, $instructionItems, true))>
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="soap-plan__block">
                                <span class="soap-plan__label">Rencana perawatan</span>
                                <div class="soap-plan__fields">
                                    <div>
                                        <label class="form-label" for="plan_instructions_extra">Instruksi tambahan / edukasi</label>
                                        <textarea name="plan_instructions[extra]" id="plan_instructions_extra" rows="2" class="form-control">{{ $instructionExtra }}</textarea>
                                    </div>
                                    <div>
                                        <label class="form-label" for="plan_instructions_goal">Tujuan perawatan</label>
                                        <textarea name="plan_instructions[goal]" id="plan_instructions_goal" rows="2" class="form-control">{{ $instructions['goal'] ?? '' }}</textarea>
                                    </div>
                                    <div class="soap-plan__pair">
                                        <div>
                                            <label class="form-label" for="plan_instructions_status">Status rencana</label>
                                            <select name="plan_instructions[status]" id="plan_instructions_status" class="form-select">
                                                <option value="">—</option>
                                                @foreach ($examOptions['care_plan_statuses'] as $value => $label)
                                                    <option value="{{ $value }}" @selected($planStatus === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label" for="control_date">Tanggal kontrol</label>
                                            <input type="date" name="control_date" id="control_date" class="form-control" value="{{ old('control_date', $record?->control_date?->format('Y-m-d')) }}">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label" for="plan_instructions_prognosis">Prognosa</label>
                                        <textarea name="plan_instructions[prognosis]" id="plan_instructions_prognosis" rows="2" class="form-control" placeholder="cth: Ad bonam">{{ $instructions['prognosis'] ?? '' }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                @else
                    <div class="soap-plan">
                        <div class="soap-plan__block">
                            <span class="soap-plan__label">Instruksi pasien</span>
                            <div>{{ $checkedLabels->isNotEmpty() ? $checkedLabels->implode(', ') : 'Belum dicatat' }}</div>
                        </div>
                        <div class="soap-plan__block">
                            <span class="soap-plan__label">Rencana perawatan</span>
                            <div class="soap-plan__fields">
                                <div><span class="text-muted small">Instruksi tambahan</span><div>{{ filled($instructionExtra) ? $instructionExtra : 'Belum dicatat' }}</div></div>
                                <div><span class="text-muted small">Tujuan perawatan</span><div>{{ filled($instructions['goal'] ?? null) ? $instructions['goal'] : 'Belum dicatat' }}</div></div>
                                <div class="soap-plan__pair">
                                    <div><span class="text-muted small">Status rencana</span><div>{{ $statusLabel ?: 'Belum dipilih' }}</div></div>
                                    <div><span class="text-muted small">Tanggal kontrol</span><div>{{ $record?->control_date?->format('d M Y') ?: 'Belum dicatat' }}</div></div>
                                </div>
                                <div><span class="text-muted small">Prognosa</span><div>{{ filled($instructions['prognosis'] ?? null) ? $instructions['prognosis'] : 'Belum dicatat' }}</div></div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endcan
        @endif

        @if ($showNotes)
        @can('care_note.view')
            <div>
                @unless ($embedded)
                    <div class="fw-semibold mb-2">Catatan pelayanan</div>
                @endunless
                @if ($canEditNotes)
                    <form action="{{ route('care.notes.update', $visit) }}" method="POST" data-autosave>
                        @csrf
                        @method('PUT')
                        <div class="soap-field">
                            @unless ($embedded)
                                <label class="form-label" for="care_notes">Catatan internal pelayanan</label>
                            @endunless
                            <textarea name="care_notes" id="care_notes" rows="2" class="form-control" placeholder="Catatan untuk tim pelayanan" @if ($embedded) aria-label="Catatan" @endif>{{ old('care_notes', $record?->care_notes) }}</textarea>
                        </div>
                    </form>
                @else
                    <p class="mb-0">{{ $record?->care_notes ?: 'Belum ada catatan.' }}</p>
                @endif
            </div>
        @endcan
        @endif
    </div>
</section>
