@php
    $instructions = old('plan_instructions', $record?->plan_instructions ?? []);
    $instructionItems = $instructions['items'] ?? [];
    $instructionExtra = $instructions['extra'] ?? (empty($instructions) ? $record?->clinical_notes : '');
@endphp
<section class="care-section" id="instruksi">
    <div class="care-section__head d-flex justify-content-between align-items-start gap-2">
        <div>
            <h5 class="care-section__title">Instruksi</h5>
            <p class="care-section__hint">Centang instruksi yang diberikan. Teks bebas opsional.</p>
        </div>
        @can('medical_record.view')
            <a href="{{ route('care.instructions.pdf', $visit) }}" class="btn btn-sm btn-soft-secondary" target="_blank">
                <i class="bx bx-printer me-1"></i>Cetak PDF
            </a>
        @endcan
    </div>
    <div class="care-section__body">
        <div class="row">
            @can('medical_record.view')
                <div class="col-lg-7">
                    @can('medical_record.update')
                        @if ($writable)
                            <form action="{{ route('care.record.update', $visit) }}" method="POST" data-autosave>
                                @csrf
                                @method('PUT')
                                <div class="sys-grid mb-3">
                                    @foreach ($examOptions['instructions'] as $value => $label)
                                        <label class="exam-choice exam-choice--check">
                                            <input type="checkbox" name="plan_instructions[items][]" value="{{ $value }}" @checked(in_array($value, $instructionItems, true))>
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <label class="form-label" for="plan_instructions_extra">Instruksi tambahan</label>
                                <textarea name="plan_instructions[extra]" id="plan_instructions_extra" rows="4" class="form-control">{{ $instructionExtra }}</textarea>
                            </form>
                        @else
                            <p class="mb-0">{{ $record?->clinical_notes ?: 'Belum ada instruksi.' }}</p>
                        @endif
                    @else
                        <p class="mb-0">{{ $record?->clinical_notes ?: 'Belum ada instruksi.' }}</p>
                    @endcan
                </div>
            @endcan

            @can('care_note.view')
                <div class="col-lg-5">
                    @can('care_note.manage')
                        @if ($writable)
                            <form action="{{ route('care.notes.update', $visit) }}" method="POST" data-autosave>
                                @csrf
                                @method('PUT')
                                <label class="form-label" for="care_notes">Catatan pelayanan</label>
                                <textarea name="care_notes" id="care_notes" rows="8" class="form-control">{{ old('care_notes', $record?->care_notes) }}</textarea>
                            </form>
                        @else
                            <label class="form-label">Catatan pelayanan</label>
                            <p class="mb-0">{{ $record?->care_notes ?: 'Belum ada catatan.' }}</p>
                        @endif
                    @else
                        <label class="form-label">Catatan pelayanan</label>
                        <p class="mb-0">{{ $record?->care_notes ?: 'Belum ada catatan.' }}</p>
                    @endcan
                </div>
            @endcan
        </div>
    </div>
</section>
