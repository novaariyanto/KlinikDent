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
@endphp
<section class="care-section" id="instruksi">
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
    <div class="care-section__body">
        @can('medical_record.view')
            <div class="mb-4">
                <div class="fw-semibold mb-2">Instruksi pasien</div>
                @if ($canEditPlan)
                    <form action="{{ route('care.record.update', $visit) }}" method="POST" data-autosave>
                        @csrf
                        @method('PUT')
                        <div class="instruksi-grid mb-3">
                            @foreach ($examOptions['instructions'] as $value => $label)
                                <label class="exam-choice exam-choice--check">
                                    <input type="checkbox" name="plan_instructions[items][]" value="{{ $value }}" @checked(in_array($value, $instructionItems, true))>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <label class="form-label" for="plan_instructions_extra">Instruksi tambahan / edukasi</label>
                        <textarea name="plan_instructions[extra]" id="plan_instructions_extra" rows="3" class="form-control mb-3">{{ $instructionExtra }}</textarea>
                        <label class="form-label" for="plan_instructions_goal">Tujuan perawatan</label>
                        <textarea name="plan_instructions[goal]" id="plan_instructions_goal" rows="2" class="form-control mb-3">{{ $instructions['goal'] ?? '' }}</textarea>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="plan_instructions_status">Status rencana</label>
                                <select name="plan_instructions[status]" id="plan_instructions_status" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($examOptions['care_plan_statuses'] as $value => $label)
                                        <option value="{{ $value }}" @selected($planStatus === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="control_date">Tanggal kontrol</label>
                                <input type="date" name="control_date" id="control_date" class="form-control" value="{{ old('control_date', $record?->control_date?->format('Y-m-d')) }}">
                            </div>
                        </div>
                        <label class="form-label mt-3" for="plan_instructions_prognosis">Prognosis</label>
                        <textarea name="plan_instructions[prognosis]" id="plan_instructions_prognosis" rows="2" class="form-control">{{ $instructions['prognosis'] ?? '' }}</textarea>
                    </form>
                @else
                    <div class="exam-readonly">
                        <div class="exam-readonly__wide">
                            <span>Instruksi</span>
                            {{ $checkedLabels->isNotEmpty() ? $checkedLabels->implode(', ') : 'Belum dicatat' }}
                        </div>
                        <div class="exam-readonly__wide">
                            <span>Instruksi tambahan</span>
                            {{ filled($instructionExtra) ? $instructionExtra : 'Belum dicatat' }}
                        </div>
                        <div class="exam-readonly__wide">
                            <span>Tujuan perawatan</span>
                            {{ filled($instructions['goal'] ?? null) ? $instructions['goal'] : 'Belum dicatat' }}
                        </div>
                        <div>
                            <span>Status rencana</span>
                            {{ $statusLabel ?: 'Belum dipilih' }}
                        </div>
                        <div>
                            <span>Tanggal kontrol</span>
                            {{ $record?->control_date?->format('d M Y') ?: 'Belum dicatat' }}
                        </div>
                        <div class="exam-readonly__wide">
                            <span>Prognosis</span>
                            {{ filled($instructions['prognosis'] ?? null) ? $instructions['prognosis'] : 'Belum dicatat' }}
                        </div>
                    </div>
                @endif
            </div>
        @endcan

        @can('care_note.view')
            <div>
                <div class="fw-semibold mb-2">Catatan pelayanan</div>
                @if ($canEditNotes)
                    <form action="{{ route('care.notes.update', $visit) }}" method="POST" data-autosave>
                        @csrf
                        @method('PUT')
                        <label class="form-label" for="care_notes">Catatan internal pelayanan</label>
                        <textarea name="care_notes" id="care_notes" rows="4" class="form-control">{{ old('care_notes', $record?->care_notes) }}</textarea>
                    </form>
                @else
                    <p class="mb-0">{{ $record?->care_notes ?: 'Belum ada catatan.' }}</p>
                @endif
            </div>
        @endcan
    </div>
</section>
