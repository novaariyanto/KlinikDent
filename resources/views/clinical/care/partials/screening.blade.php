@php
    $stored = is_array($systemicHistory ?? null) ? $systemicHistory : [];
    $postedSystemic = old('allergy_status') !== null;
    $sys = $postedSystemic ? array_merge($stored, old()) : $stored;
    $conditions = $postedSystemic ? (array) old('conditions', []) : (array) ($stored['conditions'] ?? []);
    $conditions = array_values(array_filter($conditions, fn ($value) => is_string($value) && $value !== ''));
    $none = filter_var($postedSystemic ? old('none', false) : ($stored['none'] ?? false), FILTER_VALIDATE_BOOLEAN);
    $vitalSigns = is_array($record?->vital_signs) ? $record->vital_signs : [];
    $split = array_pad(explode('/', (string) ($vitalSigns['blood_pressure'] ?? ''), 2), 2, '');
    $bpSys = (string) ($vitalSigns['blood_pressure_sys'] ?? ($split[0] !== '' ? $split[0] : ($stored['blood_pressure_sys'] ?? '')));
    $bpDia = (string) ($vitalSigns['blood_pressure_dia'] ?? ($split[1] !== '' ? $split[1] : ($stored['blood_pressure_dia'] ?? '')));
    if (is_string(old('blood_pressure_sys'))) {
        $bpSys = old('blood_pressure_sys');
    }
    if (is_string(old('blood_pressure_dia'))) {
        $bpDia = old('blood_pressure_dia');
    }
    $canEditSystemic = $writable && (auth()->user()?->can('anamnesis.manage') || auth()->user()?->can('medical_record.update'));
    $canEditVitals = $writable && auth()->user()?->can('vital_sign.manage');
    $allergyStatus = (string) ($sys['allergy_status'] ?? '');
    $medicationStatus = (string) ($sys['medication_status'] ?? '');
    $embedded = $embedded ?? false;
    $screeningPart = $screeningPart ?? 'all';
    $showSystemic = in_array($screeningPart, ['all', 'systemic'], true);
    $showVitals = in_array($screeningPart, ['all', 'vitals'], true);
@endphp
<section class="care-section{{ $embedded ? ' care-section--embedded' : '' }}" id="{{ $showSystemic ? 'screening' : 'soap-vitals' }}">
    @unless ($embedded)
        <div class="care-section__head">
            <h5 class="care-section__title">Screening cepat</h5>
            <p class="care-section__hint">Kondisi sistemik dan tanda vital. Rincian alergi, obat, dan tekanan darah muncul sesuai pilihan.</p>
        </div>
    @endunless
    <div class="care-section__body">
        @if ($showSystemic)
        @if ($canEditSystemic)
            <form action="{{ route('care.systemic.update', $visit) }}" method="POST" data-autosave data-allergy-form class="care-form">
                @csrf
                @method('PUT')
                <p class="text-danger small mb-2" data-sys-warning hidden></p>
                <div class="care-form__block">
                    <div class="care-form__title">Penyakit sistemik</div>
                    <input type="hidden" name="none" value="0">
                    <label class="exam-choice exam-choice--check mb-2">
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
                    <div class="exam-reveal mt-2" data-sys-panel="hypertension" @unless (in_array('hypertension', $conditions, true)) hidden @endunless>
                        <label class="form-label">Tekanan darah</label>
                        <div class="vital-inline">
                            <input class="form-control" name="blood_pressure_sys" value="{{ $bpSys }}" inputmode="numeric" placeholder="120" aria-label="Sistole">
                            <span>/</span>
                            <input class="form-control" name="blood_pressure_dia" value="{{ $bpDia }}" inputmode="numeric" placeholder="80" aria-label="Diastole">
                            <span class="text-muted">mmHg</span>
                        </div>
                    </div>
                    <div class="exam-reveal mt-2" data-sys-panel="other" @unless (in_array('other', $conditions, true)) hidden @endunless>
                        <x-input name="other_detail" label="Keterangan kondisi lain" :value="$sys['other_detail'] ?? null" />
                    </div>
                </div>
                <div class="care-form__block">
                    <div class="care-form__title">Alergi</div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="allergy_status">Status</label>
                            <select name="allergy_status" id="allergy_status" class="form-select">
                                @foreach ($examOptions['allergy_statuses'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($sys['allergy_status'] ?? 'not_asked') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p class="care-form__hint">Belum ditanyakan tidak diartikan sebagai tidak ada alergi.</p>
                    <div data-sys-panel="allergy" @unless ($allergyStatus === 'present' || in_array('allergy', $conditions, true)) hidden @endunless>
                        @php
                            $allergyCategory = $sys['allergy_category'] ?? '';
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
                            <div class="col-md-6">
                                <x-input name="allergy_detail" label="Zat atau bahan pemicu" :value="$sys['allergy_detail'] ?? null" />
                            </div>
                            <div class="col-md-6">
                                <x-input name="allergy_reaction" label="Reaksi atau manifestasi" :value="$sys['allergy_reaction'] ?? null" />
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="allergy_note">Catatan klinis alergi</label>
                                <textarea name="allergy_note" id="allergy_note" rows="2" class="form-control">{{ $sys['allergy_note'] ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="care-form__block" data-sys-panel="medication" @unless (in_array('medication', $conditions, true) || ($medicationStatus !== '' && $medicationStatus !== 'not_asked')) hidden @endunless>
                    <div class="care-form__title">Obat rutin</div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="medication_status">Status</label>
                            <select name="medication_status" id="medication_status" class="form-select">
                                <option value="not_asked" @selected($medicationStatus === '' || $medicationStatus === 'not_asked')>Belum ditanyakan</option>
                                <option value="none_confirmed" @selected($medicationStatus === 'none_confirmed')>Tidak memakai obat rutin</option>
                                <option value="present" @selected($medicationStatus === 'present')>Sedang memakai obat</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <x-input name="medication_detail" label="Nama obat" :value="$sys['medication_detail'] ?? null" />
                        </div>
                    </div>
                </div>
                <div class="care-form__block">
                    <div class="care-form__title">Darah dan kehamilan</div>
                    <div class="row g-2">
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
                </div>
                <div class="care-form__block">
                    <div class="care-form__title">Riwayat lain</div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="family_history">Riwayat penyakit keluarga</label>
                            <textarea name="family_history" id="family_history" rows="2" class="form-control">{{ old('family_history', $sys['family_history'] ?? '') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="dental_history">Riwayat perawatan gigi sebelumnya</label>
                            <textarea name="dental_history" id="dental_history" rows="2" class="form-control">{{ old('dental_history', $sys['dental_history'] ?? '') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="drug_allergy">Alergi obat</label>
                            <textarea name="drug_allergy" id="drug_allergy" rows="2" class="form-control">{{ old('drug_allergy', $sys['drug_allergy'] ?? '') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="material_allergy">Alergi bahan</label>
                            <textarea name="material_allergy" id="material_allergy" rows="2" class="form-control">{{ old('material_allergy', $sys['material_allergy'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </form>
        @else
            <div class="mb-4">
                @if ($none)
                    <p class="mb-1">Tidak ada riwayat penyakit sistemik.</p>
                @elseif ($conditions !== [])
                    <p class="mb-1">Riwayat: {{ collect($conditions)->map(fn ($key) => $examOptions['systemic'][$key] ?? $key)->implode(', ') }}</p>
                @else
                    <p class="mb-1 text-muted">Riwayat penyakit sistemik belum dicatat.</p>
                @endif
                @if ($allergyStatus !== '' && $allergyStatus !== 'not_asked')
                    <p class="mb-1">Alergi: {{ $examOptions['allergy_statuses'][$allergyStatus] ?? $allergyStatus }}@if (filled($sys['allergy_detail'] ?? null)) — {{ $sys['allergy_detail'] }}@endif</p>
                @endif
                @if ($medicationStatus === 'present' || filled($sys['medication_detail'] ?? null))
                    <p class="mb-1">Obat rutin: {{ filled($sys['medication_detail'] ?? null) ? $sys['medication_detail'] : 'sedang memakai obat' }}</p>
                @elseif ($medicationStatus === 'none_confirmed')
                    <p class="mb-1">Tidak memakai obat rutin.</p>
                @endif
                @if (filled($sys['blood_type'] ?? null) || filled($sys['rhesus'] ?? null) || filled($sys['pregnancy_status'] ?? null) || filled($sys['other_detail'] ?? null))
                    <p class="mb-0 text-muted">
                        Golongan darah {{ $sys['blood_type'] ?: '-' }}{{ ($sys['rhesus'] ?? '') === 'positive' ? '+' : (($sys['rhesus'] ?? '') === 'negative' ? '-' : '') }}
                        @if (($sys['pregnancy_status'] ?? '') === 'pregnant')
                            · Hamil
                        @elseif (($sys['pregnancy_status'] ?? '') === 'not_pregnant')
                            · Tidak hamil
                        @endif
                        @if (filled($sys['other_detail'] ?? null))
                            · {{ $sys['other_detail'] }}
                        @endif
                    </p>
                @endif
            </div>
        @endif
        @endif

        @if ($showVitals)
        @if ($canEditVitals)
            <form action="{{ route('care.vitals.update', $visit) }}" method="POST" data-autosave>
                @csrf
                @method('PUT')
                @if ($embedded)
                    <div class="soap-vital-grid">
                        <label>Sistole <span>mmHg</span><input class="form-control" name="blood_pressure_sys" value="{{ $bpSys }}" inputmode="numeric" placeholder="120"></label>
                        <label>Diastole <span>mmHg</span><input class="form-control" name="blood_pressure_dia" value="{{ $bpDia }}" inputmode="numeric" placeholder="80"></label>
                        <label>Nadi <span>x/mnt</span><input class="form-control" name="pulse" value="{{ $vitalSigns['pulse'] ?? '' }}" inputmode="numeric" placeholder="84"></label>
                        <label>Napas <span>x/mnt</span><input class="form-control" name="respiration" value="{{ $vitalSigns['respiration'] ?? '' }}" inputmode="numeric" placeholder="20"></label>
                        <label>Suhu <span>°C</span><input class="form-control" name="temperature" value="{{ $vitalSigns['temperature'] ?? '' }}" inputmode="decimal" placeholder="36.5"></label>
                        <label>Berat <span>kg</span><input class="form-control" name="weight" value="{{ $vitalSigns['weight'] ?? '' }}" inputmode="decimal" placeholder="60"></label>
                        <label>Tinggi <span>cm</span><input class="form-control" name="height" value="{{ $vitalSigns['height'] ?? '' }}" inputmode="decimal" placeholder="160"></label>
                        <label class="soap-vital-grid__wide">Keadaan umum<input class="form-control" name="general_condition" value="{{ $vitalSigns['general_condition'] ?? '' }}" placeholder="Baik"></label>
                        <label class="soap-vital-grid__wide">Kesadaran<input class="form-control" name="consciousness" value="{{ $vitalSigns['consciousness'] ?? '' }}" placeholder="Compos mentis"></label>
                    </div>
                @else
                <div class="vital-grid">
                    <div>
                        <label class="form-label">TD</label>
                        <div class="vital-inline">
                            <input class="form-control" name="blood_pressure_sys" value="{{ $bpSys }}" inputmode="numeric" placeholder="120">
                            <span>/</span>
                            <input class="form-control" name="blood_pressure_dia" value="{{ $bpDia }}" inputmode="numeric" placeholder="80">
                            <span class="text-muted">mmHg</span>
                        </div>
                    </div>
                    <x-input name="pulse" label="Nadi" :value="$vitalSigns['pulse'] ?? null" placeholder="80" />
                    <x-input name="temperature" label="Suhu" :value="$vitalSigns['temperature'] ?? null" placeholder="36.5" />
                    <x-input name="respiration" label="Respirasi" :value="$vitalSigns['respiration'] ?? null" placeholder="18" />
                    <x-input name="weight" label="Berat (kg)" :value="$vitalSigns['weight'] ?? null" />
                    <x-input name="height" label="Tinggi (cm)" :value="$vitalSigns['height'] ?? null" />
                    <x-input name="general_condition" label="Keadaan umum" :value="$vitalSigns['general_condition'] ?? null" />
                    <x-input name="consciousness" label="Kesadaran" :value="$vitalSigns['consciousness'] ?? null" />
                </div>
                @endif
            </form>
        @else
            @php
                $bpText = trim($bpSys.'/'.$bpDia, '/');
                $vitalBits = array_filter([
                    $bpText !== '' ? 'TD '.$bpText.' mmHg' : null,
                    filled($vitalSigns['pulse'] ?? null) ? 'Nadi '.$vitalSigns['pulse'] : null,
                    filled($vitalSigns['temperature'] ?? null) ? 'Suhu '.$vitalSigns['temperature'] : null,
                    filled($vitalSigns['respiration'] ?? null) ? 'Respirasi '.$vitalSigns['respiration'] : null,
                    filled($vitalSigns['weight'] ?? null) ? 'Berat '.$vitalSigns['weight'].' kg' : null,
                    filled($vitalSigns['height'] ?? null) ? 'Tinggi '.$vitalSigns['height'].' cm' : null,
                ]);
            @endphp
            <p class="mb-0 {{ $vitalBits === [] ? 'text-muted' : '' }}">{{ $vitalBits === [] ? 'Tanda vital belum dicatat.' : implode(' · ', $vitalBits) }}</p>
        @endif
        @endif
    </div>
</section>

@once
@push('scripts')
<script>
(function () {
    var workspace = document.querySelector('.care-workspace') || document;
    workspace.querySelectorAll('[name="blood_pressure_sys"], [name="blood_pressure_dia"]').forEach(function (input) {
        input.addEventListener('input', function () {
            workspace.querySelectorAll('[name="' + input.name + '"]').forEach(function (other) {
                if (other !== input) other.value = input.value;
            });
        });
    });

    var root = document.getElementById('screening');
    if (!root) return;
    var noneBox = root.querySelector('[data-sys-none]');
    var conditionBoxes = root.querySelectorAll('[data-sys-conditions] input[type="checkbox"]');
    var allergyStatus = root.querySelector('#allergy_status');
    var medStatus = root.querySelector('[name="medication_status"]');
    var warning = root.querySelector('[data-sys-warning]');

    function boxFor(key) {
        return root.querySelector('[data-sys-toggle="' + key + '"] input');
    }

    function warn(text) {
        if (!warning) return;
        warning.hidden = !text;
        warning.textContent = text || '';
    }

    function syncSys() {
        if ((allergyStatus && allergyStatus.value === 'present') || (medStatus && medStatus.value === 'present')) {
            if (noneBox) noneBox.checked = false;
        }
        ['hypertension', 'other'].forEach(function (key) {
            var box = boxFor(key);
            var panel = root.querySelector('[data-sys-panel="' + key + '"]');
            if (panel) panel.hidden = !(box && box.checked);
        });
        var allergyPanel = root.querySelector('[data-sys-panel="allergy"]');
        var allergyBox = boxFor('allergy');
        if (allergyBox && allergyStatus && allergyStatus.value === 'present') allergyBox.checked = true;
        var allergyOn = (allergyBox && allergyBox.checked) || (allergyStatus && allergyStatus.value === 'present');
        if (allergyPanel) allergyPanel.hidden = !allergyOn;
        var medPanel = root.querySelector('[data-sys-panel="medication"]');
        var medBox = boxFor('medication');
        if (medBox && medStatus && medStatus.value === 'present') medBox.checked = true;
        var medOn = (medBox && medBox.checked) || (medStatus && medStatus.value && medStatus.value !== 'not_asked');
        if (medPanel) medPanel.hidden = !medOn;
    }

    if (noneBox) {
        noneBox.addEventListener('change', function () {
            if (!noneBox.checked) {
                warn('');
                syncSys();
                return;
            }
            var blocked = (allergyStatus && allergyStatus.value === 'present') || (medStatus && medStatus.value === 'present');
            if (blocked) {
                noneBox.checked = false;
                warn('Masih ada alergi atau obat rutin. Ubah statusnya sebelum menandai tidak ada riwayat.');
                syncSys();
                return;
            }
            conditionBoxes.forEach(function (box) { box.checked = false; });
            var other = root.querySelector('[name="other_detail"]');
            if (other) other.value = '';
            warn('');
            syncSys();
        });
    }

    conditionBoxes.forEach(function (box) {
        box.addEventListener('change', function () {
            var toggle = box.closest('[data-sys-toggle]');
            var key = toggle ? toggle.getAttribute('data-sys-toggle') : '';
            if (box.checked && noneBox) noneBox.checked = false;
            if (key === 'allergy' && allergyStatus) {
                allergyStatus.value = box.checked ? 'present' : (allergyStatus.value === 'present' ? 'not_asked' : allergyStatus.value);
            }
            if (key === 'medication' && medStatus) {
                medStatus.value = box.checked ? 'present' : (medStatus.value === 'present' ? 'not_asked' : medStatus.value);
            }
            if (box.checked) warn('');
            syncSys();
        });
    });

    if (allergyStatus) {
        allergyStatus.addEventListener('change', function () {
            var allergyBox = boxFor('allergy');
            if (allergyBox) allergyBox.checked = allergyStatus.value === 'present';
            if (allergyStatus.value === 'present' && noneBox) noneBox.checked = false;
            warn('');
            syncSys();
        });
    }

    if (medStatus) {
        medStatus.addEventListener('change', function () {
            var medBox = boxFor('medication');
            if (medBox && medStatus.value === 'present') medBox.checked = true;
            if (medBox && medStatus.value !== 'present') medBox.checked = false;
            if (medStatus.value === 'present' && noneBox) noneBox.checked = false;
            warn('');
            syncSys();
        });
    }

    syncSys();
})();
</script>
@endpush
@endonce
