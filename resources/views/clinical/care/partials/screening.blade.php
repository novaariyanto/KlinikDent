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
                <div class="mt-3">
                    <label class="form-label" for="allergy_status">Riwayat alergi</label>
                    <select name="allergy_status" id="allergy_status" class="form-select mb-2">
                        @foreach ($examOptions['allergy_statuses'] as $value => $label)
                            <option value="{{ $value }}" @selected(($sys['allergy_status'] ?? 'not_asked') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-muted small">Kosong atau belum ditanyakan tidak diartikan sebagai tidak ada alergi.</p>
                    @php
                        $allergyCategory = $sys['allergy_category'] ?? ((($sys['allergy_status'] ?? '') === 'present') ? 'medication' : '');
                    @endphp
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="allergy_category">Kategori</label>
                            <select name="allergy_category" id="allergy_category" class="form-select">
                                <option value="">—</option>
                                @foreach ($examOptions['allergy_categories'] as $value => $label)
                                    <option value="{{ $value }}" @selected($allergyCategory === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="allergy_type">Jenis</label>
                            <select name="allergy_type" id="allergy_type" class="form-select">
                                <option value="">—</option>
                                @foreach ($examOptions['allergy_types'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($sys['allergy_type'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="allergy_clinical_status">Status klinis</label>
                            <select name="allergy_clinical_status" id="allergy_clinical_status" class="form-select">
                                <option value="">—</option>
                                @foreach ($examOptions['allergy_clinical_statuses'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($sys['allergy_clinical_status'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="allergy_severity">Tingkat keparahan reaksi</label>
                            <select name="allergy_severity" id="allergy_severity" class="form-select">
                                <option value="">—</option>
                                @foreach ($examOptions['allergy_severities'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($sys['allergy_severity'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <x-input name="allergy_detail" label="Zat atau bahan pemicu" :value="$sys['allergy_detail'] ?? null" />
                    <x-input name="allergy_reaction" label="Reaksi atau manifestasi" :value="$sys['allergy_reaction'] ?? null" />
                    <label class="form-label" for="allergy_note">Catatan klinis alergi</label>
                    <textarea name="allergy_note" id="allergy_note" rows="2" class="form-control">{{ $sys['allergy_note'] ?? '' }}</textarea>
                </div>
                <div class="exam-reveal mt-2" data-sys-panel="medication" hidden>
                    <label class="form-label">Status obat rutin (SATUSEHAT)</label>
                    <select name="medication_status" class="form-select mb-2">
                        <option value="not_asked" @selected(($sys['medication_status'] ?? 'not_asked') === 'not_asked')>Belum ditanyakan</option>
                        <option value="none_confirmed" @selected(($sys['medication_status'] ?? '') === 'none_confirmed')>Tidak memakai obat rutin</option>
                        <option value="present" @selected(($sys['medication_status'] ?? '') === 'present')>Sedang memakai obat</option>
                    </select>
                    <x-input name="medication_detail" label="Obat rutin" :value="$sys['medication_detail'] ?? null" />
                </div>
                <div class="row g-2 mt-2">
                    <div class="col-md-4">
                        <label class="form-label" for="blood_type">Golongan darah</label>
                        <select name="blood_type" id="blood_type" class="form-select">
                            <option value="">—</option>
                            @foreach (['A','B','AB','O'] as $abo)
                                <option value="{{ $abo }}" @selected(($sys['blood_type'] ?? '') === $abo)>{{ $abo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="rhesus">Rhesus</label>
                        <select name="rhesus" id="rhesus" class="form-select">
                            <option value="">—</option>
                            <option value="positive" @selected(($sys['rhesus'] ?? '') === 'positive')>Positif</option>
                            <option value="negative" @selected(($sys['rhesus'] ?? '') === 'negative')>Negatif</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="pregnancy_status">Status kehamilan</label>
                        <select name="pregnancy_status" id="pregnancy_status" class="form-select">
                            <option value="">—</option>
                            <option value="pregnant" @selected(($sys['pregnancy_status'] ?? '') === 'pregnant')>Hamil</option>
                            <option value="not_pregnant" @selected(($sys['pregnancy_status'] ?? '') === 'not_pregnant')>Tidak hamil</option>
                        </select>
                    </div>
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
        ['hypertension', 'medication', 'other'].forEach(function (key) {
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
