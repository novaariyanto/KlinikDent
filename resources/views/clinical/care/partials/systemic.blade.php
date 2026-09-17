@php($sys = old() + $systemicHistory)
@php($conditions = old('conditions', $sys['conditions'] ?? []))
<section class="care-section" id="systemic">
    <div class="care-section__head">
        <h5 class="care-section__title">Riwayat Penyakit Sistemik</h5>
        <p class="care-section__hint">Kondisi umum, penyakit penyerta, alergi, dan faktor risiko.</p>
    </div>
    <div class="care-section__body">
        @if ($writable && (auth()->user()?->can('anamnesis.manage') || auth()->user()?->can('medical_record.update')))
            <form action="{{ route('care.systemic.update', $visit) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="sys-grid">
                    @foreach ($examOptions['systemic'] as $value => $label)
                        <label class="exam-choice exam-choice--check" data-sys-toggle="{{ $value }}">
                            <input type="checkbox" name="conditions[]" value="{{ $value }}" @checked(in_array($value, $conditions, true))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="exam-reveal mt-3" data-sys-panel="hypertension" hidden>
                    <div class="row">
                        <div class="col-sm-4">
                            <x-input name="blood_pressure_sys" label="Tekanan darah (sistol)" :value="$sys['blood_pressure_sys'] ?? null" />
                        </div>
                        <div class="col-sm-4">
                            <x-input name="blood_pressure_dia" label="Diastol" :value="$sys['blood_pressure_dia'] ?? null" />
                        </div>
                        <div class="col-sm-4 d-flex align-items-end pb-3">
                            <span class="text-muted">mmHg</span>
                        </div>
                    </div>
                </div>
                <div class="exam-reveal" data-sys-panel="allergy" hidden>
                    <x-input name="allergy_detail" label="Alergi" :value="$sys['allergy_detail'] ?? null" />
                </div>
                <div class="exam-reveal" data-sys-panel="other" hidden>
                    <x-input name="other_detail" label="Keterangan" :value="$sys['other_detail'] ?? null" />
                </div>

                <x-button type="submit" icon="bx bx-save">Simpan riwayat sistemik</x-button>
            </form>
        @else
            <div class="exam-readonly">
                @forelse ($conditions as $condition)
                    <div>{{ $examOptions['systemic'][$condition] ?? $condition }}</div>
                @empty
                    <div class="text-muted">Tidak ada riwayat sistemik tercatat.</div>
                @endforelse
            </div>
        @endif
    </div>
</section>

@push('scripts')
<script>
(function () {
    function syncSys() {
        ['hypertension', 'allergy', 'other'].forEach(function (key) {
            var box = document.querySelector('[data-sys-toggle="' + key + '"] input');
            var panel = document.querySelector('[data-sys-panel="' + key + '"]');
            if (panel) panel.hidden = !(box && box.checked);
        });
    }
    document.querySelectorAll('[data-sys-toggle] input').forEach(function (el) {
        el.addEventListener('change', syncSys);
    });
    syncSys();
})();
</script>
@endpush
