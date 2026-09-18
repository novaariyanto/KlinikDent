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
                        <div class="odo-form__title">Gigi <span data-odo-number>—</span></div>
                        <input type="hidden" name="tooth_number" required>
                        <input type="hidden" name="status" value="healthy">
                        <p class="care-section__hint mb-2">Pilih gigi, lalu kondisi. Permukaan muncul jika karies atau tumpatan.</p>
                        <div class="odo-legend odo-status-picks">
                            @foreach ($toothStatusMeta as $value => $meta)
                                <label class="odo-legend__item">
                                    <input type="radio" name="odo_status" value="{{ $value }}" data-whole="{{ $meta['whole'] ? '1' : '0' }}" data-surface="{{ ! empty($meta['needsSurface']) ? '1' : '0' }}" @checked($value === 'healthy')>
                                    <i style="background: {{ $meta['color'] }}">{{ $meta['symbol'] }}</i>
                                    <span>{{ $value === 'healthy' ? 'Sehat / reset' : $meta['label'] }}</span>
                                </label>
                            @endforeach
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
    var surfacesWrap = document.querySelector('[data-odo-surfaces]');
    var numberLabel = document.querySelector('[data-odo-number]');
    var notesInput = form ? form.querySelector('[name="notes"]') : null;
    var findingsBody = document.querySelector('[data-odo-findings]');
    var board = document.querySelector('[data-odo-board]');
    var notesTimer;
    var meta = @json($toothStatusMeta);
    var canEdit = {{ $writable && auth()->user()?->can('odontogram.manage') ? 'true' : 'false' }};
    var ns = 'http://www.w3.org/2000/svg';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    function selectedSurfaces(tooth) {
        try { return JSON.parse(tooth.dataset.surfaces || '{}'); } catch (e) { return {}; }
    }

    function syncSurfaces(status) {
        var info = meta[status] || {};
        if (surfacesWrap) surfacesWrap.hidden = !info.needsSurface;
    }

    function svgEl(name, attrs) {
        var node = document.createElementNS(ns, name);
        Object.keys(attrs).forEach(function (key) { node.setAttribute(key, attrs[key]); });
        return node;
    }

    function paintOverlay(svg, status) {
        svg.querySelectorAll('.odo-overlay').forEach(function (node) { node.remove(); });
        if (status === 'extracted' || status === 'missing') {
            var g = svgEl('g', { 'class': 'odo-overlay odo-overlay--x' + (status === 'missing' ? ' is-missing' : '') });
            g.appendChild(svgEl('line', { x1: '7', y1: '7', x2: '93', y2: '93' }));
            g.appendChild(svgEl('line', { x1: '93', y1: '7', x2: '7', y2: '93' }));
            svg.appendChild(g);
        } else if (status === 'crown') {
            svg.appendChild(svgEl('rect', { 'class': 'odo-overlay odo-overlay--crown', x: '3.5', y: '3.5', width: '93', height: '93' }));
            svg.appendChild(svgEl('rect', { 'class': 'odo-overlay odo-overlay--crown-inner', x: '7', y: '7', width: '86', height: '86' }));
        } else if (status === 'root_canal') {
            svg.appendChild(svgEl('polygon', { 'class': 'odo-overlay odo-overlay--rct', points: '50,38 61,62 39,62' }));
        } else if (status === 'fracture') {
            svg.appendChild(svgEl('polyline', { 'class': 'odo-overlay odo-overlay--fracture', points: '22,8 38,32 28,52 46,72 40,92' }));
        } else if (status === 'implant') {
            svg.appendChild(svgEl('circle', { 'class': 'odo-overlay odo-overlay--implant', cx: '50', cy: '50', r: '11' }));
        } else if (status === 'bridge') {
            svg.appendChild(svgEl('line', { 'class': 'odo-overlay odo-overlay--bridge', x1: '4', y1: '10', x2: '96', y2: '10' }));
            svg.appendChild(svgEl('line', { 'class': 'odo-overlay odo-overlay--bridge', x1: '4', y1: '90', x2: '96', y2: '90' }));
        } else if (status === 'impacted') {
            svg.appendChild(svgEl('polygon', { 'class': 'odo-overlay odo-overlay--impacted', points: '50,14 64,38 36,38' }));
        } else if (status === 'mobility') {
            var text = svgEl('text', { 'class': 'odo-overlay odo-overlay--mobility', x: '50', y: '56' });
            text.textContent = 'M';
            svg.appendChild(text);
        }
    }

    function applyVisual(tooth, status, surfaces, notes) {
        var info = meta[status] || {};
        var color = info.color || '#ffffff';
        var fillSurfaces = !!info.needsSurface;
        tooth.dataset.status = status;
        tooth.dataset.surfaces = JSON.stringify(fillSurfaces ? surfaces : {});
        if (typeof notes === 'string') tooth.dataset.notes = notes;
        else if (notesInput) tooth.dataset.notes = notesInput.value || '';
        tooth.title = tooth.dataset.number + ' — ' + (info.label || status);
        Object.keys(meta).forEach(function (key) {
            tooth.classList.toggle('is-' + key, key === status && status !== 'healthy');
        });
        tooth.querySelectorAll('.odo-surf').forEach(function (surf) {
            var key = surf.dataset.surface;
            surf.style.setProperty('--surf', fillSurfaces && surfaces[key] ? color : '#ffffff');
        });
        var svg = tooth.querySelector('.odo-svg');
        if (svg) paintOverlay(svg, status);
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
        var picked = Object.keys(current);
        if (surface && picked.indexOf(surface.dataset.surface) === -1) {
            picked.push(surface.dataset.surface);
        }
        form.querySelectorAll('[name="surfaces[]"]').forEach(function (box) {
            box.checked = picked.indexOf(box.value) !== -1;
        });
    }

    function findingRow(tooth) {
        var article = document.createElement('article');
        article.className = 'odo-finding';
        article.setAttribute('data-finding-row', tooth.number);
        var notes = tooth.notes ? '<p class="odo-finding__notes"></p>' : '';
        var surfaces = tooth.surface_labels ? '<span></span>' : '';
        var actions = canEdit
            ? '<div class="odo-finding__actions"><button type="button" class="btn btn-sm btn-soft-primary" data-odo-edit="' + tooth.number + '">Ubah</button><button type="button" class="btn btn-sm btn-soft-danger" data-odo-delete="' + tooth.number + '">Hapus</button></div>'
            : '';
        article.innerHTML =
            '<div class="odo-finding__num"></div>' +
            '<div class="odo-finding__body">' +
                '<div class="odo-finding__status"><i></i><strong></strong>' + surfaces + '</div>' +
                notes +
            '</div>' +
            actions;
        article.querySelector('.odo-finding__num').textContent = tooth.number;
        article.querySelector('.odo-finding__status i').style.background = tooth.color || '#adb5bd';
        article.querySelector('.odo-finding__status strong').textContent = tooth.status_label || tooth.status;
        var surfEl = article.querySelector('.odo-finding__status span');
        if (surfEl) surfEl.textContent = tooth.surface_labels;
        var notesEl = article.querySelector('.odo-finding__notes');
        if (notesEl) notesEl.textContent = tooth.notes;
        return article;
    }

    function upsertFinding(tooth) {
        if (!findingsBody) return;
        var empty = findingsBody.querySelector('[data-odo-empty]');
        var row = findingsBody.querySelector('[data-finding-row="' + tooth.number + '"]');
        if (tooth.status === 'healthy') {
            if (row) row.remove();
        } else if (row) {
            row.replaceWith(findingRow(tooth));
        } else {
            findingsBody.appendChild(findingRow(tooth));
        }
        if (empty) empty.remove();
        if (!findingsBody.querySelector('[data-finding-row]')) {
            findingsBody.innerHTML = '<div class="odo-board__empty" data-odo-empty>Belum ada temuan. Klik gigi pada chart untuk mencatat kondisi.</div>';
        }
        var count = document.querySelector('[data-odo-count]');
        if (count) count.textContent = findingsBody.querySelectorAll('[data-finding-row]').length;
    }

    function applyPayload(data) {
        if (data && data.tooth) {
            upsertFinding(data.tooth);
            if (window.careSyncOdoToothOption) window.careSyncOdoToothOption(data.tooth);
        }
    }

    function saveTooth() {
        if (!form || !form.querySelector('[name="tooth_number"]').value) return;
        var tooth = chart.querySelector('.odo-tooth.is-selected');
        var status = form.querySelector('[name="status"]').value;
        var info = meta[status] || {};
        var surfaces = {};
        if (info.needsSurface) {
            form.querySelectorAll('[name="surfaces[]"]:checked').forEach(function (box) {
                surfaces[box.value] = status;
            });
        }
        if (tooth) applyVisual(tooth, status, surfaces);
        if (!window.careSave) return;
        window.careSave(form).then(applyPayload);
    }

    function resetInspector(number) {
        if (!form) return;
        var radio = form.querySelector('[name="odo_status"][value="healthy"]');
        form.querySelector('[name="tooth_number"]').value = number || '';
        form.querySelector('[name="status"]').value = 'healthy';
        if (radio) radio.checked = true;
        if (notesInput) notesInput.value = '';
        form.querySelectorAll('[name="surfaces[]"]').forEach(function (box) { box.checked = false; });
        syncSurfaces('healthy');
        if (numberLabel) numberLabel.textContent = number || '—';
    }

    function deleteFinding(number) {
        if (!board || !number) return;
        if (!window.confirm('Hapus temuan gigi ' + number + '? Status gigi kembali ke sehat.')) return;
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
            var tooth = chart.querySelector('.odo-tooth[data-number="' + number + '"]');
            if (tooth) applyVisual(tooth, 'healthy', {}, '');
            if (form && form.querySelector('[name="tooth_number"]').value === number) resetInspector(number);
            applyPayload(data);
            if (statusEl) statusEl.textContent = '✓ Tersimpan';
        }).catch(function () {
            if (statusEl) statusEl.textContent = 'Gagal menghapus';
        });
    }

    chart.querySelectorAll('.odo-tooth').forEach(function (tooth) {
        tooth.addEventListener('click', function (e) {
            selectTooth(tooth, e.target.closest('.odo-surf'));
        });
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

    chart.addEventListener('pointerover', function (e) {
        var surf = e.target.closest('.odo-surf');
        if (hint && surf && surf.dataset.label) hint.textContent = surf.dataset.label;
    });
})();
</script>
@endpush
