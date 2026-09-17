<section class="care-section" id="odontogram">
    <div class="care-section__head">
        <h5 class="care-section__title">Odontogram</h5>
        <p class="care-section__hint">Klik gigi yang bermasalah, pilih kondisi, lalu permukaan jika perlu. Gigi sehat tidak wajib diisi.</p>
    </div>

    <div class="care-section__body odo-layout">
        <div class="odo-chart" data-writable="{{ $writable && auth()->user()?->can('odontogram.manage') ? '1' : '0' }}">
            <div class="odo-sheet">
                <div class="odo-jaw-name">Rahang atas</div>

                <div class="odo-quad-labels odo-quad-labels--top">
                    <span>Kuadran 1</span>
                    <span>Kuadran 2</span>
                </div>

                <div class="odo-row odo-row--upper odo-row--primary">
                    @for ($i = 0; $i < 3; $i++)<span class="odo-skip" aria-hidden="true"></span>@endfor
                    @foreach ($chart['upper_primary'][0] as $number)
                        <x-odontogram.tooth :number="$number" :record="$teeth[$number] ?? null" />
                    @endforeach
                    <span class="odo-midline"></span>
                    @foreach ($chart['upper_primary'][1] as $number)
                        <x-odontogram.tooth :number="$number" :record="$teeth[$number] ?? null" />
                    @endforeach
                    @for ($i = 0; $i < 3; $i++)<span class="odo-skip" aria-hidden="true"></span>@endfor
                </div>

                <div class="odo-row odo-row--upper">
                    @foreach ($chart['upper_adult'][0] as $number)
                        <x-odontogram.tooth :number="$number" :record="$teeth[$number] ?? null" />
                    @endforeach
                    <span class="odo-midline"></span>
                    @foreach ($chart['upper_adult'][1] as $number)
                        <x-odontogram.tooth :number="$number" :record="$teeth[$number] ?? null" />
                    @endforeach
                </div>

                <div class="odo-plane"><span>Bidang oklusal</span></div>

                <div class="odo-row odo-row--lower">
                    @foreach ($chart['lower_adult'][0] as $number)
                        <x-odontogram.tooth :number="$number" :record="$teeth[$number] ?? null" />
                    @endforeach
                    <span class="odo-midline"></span>
                    @foreach ($chart['lower_adult'][1] as $number)
                        <x-odontogram.tooth :number="$number" :record="$teeth[$number] ?? null" />
                    @endforeach
                </div>

                <div class="odo-row odo-row--lower odo-row--primary">
                    @for ($i = 0; $i < 3; $i++)<span class="odo-skip" aria-hidden="true"></span>@endfor
                    @foreach ($chart['lower_primary'][0] as $number)
                        <x-odontogram.tooth :number="$number" :record="$teeth[$number] ?? null" />
                    @endforeach
                    <span class="odo-midline"></span>
                    @foreach ($chart['lower_primary'][1] as $number)
                        <x-odontogram.tooth :number="$number" :record="$teeth[$number] ?? null" />
                    @endforeach
                    @for ($i = 0; $i < 3; $i++)<span class="odo-skip" aria-hidden="true"></span>@endfor
                </div>

                <div class="odo-quad-labels odo-quad-labels--bottom">
                    <span>Kuadran 4</span>
                    <span>Kuadran 3</span>
                </div>

                <div class="odo-jaw-name odo-jaw-name--bottom">Rahang bawah</div>
            </div>

            <div class="odo-hint" data-odo-hint>Klik gigi yang memiliki temuan, bukan semua gigi.</div>
        </div>

        <aside class="odo-inspector">
            <div class="odo-legend">
                <div class="odo-legend__title">Kode simbol</div>
                @foreach ($toothStatusMeta as $value => $meta)
                    <button type="button" class="odo-legend__item" data-status="{{ $value }}" title="{{ $meta['label'] }}">
                        <i style="background: {{ $meta['color'] }}">{{ $meta['symbol'] }}</i>
                        <span>{{ $meta['label'] }}</span>
                    </button>
                @endforeach
            </div>

            @can('odontogram.manage')
                @if ($writable)
                    <form action="{{ route('care.tooth.update', $visit) }}" method="POST" id="odontogram-form" class="odo-form">
                        @csrf
                        @method('PUT')
                        <div class="odo-form__title">Gigi <span data-odo-number>—</span></div>
                        <input type="hidden" name="tooth_number" required>
                        <input type="hidden" name="status" value="healthy">
                        <p class="care-section__hint mb-2">Pilih kondisi, lalu permukaan jika diperlukan.</p>
                        <div class="odo-status-picks">
                            @foreach ($toothStatusMeta as $value => $meta)
                                @continue($value === 'healthy')
                                <label class="exam-choice exam-choice--check">
                                    <input type="radio" name="odo_status" value="{{ $value }}" data-whole="{{ $meta['whole'] ? '1' : '0' }}" data-surface="{{ ! empty($meta['needsSurface']) ? '1' : '0' }}">
                                    <span>{{ $meta['label'] }}</span>
                                </label>
                            @endforeach
                            <label class="exam-choice exam-choice--check">
                                <input type="radio" name="odo_status" value="healthy" data-whole="0" data-surface="0">
                                <span>Sehat / reset</span>
                            </label>
                        </div>
                        <div class="odo-surface-picks mt-3" data-odo-surfaces hidden>
                            <div class="form-label">Permukaan</div>
                            @foreach (\App\Enums\ToothSurface::cases() as $surface)
                                <label>
                                    <input type="checkbox" name="surfaces[]" value="{{ $surface->value }}">
                                    {{ $surface->short() }}
                                    <small>{{ $surface->label() }}</small>
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-3">
                            <x-input name="notes" label="Catatan" :value="old('notes')" />
                        </div>
                        <x-button type="submit" icon="bx bx-save">Simpan gigi</x-button>
                    </form>
                @else
                    <p class="text-muted mb-0">Pilih gigi pada chart untuk melihat status.</p>
                @endif
            @endcan
        </aside>
    </div>
</section>

@push('scripts')
<script>
(function () {
    var form = document.getElementById('odontogram-form');
    var chart = document.querySelector('.odo-chart');
    if (!chart) return;
    var hint = chart.querySelector('[data-odo-hint]');
    var surfacesWrap = document.querySelector('[data-odo-surfaces]');
    var numberLabel = document.querySelector('[data-odo-number]');
    var notesInput = form ? form.querySelector('[name="notes"]') : null;
    var notesTimer;
    var meta = @json($toothStatusMeta);

    function selectedSurfaces(tooth) {
        try { return JSON.parse(tooth.dataset.surfaces || '{}'); } catch (e) { return {}; }
    }

    function syncSurfaces(status) {
        var info = meta[status] || {};
        if (surfacesWrap) surfacesWrap.hidden = !info.needsSurface;
    }

    function selectTooth(el, surface) {
        chart.querySelectorAll('.odo-tooth.is-selected').forEach(function (n) { n.classList.remove('is-selected'); });
        chart.querySelectorAll('.odo-surf.is-selected').forEach(function (n) { n.classList.remove('is-selected'); });
        el.classList.add('is-selected');
        if (surface) surface.classList.add('is-selected');
        if (!form) return;
        form.querySelector('[name="tooth_number"]').value = el.dataset.number;
        if (numberLabel) numberLabel.textContent = el.dataset.number;
        var status = el.dataset.status || 'healthy';
        form.querySelector('[name="status"]').value = status;
        var radio = form.querySelector('[name="odo_status"][value="' + status + '"]');
        if (radio) radio.checked = true;
        if (notesInput) notesInput.value = el.dataset.notes || '';
        syncSurfaces(status);
        var current = selectedSurfaces(el);
        var picked = surface ? [surface.dataset.surface] : Object.keys(current);
        form.querySelectorAll('[name="surfaces[]"]').forEach(function (box) {
            box.checked = picked.indexOf(box.value) !== -1;
        });
    }

    function applyVisual(tooth, status, surfaces) {
        var info = meta[status] || {};
        var color = info.color || '#ffffff';
        tooth.dataset.status = status;
        tooth.dataset.surfaces = JSON.stringify(surfaces);
        if (notesInput) tooth.dataset.notes = notesInput.value || '';
        tooth.querySelectorAll('.odo-surf').forEach(function (surf) {
            var key = surf.dataset.surface;
            if (info.needsSurface) {
                surf.style.setProperty('--surf', surfaces[key] ? color : '#ffffff');
            } else if (status === 'healthy' || status === 'extracted' || status === 'missing') {
                surf.style.setProperty('--surf', '#ffffff');
            } else {
                surf.style.setProperty('--surf', color);
            }
        });
    }

    function saveTooth() {
        if (!form || !form.querySelector('[name="tooth_number"]').value) return;
        var tooth = chart.querySelector('.odo-tooth.is-selected');
        var status = form.querySelector('[name="status"]').value;
        var surfaces = {};
        form.querySelectorAll('[name="surfaces[]"]:checked').forEach(function (box) {
            surfaces[box.value] = status;
        });
        if (tooth) applyVisual(tooth, status, surfaces);
        if (window.careSave) window.careSave(form);
    }

    chart.querySelectorAll('.odo-tooth').forEach(function (tooth) {
        tooth.addEventListener('click', function (e) {
            selectTooth(tooth, e.target.closest('.odo-surf'));
        });
    });

    if (form) {
        form.querySelectorAll('[name="odo_status"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                form.querySelector('[name="status"]').value = radio.value;
                syncSurfaces(radio.value);
                if (radio.dataset.surface !== '1') {
                    form.querySelectorAll('[name="surfaces[]"]').forEach(function (box) { box.checked = false; });
                    saveTooth();
                    return;
                }
                if (form.querySelector('[name="surfaces[]"]:checked')) saveTooth();
            });
        });
        form.querySelectorAll('[name="surfaces[]"]').forEach(function (box) {
            box.addEventListener('change', saveTooth);
        });
        if (notesInput) {
            notesInput.addEventListener('input', function () {
                clearTimeout(notesTimer);
                notesTimer = setTimeout(saveTooth, 600);
            });
        }
        form.addEventListener('submit', function (e) {
            if (!window.careSave) return;
            e.preventDefault();
            saveTooth();
        });
    }

    document.querySelectorAll('.odo-legend__item').forEach(function (item) {
        item.addEventListener('click', function () {
            if (!form) return;
            var status = item.dataset.status;
            form.querySelector('[name="status"]').value = status;
            var radio = form.querySelector('[name="odo_status"][value="' + status + '"]');
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    chart.addEventListener('pointerover', function (e) {
        var surf = e.target.closest('.odo-surf');
        if (hint && surf && surf.dataset.label) hint.textContent = surf.dataset.label;
    });
})();
</script>
@endpush
