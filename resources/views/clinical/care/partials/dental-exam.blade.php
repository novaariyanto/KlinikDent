@php($exam = old() + $dentalExam)
<section class="care-section" id="pemeriksaan">
    <div class="care-section__head">
        <h5 class="care-section__title">Pemeriksaan</h5>
        <p class="care-section__hint">Normal = satu klik. Detail hanya muncul jika ada kelainan.</p>
    </div>
    <div class="care-section__body">
        @can('examination.manage')
            @if ($writable)
                <form action="{{ route('care.dental-exam.update', $visit) }}" method="POST" data-autosave>
                    @csrf
                    @method('PUT')
                    <div class="exam-grid">
                        @include('clinical.care.partials.choice-group', ['name' => 'extraoral', 'label' => 'Pemeriksaan ekstraoral', 'options' => $examOptions['finding'], 'selected' => $exam['extraoral'] ?? 'normal', 'reveal' => 'extraoral', 'revealWhen' => 'abnormal'])
                        <div class="exam-reveal exam-field--full" data-reveal="extraoral" hidden>
                            <label class="form-label" for="extraoral_note">Temuan ekstraoral</label>
                            <textarea name="extraoral_note" id="extraoral_note" rows="2" class="form-control">{{ $exam['extraoral_note'] ?? '' }}</textarea>
                        </div>

                        @include('clinical.care.partials.choice-group', ['name' => 'intraoral', 'label' => 'Pemeriksaan intraoral', 'options' => $examOptions['finding'], 'selected' => $exam['intraoral'] ?? 'normal', 'reveal' => 'intraoral', 'revealWhen' => 'abnormal'])
                        <div class="exam-reveal exam-field--full" data-reveal="intraoral" hidden>
                            <label class="form-label" for="intraoral_note">Temuan intraoral</label>
                            <textarea name="intraoral_note" id="intraoral_note" rows="2" class="form-control">{{ $exam['intraoral_note'] ?? '' }}</textarea>
                        </div>

                        @include('clinical.care.partials.choice-group', ['name' => 'occlusion', 'label' => 'Occlusi', 'options' => $examOptions['occlusion'], 'selected' => $exam['occlusion'] ?? null])
                        @include('clinical.care.partials.choice-group', ['name' => 'gingiva', 'label' => 'Gingiva', 'options' => $examOptions['gingiva'], 'selected' => $exam['gingiva'] ?? 'normal'])
                        @include('clinical.care.partials.choice-group', ['name' => 'mucosa', 'label' => 'Mukosa', 'options' => $examOptions['mucosa'], 'selected' => $exam['mucosa'] ?? 'normal'])
                        @include('clinical.care.partials.choice-group', ['name' => 'palate', 'label' => 'Palatum', 'options' => $examOptions['palate'], 'selected' => $exam['palate'] ?? null])
                        @include('clinical.care.partials.choice-group', ['name' => 'torus_palatinus', 'label' => 'Torus Palatinus', 'options' => $examOptions['torus'], 'selected' => $exam['torus_palatinus'] ?? 'none'])
                        @include('clinical.care.partials.choice-group', ['name' => 'torus_mandibularis', 'label' => 'Torus Mandibularis', 'options' => $examOptions['torus'], 'selected' => $exam['torus_mandibularis'] ?? 'none'])

                        @include('clinical.care.partials.choice-group', ['name' => 'diastema', 'label' => 'Diastema', 'options' => $examOptions['presence'], 'selected' => $exam['diastema'] ?? 'none', 'reveal' => 'diastema'])
                        <div class="exam-reveal" data-reveal="diastema" hidden>
                            <x-input name="diastema_elements" label="Elemen" :value="$exam['diastema_elements'] ?? null" />
                        </div>

                        @include('clinical.care.partials.choice-group', ['name' => 'supernumerary', 'label' => 'Supernumerary teeth', 'options' => $examOptions['presence'], 'selected' => $exam['supernumerary'] ?? 'none', 'reveal' => 'supernumerary'])
                        <div class="exam-reveal" data-reveal="supernumerary" hidden>
                            <x-input name="supernumerary_teeth" label="Gigi" :value="$exam['supernumerary_teeth'] ?? null" />
                        </div>

                        @include('clinical.care.partials.choice-group', ['name' => 'anomaly', 'label' => 'Anomali gigi', 'options' => $examOptions['presence'], 'selected' => $exam['anomaly'] ?? 'none', 'reveal' => 'anomaly'])
                        <div class="exam-reveal" data-reveal="anomaly" hidden>
                            <x-input name="anomaly_description" label="Deskripsi" :value="$exam['anomaly_description'] ?? null" />
                        </div>

                        <div class="exam-field exam-field--full">
                            <label class="form-label" for="habits">Kebiasaan buruk / temuan lainnya</label>
                            <textarea name="habits" id="habits" rows="2" class="form-control">{{ $exam['habits'] ?? '' }}</textarea>
                        </div>
                    </div>
                </form>
            @else
                <p class="text-muted mb-0">Kunjungan tidak dapat diubah.</p>
            @endif
        @else
            <div class="exam-readonly">
                <div><span>Ekstraoral</span>{{ $examOptions['finding'][$exam['extraoral'] ?? ''] ?? '-' }}</div>
                <div><span>Intraoral</span>{{ $examOptions['finding'][$exam['intraoral'] ?? ''] ?? '-' }}</div>
                <div><span>Occlusi</span>{{ $examOptions['occlusion'][$exam['occlusion'] ?? ''] ?? '-' }}</div>
            </div>
        @endcan
    </div>
</section>
