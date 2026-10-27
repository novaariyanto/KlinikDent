@php
    $stored = is_array($dentalExam ?? null) ? $dentalExam : [];
    $pick = function (string $key) use ($stored) {
        $value = old($key, array_key_exists($key, $stored) ? $stored[$key] : null);

        return $value === '' ? null : $value;
    };
    $extraoral = $pick('extraoral');
    $intraoral = $pick('intraoral');
    $occlusion = $pick('occlusion');
    if ($occlusion === 'deep') {
        $occlusion = 'steep';
    }
    $gingiva = $pick('gingiva');
    $mucosa = $pick('mucosa');
    $palate = $pick('palate');
    $torusPalatinus = $pick('torus_palatinus');
    $torusMandibularis = $pick('torus_mandibularis');
    $mandibularisKnown = ! filled($torusMandibularis) || array_key_exists((string) $torusMandibularis, $examOptions['torus_mandibularis']);
    $diastema = $pick('diastema');
    $supernumerary = $pick('supernumerary');
    $anomaly = $pick('anomaly');
    $ohisStored = is_array($stored['ohis'] ?? null) ? $stored['ohis'] : [];
    $ohisDi = old('ohis.debris_index', $ohisStored['debris_index'] ?? '');
    $ohisCi = old('ohis.calculus_index', $ohisStored['calculus_index'] ?? '');
    $ohisTotal = '';
    $ohisInterpret = '';
    if (is_numeric($ohisDi) && is_numeric($ohisCi)) {
        $ohisTotal = round((float) $ohisDi + (float) $ohisCi, 2);
        $ohisInterpret = match (true) {
            $ohisTotal <= 1.2 => 'Baik',
            $ohisTotal <= 3.0 => 'Sedang',
            default => 'Buruk',
        };
    }
    $ohisLabels = ['baik' => 'Baik', 'sedang' => 'Sedang', 'buruk' => 'Buruk'];
    $canEditExam = $writable && auth()->user()?->can('examination.manage');
    $labelOf = function (mixed $value, array $options): ?string {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $options[$value] ?? $value;
    };
@endphp
<section class="care-section" id="pemeriksaan">
    <div class="care-section__head">
        <h5 class="care-section__title">Pemeriksaan</h5>
        <p class="care-section__hint">Pilih temuan secara eksplisit. Detail hanya muncul jika ada kelainan.</p>
    </div>
    <div class="care-section__body">
        @if ($canEditExam)
            <form action="{{ route('care.dental-exam.update', $visit) }}" method="POST" data-autosave>
                @csrf
                @method('PUT')
                <div class="exam-grid">
                    @include('clinical.care.partials.choice-group', ['name' => 'extraoral', 'label' => 'Pemeriksaan ekstraoral', 'options' => $examOptions['finding'], 'selected' => $extraoral, 'reveal' => 'extraoral', 'revealWhen' => 'abnormal'])
                    <div class="exam-reveal exam-field--full" data-reveal="extraoral" @unless ($extraoral === 'abnormal') hidden @endunless>
                        <label class="form-label" for="extraoral_note">Temuan ekstraoral</label>
                        <textarea name="extraoral_note" id="extraoral_note" rows="2" class="form-control">{{ old('extraoral_note', $stored['extraoral_note'] ?? '') }}</textarea>
                    </div>

                    @include('clinical.care.partials.choice-group', ['name' => 'intraoral', 'label' => 'Pemeriksaan intraoral', 'options' => $examOptions['finding'], 'selected' => $intraoral, 'reveal' => 'intraoral', 'revealWhen' => 'abnormal'])
                    <div class="exam-reveal exam-field--full" data-reveal="intraoral" @unless ($intraoral === 'abnormal') hidden @endunless>
                        <label class="form-label" for="intraoral_note">Temuan intraoral</label>
                        <textarea name="intraoral_note" id="intraoral_note" rows="2" class="form-control">{{ old('intraoral_note', $stored['intraoral_note'] ?? '') }}</textarea>
                    </div>

                    @include('clinical.care.partials.choice-group', ['name' => 'occlusion', 'label' => 'Occlusi', 'options' => $examOptions['occlusion'], 'selected' => $occlusion])
                    @include('clinical.care.partials.choice-group', ['name' => 'gingiva', 'label' => 'Gingiva', 'options' => $examOptions['gingiva'], 'selected' => $gingiva])
                    @include('clinical.care.partials.choice-group', ['name' => 'mucosa', 'label' => 'Mukosa', 'options' => $examOptions['mucosa'], 'selected' => $mucosa])
                    @include('clinical.care.partials.choice-group', ['name' => 'palate', 'label' => 'Palatum', 'options' => $examOptions['palate'], 'selected' => $palate])
                    @include('clinical.care.partials.choice-group', ['name' => 'torus_palatinus', 'label' => 'Torus Palatinus', 'options' => $examOptions['torus'], 'selected' => $torusPalatinus])
                    @include('clinical.care.partials.choice-group', [
                        'name' => 'torus_mandibularis',
                        'label' => 'Torus Mandibularis',
                        'options' => $examOptions['torus_mandibularis'],
                        'selected' => $mandibularisKnown ? $torusMandibularis : null,
                        'hint' => $mandibularisKnown ? null : 'Nilai lama berupa ukuran. Pilih Tidak ada, Sisi Kiri, Sisi Kanan, atau Kedua Sisi.',
                    ])

                    @include('clinical.care.partials.choice-group', ['name' => 'diastema', 'label' => 'Diastema', 'options' => $examOptions['presence'], 'selected' => $diastema, 'reveal' => 'diastema'])
                    <div class="exam-reveal" data-reveal="diastema" @unless ($diastema === 'present') hidden @endunless>
                        <x-input name="diastema_elements" label="Elemen" :value="$stored['diastema_elements'] ?? null" />
                    </div>

                    @include('clinical.care.partials.choice-group', ['name' => 'supernumerary', 'label' => 'Supernumerary teeth', 'options' => $examOptions['presence'], 'selected' => $supernumerary, 'reveal' => 'supernumerary'])
                    <div class="exam-reveal" data-reveal="supernumerary" @unless ($supernumerary === 'present') hidden @endunless>
                        <x-input name="supernumerary_teeth" label="Gigi" :value="$stored['supernumerary_teeth'] ?? null" />
                    </div>

                    @include('clinical.care.partials.choice-group', ['name' => 'anomaly', 'label' => 'Anomali gigi', 'options' => $examOptions['presence'], 'selected' => $anomaly, 'reveal' => 'anomaly'])
                    <div class="exam-reveal" data-reveal="anomaly" @unless ($anomaly === 'present') hidden @endunless>
                        <x-input name="anomaly_description" label="Deskripsi" :value="$stored['anomaly_description'] ?? null" />
                    </div>

                    <div class="exam-field exam-field--full">
                        <label class="form-label" for="habits">Kebiasaan buruk / temuan lainnya</label>
                        <textarea name="habits" id="habits" rows="2" class="form-control">{{ old('habits', $stored['habits'] ?? '') }}</textarea>
                    </div>

                    <div class="exam-field exam-field--full border rounded p-3 mt-2">
                        <div class="fw-semibold mb-1">OHIS (Oral Hygiene Index Simplified)</div>
                        <p class="text-muted small mb-2">Isi Debris Index dan Calculus Index (0–3). Total dan interpretasi di kolom sebelah kanan terisi otomatis: Baik 0–1,2 · Sedang 1,3–3,0 · Buruk 3,1–6,0.</p>
                        <div class="ohis-grid">
                            <div>
                                <label class="form-label" for="ohis_di">Debris Index</label>
                                <input type="number" step="0.01" min="0" max="3" class="form-control" name="ohis[debris_index]" id="ohis_di" value="{{ $ohisDi }}" inputmode="decimal">
                            </div>
                            <div>
                                <label class="form-label" for="ohis_ci">Calculus Index</label>
                                <input type="number" step="0.01" min="0" max="3" class="form-control" name="ohis[calculus_index]" id="ohis_ci" value="{{ $ohisCi }}" inputmode="decimal">
                            </div>
                            <div class="ohis-result">
                                <div class="form-label mb-1">Total OHIS</div>
                                <div class="ohis-result__value" id="ohis_total">{{ $ohisTotal === '' ? '—' : $ohisTotal }}</div>
                            </div>
                            <div class="ohis-result">
                                <div class="form-label mb-1">Interpretasi</div>
                                <div class="ohis-result__value {{ $ohisInterpret !== '' ? 'is-'.strtolower($ohisInterpret) : '' }}" id="ohis_interpret">{{ $ohisInterpret !== '' ? $ohisInterpret : '—' }}</div>
                            </div>
                        </div>
                        <p class="text-danger small mb-0 mt-2" data-ohis-warning hidden></p>
                    </div>
                </div>
            </form>
        @else
            @php
                $occlusionStored = ($stored['occlusion'] ?? null) === 'deep' ? 'steep' : ($stored['occlusion'] ?? null);
                $mandStored = $stored['torus_mandibularis'] ?? null;
                $mandLabel = $examOptions['torus_mandibularis'][$mandStored] ?? (filled($mandStored) ? $mandStored.' (nilai lama)' : null);
                $ohisView = is_array($stored['ohis'] ?? null) ? $stored['ohis'] : [];
                $rows = [
                    'Ekstraoral' => trim(($labelOf($stored['extraoral'] ?? null, $examOptions['finding']) ?: 'Belum dicatat').(filled($stored['extraoral_note'] ?? null) ? ' — '.$stored['extraoral_note'] : '')),
                    'Intraoral' => trim(($labelOf($stored['intraoral'] ?? null, $examOptions['finding']) ?: 'Belum dicatat').(filled($stored['intraoral_note'] ?? null) ? ' — '.$stored['intraoral_note'] : '')),
                    'Occlusi' => $labelOf($occlusionStored, $examOptions['occlusion']) ?: 'Belum dicatat',
                    'Gingiva' => $labelOf($stored['gingiva'] ?? null, $examOptions['gingiva']) ?: 'Belum dicatat',
                    'Mukosa' => $labelOf($stored['mucosa'] ?? null, $examOptions['mucosa']) ?: 'Belum dicatat',
                    'Palatum' => $labelOf($stored['palate'] ?? null, $examOptions['palate']) ?: 'Belum dicatat',
                    'Torus palatinus' => $labelOf($stored['torus_palatinus'] ?? null, $examOptions['torus']) ?: 'Belum dicatat',
                    'Torus mandibularis' => $mandLabel ?: 'Belum dicatat',
                    'Diastema' => trim(($labelOf($stored['diastema'] ?? null, $examOptions['presence']) ?: 'Belum dicatat').(filled($stored['diastema_elements'] ?? null) ? ' — '.$stored['diastema_elements'] : '')),
                    'Supernumerary' => trim(($labelOf($stored['supernumerary'] ?? null, $examOptions['presence']) ?: 'Belum dicatat').(filled($stored['supernumerary_teeth'] ?? null) ? ' — '.$stored['supernumerary_teeth'] : '')),
                    'Anomali gigi' => trim(($labelOf($stored['anomaly'] ?? null, $examOptions['presence']) ?: 'Belum dicatat').(filled($stored['anomaly_description'] ?? null) ? ' — '.$stored['anomaly_description'] : '')),
                ];
            @endphp
            <div class="exam-readonly">
                @foreach ($rows as $label => $value)
                    <div>
                        <span>{{ $label }}</span>
                        {{ $value }}
                    </div>
                @endforeach
                <div class="exam-readonly__wide">
                    <span>Kebiasaan / temuan lain</span>
                    {{ filled($stored['habits'] ?? null) ? $stored['habits'] : 'Belum dicatat' }}
                </div>
                <div class="exam-readonly__wide">
                    <span>OHIS</span>
                    @if (is_numeric($ohisView['debris_index'] ?? null) && is_numeric($ohisView['calculus_index'] ?? null))
                        DI {{ $ohisView['debris_index'] }} · CI {{ $ohisView['calculus_index'] }} · total {{ $ohisView['total'] ?? round((float) $ohisView['debris_index'] + (float) $ohisView['calculus_index'], 2) }}
                        @if (filled($ohisView['interpretation'] ?? null))
                            · {{ $ohisLabels[$ohisView['interpretation']] ?? $ohisView['interpretation'] }}
                        @endif
                    @else
                        Belum dicatat
                    @endif
                </div>
            </div>
        @endif
    </div>
</section>

@push('scripts')
<script>
(function () {
    var di = document.getElementById('ohis_di');
    var ci = document.getElementById('ohis_ci');
    var total = document.getElementById('ohis_total');
    var interpret = document.getElementById('ohis_interpret');
    var warning = document.querySelector('[data-ohis-warning]');
    if (!di || !ci || !total || !interpret) return;

    function score(input) {
        if (typeof input.valueAsNumber === 'number' && !Number.isNaN(input.valueAsNumber)) {
            return input.valueAsNumber;
        }
        var raw = String(input.value || '').trim().replace(',', '.');
        if (raw === '' || raw === '.' || raw === '-') return null;
        var number = Number(raw);
        return Number.isNaN(number) ? null : number;
    }

    function syncOhis() {
        var debris = score(di);
        var calculus = score(ci);
        var debrisOk = debris !== null;
        var calculusOk = calculus !== null;
        if (warning) {
            var partial = debrisOk !== calculusOk;
            warning.hidden = !partial;
            warning.textContent = partial ? 'Debris Index dan Calculus Index harus diisi bersamaan.' : '';
        }
        interpret.classList.remove('is-baik', 'is-sedang', 'is-buruk');
        if (!debrisOk || !calculusOk) {
            total.textContent = '—';
            interpret.textContent = '—';
            return;
        }
        var sum = Math.round((debris + calculus) * 100) / 100;
        var label = sum <= 1.2 ? 'Baik' : (sum <= 3 ? 'Sedang' : 'Buruk');
        total.textContent = String(sum);
        interpret.textContent = label;
        interpret.classList.add('is-' + label.toLowerCase());
    }

    di.addEventListener('input', syncOhis);
    ci.addEventListener('input', syncOhis);
    di.addEventListener('change', syncOhis);
    ci.addEventListener('change', syncOhis);
    syncOhis();
})();
</script>
@endpush
