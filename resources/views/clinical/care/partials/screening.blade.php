@php
    $sys = old() + $systemicHistory;
    $conditions = old('conditions', $sys['conditions'] ?? []);
    $none = old('none', $sys['none'] ?? (empty($conditions) && empty($sys['allergy_detail']) && empty($sys['medication_detail'])));
    $vitals = old() ?: ($record?->vital_signs ?? []);
    $bp = explode('/', (string) ($vitals['blood_pressure'] ?? ''));
@endphp
<section class="care-section" id="screening">
    <div class="care-section__head">
        <h5 class="care-section__title">Screening cepat</h5>
        <p class="care-section__hint">Kondisi sistemik dan tanda vital. Detail hanya muncul jika dipilih.</p>
    </div>
    <div class="care-section__body">
        @if ($writable && (auth()->user()?->can('anamnesis.manage') || auth()->user()?->can('medical_record.update')))
            <form action="{{ route('care.systemic.update', $visit) }}" method="POST" data-autosave class="mb-4">
                @csrf
                @method('PUT')
                <label class="exam-choice exam-choice--check mb-3">
                    <input type="checkbox" name="none" value="1" data-sys-none @checked($none)>
                    <span>Tidak ada riwayat penyakit sistemik</span>
                </label>
                <div class="sys-grid" data-sys-conditions>
                    @foreach ($examOptions['systemic'] as $value => $label)
                        <label class="exam-choice exam-choice--check" data-sys-toggle="{{ $value }}">
                            <input type="checkbox" name="conditions[]" value="{{ $value }}" @checked(in_array($value, $conditions, true))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="exam-reveal mt-3" data-sys-panel="hypertension" hidden>
                    <div class="vital-inline">
                        <span>Tekanan darah</span>
                        <input class="form-control" name="blood_pressure_sys" value="{{ $sys['blood_pressure_sys'] ?? '' }}" inputmode="numeric" placeholder="120">
                        <span>/</span>
                        <input class="form-control" name="blood_pressure_dia" value="{{ $sys['blood_pressure_dia'] ?? '' }}" inputmode="numeric" placeholder="80">
                        <span class="text-muted">mmHg</span>
                    </div>
                </div>
                <div class="exam-reveal mt-2" data-sys-panel="allergy" hidden>
                    <x-input name="allergy_detail" label="Alergi terhadap" :value="$sys['allergy_detail'] ?? null" />
                </div>
                <div class="exam-reveal mt-2" data-sys-panel="medication" hidden>
                    <x-input name="medication_detail" label="Obat rutin" :value="$sys['medication_detail'] ?? null" />
                </div>
                <div class="exam-reveal mt-2" data-sys-panel="other" hidden>
                    <x-input name="other_detail" label="Keterangan" :value="$sys['other_detail'] ?? null" />
                </div>
            </form>
        @endif

        @can('vital_sign.manage')
            @if ($writable)
                <form action="{{ route('care.vitals.update', $visit) }}" method="POST" data-autosave>
                    @csrf
                    @method('PUT')
                    <div class="vital-grid">
                        <div>
                            <label class="form-label">TD</label>
                            <div class="vital-inline">
                                <input class="form-control" name="blood_pressure_sys" value="{{ $bp[0] ?? '' }}" inputmode="numeric" placeholder="120">
                                <span>/</span>
                                <input class="form-control" name="blood_pressure_dia" value="{{ $bp[1] ?? '' }}" inputmode="numeric" placeholder="80">
                                <span class="text-muted">mmHg</span>
                            </div>
                        </div>
                        <x-input name="pulse" label="Nadi" :value="$vitals['pulse'] ?? null" placeholder="80" />
                        <x-input name="temperature" label="Suhu" :value="$vitals['temperature'] ?? null" placeholder="36.5" />
                        <x-input name="respiration" label="Respirasi" :value="$vitals['respiration'] ?? null" placeholder="18" />
                        <x-input name="weight" label="Berat (kg)" :value="$vitals['weight'] ?? null" />
                        <x-input name="height" label="Tinggi (cm)" :value="$vitals['height'] ?? null" />
                    </div>
                </form>
            @endif
        @elseif ($record?->vital_signs)
            <p class="mb-0">TD {{ $record->vital_signs['blood_pressure'] ?? '-' }} · N {{ $record->vital_signs['pulse'] ?? '-' }} · S {{ $record->vital_signs['temperature'] ?? '-' }}</p>
        @endcan
    </div>
</section>

@push('scripts')
<script>
(function () {
    var noneBox = document.querySelector('[data-sys-none]');
    var conditionBoxes = document.querySelectorAll('[data-sys-conditions] input[type="checkbox"]');
    function syncSys() {
        ['hypertension', 'allergy', 'medication', 'other'].forEach(function (key) {
            var box = document.querySelector('[data-sys-toggle="' + key + '"] input');
            var panel = document.querySelector('[data-sys-panel="' + key + '"]');
            if (panel) panel.hidden = !(box && box.checked);
        });
    }
    if (noneBox) {
        noneBox.addEventListener('change', function () {
            if (noneBox.checked) {
                conditionBoxes.forEach(function (box) { box.checked = false; });
            }
            syncSys();
        });
    }
    conditionBoxes.forEach(function (box) {
        box.addEventListener('change', function () {
            if (box.checked && noneBox) noneBox.checked = false;
            syncSys();
        });
    });
    syncSys();
})();
</script>
@endpush
