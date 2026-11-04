@php
    use App\Enums\AssessmentChange;

    $evaluasiPart = $evaluasiPart ?? 'form';
    $context = $assessmentContext ?? [];
    $review = is_array($record?->follow_up_review) ? $record->follow_up_review : [];
    $indicators = is_array($review['indicators'] ?? null) ? $review['indicators'] : [];
    $procedureEvals = is_array($review['procedure_evals'] ?? null) ? $review['procedure_evals'] : [];
    $planLabels = [
        'continue' => 'Melanjutkan perawatan',
        'change' => 'Mengubah rencana perawatan',
        'additional' => 'Tindakan tambahan',
        'control' => 'Menjadwalkan kontrol',
        'end' => 'Mengakhiri perawatan',
        'refer' => 'Merujuk pasien',
    ];
    $canEditAssessment = ($writable ?? false) && auth()->user()?->can('medical_record.update');
    $indicatorLabel = function (string $key) use ($indicators): ?string {
        $value = $indicators[$key] ?? '';

        return $value !== '' ? (AssessmentChange::tryFrom($value)?->label() ?? $value) : null;
    };
@endphp

@if ($evaluasiPart === 'form')
    @if ($canEditAssessment)
        <form method="POST" action="{{ route('care.follow-up.update', $visit) }}" id="assessment-evaluasi-form" data-autosave hidden>
            @csrf
            @method('PUT')
        </form>
    @endif
@elseif ($evaluasiPart === 'subjektif')
    @if ($canEditAssessment)
        <div class="soap-addon soap-addon--line">
            <label for="adverse_effect">Efek samping</label>
            <input class="form-control" name="adverse_effect" id="adverse_effect" form="assessment-evaluasi-form" value="{{ old('adverse_effect', $review['adverse_effect'] ?? '') }}" placeholder="Tidak ada" maxlength="2000">
            <label for="indicator-complaint">Perubahan keluhan</label>
            <select name="indicators[complaint]" id="indicator-complaint" class="form-select" form="assessment-evaluasi-form">
                <option value="">—</option>
                @foreach (AssessmentChange::options() as $value => $optionLabel)
                    <option value="{{ $value }}" @selected(($indicators['complaint'] ?? '') === $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        </div>
    @elseif (filled($review['adverse_effect'] ?? null) || $indicatorLabel('complaint'))
        <div class="text-muted small mt-1">
            @if (filled($review['adverse_effect'] ?? null))Efek samping: {{ $review['adverse_effect'] }}. @endif
            @if ($indicatorLabel('complaint'))Perubahan keluhan: {{ $indicatorLabel('complaint') }}.@endif
        </div>
    @endif
@elseif ($evaluasiPart === 'objektif')
    @if ($canEditAssessment)
        <div class="soap-addon">
            <label for="indicator-findings">Perubahan temuan</label>
            <select name="indicators[findings]" id="indicator-findings" class="form-select" form="assessment-evaluasi-form">
                <option value="">—</option>
                @foreach (AssessmentChange::options() as $value => $optionLabel)
                    <option value="{{ $value }}" @selected(($indicators['findings'] ?? '') === $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        </div>
    @elseif ($indicatorLabel('findings'))
        <div class="text-muted small mt-1">Perubahan temuan: {{ $indicatorLabel('findings') }}</div>
    @endif
@elseif ($evaluasiPart === 'asesmen')
    @if ($canEditAssessment)
        <div class="soap-addon">
            <label for="indicator-diagnosis">Perubahan diagnosis</label>
            <select name="indicators[diagnosis]" id="indicator-diagnosis" class="form-select" form="assessment-evaluasi-form">
                <option value="">—</option>
                @foreach (AssessmentChange::options() as $value => $optionLabel)
                    <option value="{{ $value }}" @selected(($indicators['diagnosis'] ?? '') === $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        </div>
    @elseif ($indicatorLabel('diagnosis'))
        <div class="text-muted small mt-1">Perubahan diagnosis: {{ $indicatorLabel('diagnosis') }}</div>
    @endif
@elseif ($evaluasiPart === 'rencana-ringkas')
    <div class="text-muted small mt-1" data-plan-review @unless (filled($review['plan_decision'] ?? null) || $indicatorLabel('plan')) hidden @endunless>
        @if (filled($review['plan_decision'] ?? null))Keputusan: {{ $planLabels[$review['plan_decision']] ?? $review['plan_decision'] }}. @endif
        @if (($review['plan_decision'] ?? null) === 'control' && $record?->control_date)Tanggal kontrol: {{ $record->control_date->format('d-m-Y') }}. @endif
        @if ($indicatorLabel('plan'))Dibanding kunjungan lalu: {{ $indicatorLabel('plan') }}.@endif
    </div>
@elseif ($evaluasiPart === 'rencana')
    @if ($canEditAssessment)
        <div class="care-form__review">
            <div class="care-form__title">Asesmen ulang</div>
            <p class="care-form__hint">Keputusan untuk perawatan yang sudah berjalan.</p>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label" for="plan_decision">Keputusan kunjungan ini</label>
                    <select name="plan_decision" id="plan_decision" class="form-select" form="assessment-evaluasi-form">
                        <option value="">—</option>
                        @foreach ($planLabels as $value => $label)
                            <option value="{{ $value }}" @selected(old('plan_decision', $review['plan_decision'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="indicator-plan">Kondisi dibanding kunjungan lalu</label>
                    <select name="indicators[plan]" id="indicator-plan" class="form-select" form="assessment-evaluasi-form">
                        <option value="">—</option>
                        @foreach (AssessmentChange::options() as $value => $optionLabel)
                            <option value="{{ $value }}" @selected(($indicators['plan'] ?? '') === $value)>{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6" data-control-date-wrap @unless (old('plan_decision', $review['plan_decision'] ?? '') === 'control') hidden @endunless>
                    <label class="form-label" for="follow-up-control-date">Tanggal kontrol</label>
                    <input type="date" name="control_date" id="follow-up-control-date" class="form-control" form="assessment-evaluasi-form" value="{{ old('plan_decision', $review['plan_decision'] ?? '') === 'control' ? old('control_date', $record?->control_date?->format('Y-m-d')) : '' }}">
                </div>
            </div>
        </div>
    @endif
@elseif ($evaluasiPart === 'tindakan')
    @foreach ($context['previousProcedures'] ?? collect() as $procedure)
        @continue(str_contains(mb_strtolower((string) ($procedure->procedure?->name ?? '')), 'konsultasi'))
        @php($eval = $procedureEvals[$procedure->id] ?? [])
        <div class="soap-addon">
            <span>{{ $procedure->procedure?->name ?: 'Tindakan' }}@if ($procedure->tooth_number) · gigi {{ $procedure->tooth_number }}@endif</span>
            @if ($canEditAssessment)
                <input class="form-control" name="procedure_evals[{{ $procedure->id }}][result]" form="assessment-evaluasi-form" value="{{ $eval['result'] ?? '' }}" placeholder="Evaluasi hasil tindakan" maxlength="1000">
            @elseif (filled($eval['result'] ?? null))
                <span class="text-muted small">{{ $eval['result'] }}</span>
            @endif
        </div>
    @endforeach
@endif
