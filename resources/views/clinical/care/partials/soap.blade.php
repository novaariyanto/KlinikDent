@php
    $embedded = $embedded ?? false;
    $followUp = $followUp ?? false;
    $review = is_array($record?->follow_up_review) ? $record->follow_up_review : [];
    $complaintValue = old('chief_complaint', filled($record?->chief_complaint) ? $record->chief_complaint : ($followUp ? ($review['complaint_now'] ?? '') : ''));
    $anamnesisValue = old('anamnesis', filled($record?->anamnesis) ? $record->anamnesis : ($followUp ? collect([$review['complaint_change'] ?? null, $review['prior_response'] ?? null, $review['compliance'] ?? null, $review['health_change'] ?? null])->filter(fn ($line) => filled($line))->implode("\n") : ''));
@endphp
<section class="care-section{{ $embedded ? ' care-section--embedded' : '' }}" id="keluhan">
    @unless ($embedded)
        <div class="care-section__head">
            <h5 class="care-section__title">Subjective / Keluhan &amp; Anamnesis</h5>
            <p class="care-section__hint">Keluhan utama mudah diisi. Anamnesis opsional.</p>
        </div>
    @endunless
    <div class="care-section__body">
        @can('medical_record.update')
            @if ($writable)
                <form action="{{ route('care.record.update', $visit) }}" method="POST" data-autosave>
                    @csrf
                    @method('PUT')
                    <div class="soap-field">
                        <textarea name="chief_complaint" id="chief_complaint" rows="2" class="form-control" placeholder="{{ $followUp ? 'Keluhan pada kunjungan ini' : 'Keluhan utama pasien' }}">{{ $complaintValue }}</textarea>
                    </div>
                </form>
            @else
                <p>{{ $complaintValue ?: 'Belum ada keluhan.' }}</p>
            @endif
        @elsecan('medical_record.view')
            <p>{{ $complaintValue ?: '—' }}</p>
        @endcan

        @unless ($embedded)
            @can('anamnesis.manage')
                @if ($writable)
                    <form action="{{ route('care.anamnesis.update', $visit) }}" method="POST" data-autosave>
                        @csrf
                        @method('PUT')
                        <div class="soap-field">
                            <textarea name="anamnesis" id="anamnesis" rows="3" class="form-control" placeholder="{{ $followUp ? 'Keluhan, respons tindakan, dan kepatuhan' : 'Riwayat keluhan, opsional' }}">{{ $anamnesisValue }}</textarea>
                        </div>
                    </form>
                @endif
            @elsecan('anamnesis.view')
                <p class="text-muted mb-0">{{ $anamnesisValue }}</p>
            @endcan
        @endunless
    </div>
</section>
