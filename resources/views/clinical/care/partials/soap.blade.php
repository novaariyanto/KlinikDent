<section class="care-section" id="keluhan">
    <div class="care-section__head">
        <h5 class="care-section__title">Subjective / Keluhan &amp; Anamnesis</h5>
        <p class="care-section__hint">Keluhan utama mudah diisi. Anamnesis opsional.</p>
    </div>
    <div class="care-section__body">
        @can('medical_record.update')
            @if ($writable)
                <form action="{{ route('care.record.update', $visit) }}" method="POST" data-autosave>
                    @csrf
                    @method('PUT')
                    <label class="form-label" for="chief_complaint">Keluhan utama</label>
                    <textarea name="chief_complaint" id="chief_complaint" rows="2" class="form-control mb-3">{{ old('chief_complaint', $record?->chief_complaint) }}</textarea>
                </form>
            @else
                <p>{{ $record?->chief_complaint ?: 'Belum ada keluhan.' }}</p>
            @endif
        @elsecan('medical_record.view')
            <p>{{ $record?->chief_complaint ?: '—' }}</p>
        @endcan

        @can('anamnesis.manage')
            @if ($writable)
                <form action="{{ route('care.anamnesis.update', $visit) }}" method="POST" data-autosave>
                    @csrf
                    @method('PUT')
                    <label class="form-label" for="anamnesis">Anamnesis / riwayat keluhan</label>
                    <textarea name="anamnesis" id="anamnesis" rows="3" class="form-control">{{ old('anamnesis', $record?->anamnesis) }}</textarea>
                </form>
            @endif
        @elsecan('anamnesis.view')
            <p class="text-muted mb-0">{{ $record?->anamnesis ?: '' }}</p>
        @endcan
    </div>
</section>
