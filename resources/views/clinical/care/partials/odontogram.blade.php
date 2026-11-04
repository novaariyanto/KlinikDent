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
            @can('odontogram.manage')
                @if ($writable)
                    <form action="{{ route('care.tooth.update', $visit) }}" method="POST" id="odontogram-form" class="odo-form">
                        @csrf
                        @method('PUT')
                        <div class="odo-form__head">
                            <div>
                                <div class="odo-form__title">Gigi <span data-odo-number>—</span></div>
                                <div class="odo-form__kind" data-odo-kind></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light" data-odo-reset disabled>
                                <i class="bx bx-reset"></i> Sehat / reset
                            </button>
                        </div>
                        <input type="hidden" name="tooth_number" required>
                        <input type="hidden" name="status" value="healthy">
                        <input type="hidden" name="conditions" value="[]">
                        <p class="care-section__hint mb-2" data-odo-guide>Pilih gigi pada chart, lalu tambahkan satu atau beberapa kondisi.</p>

                        <fieldset class="odo-fieldset" data-odo-fieldset disabled>
                            <div class="odo-current" data-odo-current></div>

                            <div class="odo-editor" data-odo-editor hidden>
                                <div class="odo-editor__head">
                                    <i data-odo-editor-swatch></i>
                                    <strong data-odo-editor-label></strong>
                                    <button type="button" class="btn btn-sm btn-link p-0 ms-auto" data-odo-editor-close>Selesai</button>
                                </div>
                                <div class="odo-surface-picks" data-odo-editor-surfaces>
                                    <div class="form-label mb-1">Permukaan</div>
                                    <div class="odo-surface-grid">
                                        @foreach (\App\Enums\ToothSurface::cases() as $surface)
                                            <label data-surface-option="{{ $surface->value }}">
                                                <input type="checkbox" value="{{ $surface->value }}" data-odo-surface>
                                                <b>{{ $surface->short() }}</b>
                                                <small>{{ $surface->label() }}</small>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="odo-editor__warn" data-odo-surface-warn hidden>Pilih minimal satu permukaan agar kondisi tersimpan.</div>
                                </div>
                                <div class="odo-editor__detail" data-odo-editor-detail></div>
                                <div class="odo-editor__note">
                                    <label class="form-label mb-1" for="odo-condition-note" data-odo-note-label>Catatan tambahan</label>
                                    <input type="text" id="odo-condition-note" class="form-control form-control-sm" maxlength="255" data-odo-note>
                                </div>
                            </div>

                            <div class="odo-picker" data-odo-picker>
                                <input type="search" class="form-control form-control-sm" placeholder="Cari kondisi atau kode (mis. KP, karies, GTS)" aria-label="Cari kondisi gigi" data-odo-search>
                                <div class="odo-picker__list">
                                    @foreach (\App\Enums\ToothStatus::groups() as $groupKey => $groupLabel)
                                        <div class="odo-picker__group" data-odo-group>
                                            <div class="odo-picker__title">{{ $groupLabel }}</div>
                                            <div class="odo-picker__grid">
                                                @foreach ($toothStatusMeta as $value => $meta)
                                                    @continue($meta['group'] !== $groupKey)
                                                    <button type="button" class="odo-pick" data-odo-pick="{{ $value }}" title="{{ $meta['label'] }}">
                                                        <i style="background: {{ $meta['color'] }}">{{ $meta['symbol'] }}</i>
                                                        <span>{{ $meta['label'] }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="odo-picker__empty" data-odo-search-empty hidden>Kondisi tidak ditemukan.</div>
                                </div>
                            </div>
                        </fieldset>

                        <div class="mt-3">
                            <x-input name="notes" label="Catatan gigi" :value="old('notes')" />
                        </div>
                        <x-button type="submit" icon="bx bx-save">Simpan gigi</x-button>
                    </form>
                @else
                    @include('clinical.care.partials.odontogram-legend')
                    <p class="text-muted mb-0">Pilih gigi pada chart untuk melihat status.</p>
                @endif
            @else
                @include('clinical.care.partials.odontogram-legend')
            @endcan
        </aside>
    </div>

    @include('clinical.care.partials.odontogram-list')
</section>

@push('scripts')
<script>
(function () {
    var form = document.getElementById('odontogram-form');
    var chart = document.querySelector('.odo-chart');
    if (!chart) return;
    var hint = chart.querySelector('[data-odo-hint]');
    var numberLabel = document.querySelector('[data-odo-number]');
    var findingsBody = document.querySelector('[data-odo-findings]');
    var board = document.querySelector('[data-odo-board]');
    var meta = @json($toothStatusMeta);
    var surfaceShort = @json(collect(\App\Enums\ToothSurface::cases())->mapWithKeys(fn ($s) => [$s->value => $s->short()]));
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    var el = form ? {
        number: form.querySelector('[name="tooth_number"]'),
        status: form.querySelector('[name="status"]'),
        conditions: form.querySelector('[name="conditions"]'),
        notes: form.querySelector('[name="notes"]'),
        kind: form.querySelector('[data-odo-kind]'),
        guide: form.querySelector('[data-odo-guide]'),
        reset: form.querySelector('[data-odo-reset]'),
        fieldset: form.querySelector('[data-odo-fieldset]'),
        current: form.querySelector('[data-odo-current]'),
        editor: form.querySelector('[data-odo-editor]'),
        editorSwatch: form.querySelector('[data-odo-editor-swatch]'),
        editorLabel: form.querySelector('[data-odo-editor-label]'),
        editorSurfaces: form.querySelector('[data-odo-editor-surfaces]'),
        surfaceWarn: form.querySelector('[data-odo-surface-warn]'),
        detail: form.querySelector('[data-odo-editor-detail]'),
        note: form.querySelector('[data-odo-note]'),
        noteLabel: form.querySelector('[data-odo-note-label]'),
        search: form.querySelector('[data-odo-search]'),
        searchEmpty: form.querySelector('[data-odo-search-empty]')
    } : null;

    var state = { tooth: null, conditions: [], editing: null, clicked: null };
    var timers = {};

    function debounce(key, fn, wait) {
        clearTimeout(timers[key]);
        timers[key] = setTimeout(fn, wait);
    }

    function parseConditions(tooth) {
        try { return JSON.parse(tooth.dataset.conditions || '[]') || []; } catch (e) { return []; }
    }

    function toothInfo(tooth) {
        var kind = tooth.dataset.kind || 'molar';
        var anterior = kind === 'incisor' || kind === 'canine';
        var upper = tooth.classList.contains('is-upper');
        return {
            number: tooth.dataset.number,
            kind: kind,
            anterior: anterior,
            upper: upper,
            primary: tooth.classList.contains('is-primary'),
            surfaces: ['mesial', 'distal', anterior ? 'incisal' : 'occlusal', 'buccal', upper ? 'palatal' : 'lingual']
        };
    }

    function canonical(info, key) {
        if (key === 'occlusal' || key === 'incisal') return info.anterior ? 'incisal' : 'occlusal';
        if (key === 'palatal' || key === 'lingual') return info.upper ? 'palatal' : 'lingual';
        return key;
    }

    function available(code, info) {
        var m = meta[code];
        if (!m || code === 'healthy' || !info) return false;
        if (m.scope === 'adult' && info.primary) return false;
        if (m.scope === 'primary' && !info.primary) return false;
        return !m.kinds || m.kinds.indexOf(info.kind) !== -1;
    }

    function findCondition(code) {
        for (var i = 0; i < state.conditions.length; i++) {
            if (state.conditions[i].code === code) return state.conditions[i];
        }
        return null;
    }

    function isPending(cond) {
        return !!(meta[cond.code] && meta[cond.code].needsSurface && !cond.surfaces.length);
    }

    function describe(cond) {
        var parts = [];
        if (cond.surfaces && cond.surfaces.length) {
            parts.push(cond.surfaces.map(function (s) { return surfaceShort[s] || ''; }).join(''));
        }
        var field = meta[cond.code] && meta[cond.code].detail;
        var value = field && cond.detail ? cond.detail[field.key] : null;
        if (field && value) {
            parts.push(field.type === 'number' ? value + ' ' + (field.unit || '') : (field.options[value] || value));
        }
        return parts.join(' · ').trim();
    }

    function swatch(code) {
        var i = document.createElement('i');
        i.style.background = (meta[code] && meta[code].color) || '#9E9E9E';
        i.textContent = (meta[code] && meta[code].symbol) || '';
        return i;
    }

    function renderCurrent() {
        el.current.innerHTML = '';
        if (!state.tooth) return;
        if (!state.conditions.length) {
            var empty = document.createElement('div');
            empty.className = 'odo-current__empty';
            empty.textContent = 'Gigi sehat. Tambahkan kondisi dari daftar di bawah.';
            el.current.appendChild(empty);
            return;
        }
        state.conditions.forEach(function (cond) {
            var row = document.createElement('div');
            row.className = 'odo-current__item' + (state.editing === cond.code ? ' is-editing' : '') + (isPending(cond) ? ' is-pending' : '');
            var main = document.createElement('button');
            main.type = 'button';
            main.className = 'odo-current__main';
            main.setAttribute('data-odo-current-edit', cond.code);
            main.appendChild(swatch(cond.code));
            var text = document.createElement('span');
            var label = document.createElement('strong');
            label.textContent = meta[cond.code] ? meta[cond.code].label : cond.code;
            text.appendChild(label);
            var extra = isPending(cond) ? 'pilih permukaan' : describe(cond);
            if (extra) {
                var small = document.createElement('small');
                small.textContent = extra;
                text.appendChild(small);
            }
            main.appendChild(text);
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'odo-current__remove';
            remove.setAttribute('data-odo-current-remove', cond.code);
            remove.setAttribute('aria-label', 'Hapus kondisi ' + label.textContent);
            remove.innerHTML = '<i class="bx bx-x"></i>';
            row.appendChild(main);
            row.appendChild(remove);
            el.current.appendChild(row);
        });
    }

    function renderPicker() {
        var info = state.tooth ? toothInfo(state.tooth) : null;
        var q = (el.search.value || '').trim().toLowerCase();
        var visibleTotal = 0;
        form.querySelectorAll('[data-odo-group]').forEach(function (group) {
            var visible = 0;
            group.querySelectorAll('[data-odo-pick]').forEach(function (btn) {
                var code = btn.getAttribute('data-odo-pick');
                var show = (!info || available(code, info)) && (!q || (meta[code].search || '').indexOf(q) !== -1);
                btn.hidden = !show;
                btn.classList.toggle('is-active', !!findCondition(code));
                btn.classList.toggle('is-editing', state.editing === code);
                if (show) visible++;
            });
            group.hidden = visible === 0;
            visibleTotal += visible;
        });
        el.searchEmpty.hidden = visibleTotal > 0;
    }

    function renderDetail(cond) {
        el.detail.innerHTML = '';
        var field = meta[cond.code].detail;
        if (!field) return;
        var wrap = document.createElement('div');
        var label = document.createElement('div');
        label.className = 'form-label mb-1';
        label.textContent = field.label;
        wrap.appendChild(label);
        var value = (cond.detail || {})[field.key] || '';

        if (field.type === 'choice') {
            var group = document.createElement('div');
            group.className = 'odo-choice';
            Object.keys(field.options).forEach(function (key) {
                var opt = document.createElement('label');
                var input = document.createElement('input');
                input.type = 'radio';
                input.name = 'odo_detail_choice';
                input.value = key;
                input.checked = value === key;
                input.setAttribute('data-odo-detail', field.key);
                var span = document.createElement('span');
                span.textContent = field.options[key];
                opt.appendChild(input);
                opt.appendChild(span);
                group.appendChild(opt);
            });
            wrap.appendChild(group);
        } else if (field.type === 'select') {
            var select = document.createElement('select');
            select.className = 'form-select form-select-sm';
            select.setAttribute('data-odo-detail', field.key);
            select.appendChild(new Option('— Pilih —', ''));
            Object.keys(field.options).forEach(function (key) {
                select.appendChild(new Option(field.options[key], key, false, value === key));
            });
            wrap.appendChild(select);
        } else if (field.type === 'number') {
            var box = document.createElement('div');
            box.className = 'input-group input-group-sm';
            var input = document.createElement('input');
            input.type = 'number';
            input.className = 'form-control';
            input.min = field.min;
            input.max = field.max;
            input.step = field.step;
            input.inputMode = 'decimal';
            input.value = value;
            input.setAttribute('data-odo-detail', field.key);
            var unit = document.createElement('span');
            unit.className = 'input-group-text';
            unit.textContent = field.unit || '';
            box.appendChild(input);
            box.appendChild(unit);
            wrap.appendChild(box);
        }
        el.detail.appendChild(wrap);
    }

    function renderEditor() {
        var cond = state.editing ? findCondition(state.editing) : null;
        el.editor.hidden = !cond;
        if (!cond) return;
        var m = meta[cond.code];
        var info = toothInfo(state.tooth);
        var sw = swatch(cond.code);
        el.editorSwatch.replaceWith(sw);
        el.editorSwatch = sw;
        el.editorLabel.textContent = m.label;
        el.editorSurfaces.hidden = !m.needsSurface;
        el.editorSurfaces.querySelectorAll('[data-surface-option]').forEach(function (label) {
            var key = label.getAttribute('data-surface-option');
            label.hidden = info.surfaces.indexOf(key) === -1;
            label.querySelector('input').checked = cond.surfaces.indexOf(key) !== -1;
        });
        el.surfaceWarn.hidden = !isPending(cond);
        renderDetail(cond);
        el.noteLabel.textContent = m.noteLabel || 'Catatan tambahan';
        el.note.value = cond.note || '';
    }

    function render() {
        if (!el) return;
        renderCurrent();
        renderEditor();
        renderPicker();
    }

    function commit() {
        if (!form || !state.tooth) return;
        var saved = state.conditions.filter(function (c) { return !isPending(c); });
        el.conditions.value = JSON.stringify(saved);
        el.status.value = saved.length ? saved[0].code : 'healthy';
        if (!window.careSave) return;
        window.careSave(form).then(applyPayload);
    }

    function replaceTooth(number, html) {
        var old = chart.querySelector('.odo-tooth[data-number="' + number + '"]');
        if (!old || !html) return null;
        var holder = document.createElement('div');
        holder.innerHTML = html.trim();
        var fresh = holder.firstElementChild;
        if (!fresh) return null;
        if (old.classList.contains('is-selected')) fresh.classList.add('is-selected');
        old.replaceWith(fresh);
        if (state.tooth === old) state.tooth = fresh;
        return fresh;
    }

    function upsertFinding(tooth) {
        if (!findingsBody) return;
        var row = findingsBody.querySelector('[data-finding-row="' + tooth.number + '"]');
        var empty = findingsBody.querySelector('[data-odo-empty]');
        if (!tooth.row_html) {
            if (row) row.remove();
        } else {
            var holder = document.createElement('div');
            holder.innerHTML = tooth.row_html.trim();
            var fresh = holder.firstElementChild;
            if (row) row.replaceWith(fresh);
            else findingsBody.appendChild(fresh);
            if (empty) empty.remove();
        }
        if (!findingsBody.querySelector('[data-finding-row]')) {
            findingsBody.innerHTML = '<div class="odo-board__empty" data-odo-empty>Belum ada temuan. Klik gigi pada chart untuk mencatat kondisi.</div>';
        }
        var count = document.querySelector('[data-odo-count]');
        if (count) count.textContent = findingsBody.querySelectorAll('[data-finding-row]').length;
    }

    function applyPayload(data) {
        if (!data || !data.tooth) return;
        replaceTooth(data.tooth.number, data.tooth.html);
        upsertFinding(data.tooth);
        if (window.careSyncOdoToothOption) window.careSyncOdoToothOption(data.tooth);
    }

    function kindLabel(info) {
        var names = { incisor: 'Insisivus', canine: 'Kaninus', premolar: 'Premolar', molar: 'Molar' };
        return names[info.kind] + ' · ' + (info.primary ? 'gigi sulung' : 'gigi permanen') + ' · rahang ' + (info.upper ? 'atas' : 'bawah');
    }

    function selectTooth(tooth, surface) {
        chart.querySelectorAll('.odo-tooth.is-selected').forEach(function (n) { n.classList.remove('is-selected'); });
        chart.querySelectorAll('.odo-surf.is-selected').forEach(function (n) { n.classList.remove('is-selected'); });
        tooth.classList.add('is-selected');
        if (surface) surface.classList.add('is-selected');
        if (!form) return;
        var info = toothInfo(tooth);
        state.tooth = tooth;
        state.conditions = parseConditions(tooth).map(function (c) {
            return { code: c.code, surfaces: (c.surfaces || []).slice(), detail: Object.assign({}, c.detail || {}), note: c.note || '' };
        });
        state.clicked = surface ? canonical(info, surface.dataset.surface) : null;
        state.editing = null;
        if (state.clicked) {
            var hit = state.conditions.filter(function (c) { return c.surfaces.indexOf(state.clicked) !== -1; }).pop();
            if (hit) state.editing = hit.code;
        }
        el.number.value = info.number;
        if (numberLabel) numberLabel.textContent = info.number;
        el.kind.textContent = kindLabel(info);
        el.notes.value = tooth.dataset.notes || '';
        el.fieldset.disabled = false;
        el.reset.disabled = false;
        el.guide.hidden = true;
        render();
    }

    function pick(code) {
        if (!state.tooth || !meta[code]) return;
        if (findCondition(code)) {
            state.editing = code;
            render();
            return;
        }
        var info = toothInfo(state.tooth);
        var m = meta[code];
        var cond = { code: code, surfaces: [], detail: {}, note: '' };
        if (m.detail && m.detail['default']) cond.detail[m.detail.key] = m.detail['default'];
        if (m.needsSurface && state.clicked && info.surfaces.indexOf(state.clicked) !== -1) cond.surfaces.push(state.clicked);
        state.conditions.push(cond);
        state.editing = code;
        render();
        if (!isPending(cond)) commit();
    }

    function removeCondition(code) {
        state.conditions = state.conditions.filter(function (c) { return c.code !== code; });
        if (state.editing === code) state.editing = null;
        render();
        commit();
    }

    function deleteFinding(number) {
        if (!board || !number) return;
        if (!window.confirm('Hapus temuan gigi ' + number + '? Semua kondisi gigi ini dihapus dan status kembali ke sehat.')) return;
        var statusEl = document.querySelector('[data-care-save]');
        if (statusEl) statusEl.textContent = 'Menghapus…';
        fetch(board.getAttribute('data-odo-destroy') + '/' + number, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (res) {
            if (!res.ok) throw new Error('delete failed');
            return res.json();
        }).then(function (data) {
            applyPayload(data);
            if (form && state.tooth && state.tooth.dataset.number === number) {
                selectTooth(state.tooth);
            }
            if (statusEl) statusEl.textContent = '✓ Tersimpan';
        }).catch(function () {
            if (statusEl) statusEl.textContent = 'Gagal menghapus';
        });
    }

    function toggleSurface(surface) {
        var cond = findCondition(state.editing);
        if (!cond || !meta[cond.code].needsSurface) return false;
        var info = toothInfo(state.tooth);
        var key = canonical(info, surface.dataset.surface);
        var picked = cond.surfaces.indexOf(key) === -1
            ? cond.surfaces.concat([key])
            : cond.surfaces.filter(function (s) { return s !== key; });
        cond.surfaces = info.surfaces.filter(function (s) { return picked.indexOf(s) !== -1; });
        surface.style.setProperty('--surf', cond.surfaces.indexOf(key) !== -1 ? meta[cond.code].color : '#ffffff');
        state.clicked = key;
        render();
        commit();
        return true;
    }

    chart.addEventListener('click', function (e) {
        var tooth = e.target.closest('.odo-tooth');
        if (!tooth) return;
        var surface = e.target.closest('.odo-surf');
        if (form && surface && tooth === state.tooth && toggleSurface(surface)) return;
        selectTooth(tooth, surface);
    });

    document.addEventListener('click', function (e) {
        var editBtn = e.target.closest('[data-odo-edit]');
        if (editBtn) {
            var tooth = chart.querySelector('.odo-tooth[data-number="' + editBtn.getAttribute('data-odo-edit') + '"]');
            if (tooth) {
                selectTooth(tooth);
                tooth.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
            return;
        }
        var deleteBtn = e.target.closest('[data-odo-delete]');
        if (deleteBtn) deleteFinding(deleteBtn.getAttribute('data-odo-delete'));
    });

    if (form) {
        form.addEventListener('click', function (e) {
            var pickBtn = e.target.closest('[data-odo-pick]');
            if (pickBtn) return pick(pickBtn.getAttribute('data-odo-pick'));
            var editCond = e.target.closest('[data-odo-current-edit]');
            if (editCond) {
                var code = editCond.getAttribute('data-odo-current-edit');
                state.editing = state.editing === code ? null : code;
                return render();
            }
            var removeCond = e.target.closest('[data-odo-current-remove]');
            if (removeCond) return removeCondition(removeCond.getAttribute('data-odo-current-remove'));
            if (e.target.closest('[data-odo-editor-close]')) {
                state.editing = null;
                return render();
            }
        });

        el.reset.addEventListener('click', function () {
            if (!state.tooth) return;
            if (state.conditions.length && !window.confirm('Reset gigi ' + state.tooth.dataset.number + ' ke sehat? Semua kondisi gigi ini dihapus.')) return;
            state.conditions = [];
            state.editing = null;
            render();
            commit();
        });

        el.editorSurfaces.addEventListener('change', function (e) {
            var cond = findCondition(state.editing);
            if (!cond || !e.target.matches('[data-odo-surface]')) return;
            var info = toothInfo(state.tooth);
            cond.surfaces = info.surfaces.filter(function (key) {
                var box = el.editorSurfaces.querySelector('[data-odo-surface][value="' + key + '"]');
                return box && box.checked;
            });
            el.surfaceWarn.hidden = !isPending(cond);
            renderCurrent();
            commit();
        });

        el.detail.addEventListener('change', function (e) {
            var cond = findCondition(state.editing);
            var key = e.target.getAttribute('data-odo-detail');
            if (!cond || !key) return;
            if (e.target.value === '') delete cond.detail[key];
            else cond.detail[key] = e.target.value;
            renderCurrent();
            commit();
        });

        el.detail.addEventListener('input', function (e) {
            if (e.target.type !== 'number') return;
            var cond = findCondition(state.editing);
            var key = e.target.getAttribute('data-odo-detail');
            if (!cond || !key) return;
            cond.detail[key] = e.target.value;
            debounce('detail', function () { renderCurrent(); commit(); }, 600);
        });

        el.note.addEventListener('input', function () {
            var cond = findCondition(state.editing);
            if (!cond) return;
            cond.note = el.note.value;
            if (!isPending(cond)) debounce('note', commit, 600);
        });

        el.notes.addEventListener('input', function () {
            debounce('notes', commit, 600);
        });

        el.search.addEventListener('input', renderPicker);
        el.search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                el.search.value = '';
                renderPicker();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                var first = form.querySelector('[data-odo-pick]:not([hidden])');
                if (first) pick(first.getAttribute('data-odo-pick'));
            }
        });

        form.addEventListener('submit', function (e) {
            if (!window.careSave) return;
            e.preventDefault();
            commit();
        });

        renderPicker();
    }

    chart.addEventListener('pointerover', function (e) {
        var surf = e.target.closest('.odo-surf');
        if (hint && surf && surf.dataset.label) hint.textContent = surf.dataset.label;
    });
})();
</script>
@endpush

