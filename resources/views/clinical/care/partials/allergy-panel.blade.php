@php
    $stored = is_array($systemicHistory ?? null) ? $systemicHistory : [];
    $postedSystemic = old('allergy_status') !== null || old('conditions') !== null || old('none') !== null;
    $sys = $postedSystemic ? array_merge($stored, old()) : $stored;
    $conditions = $postedSystemic ? (array) old('conditions', $stored['conditions'] ?? []) : (array) ($stored['conditions'] ?? []);
    $conditions = array_values(array_filter($conditions, fn ($value) => is_string($value) && $value !== ''));
    $none = filter_var($postedSystemic ? old('none', false) : ($stored['none'] ?? false), FILTER_VALIDATE_BOOLEAN);
    $allergyStatus = (string) ($sys['allergy_status'] ?? 'not_asked');
    $medicationStatus = (string) ($sys['medication_status'] ?? '');
    $allergyItems = \App\Support\Clinical\AllergyHistory::items($sys);
    $canEditAllergy = $writable && (auth()->user()?->can('anamnesis.manage') || auth()->user()?->can('medical_record.update'));
    $formId = $allergyFormId ?? 'allergy-manager-form';
    $categories = $examOptions['allergy_categories'] ?? [];
    $types = $examOptions['allergy_types'] ?? [];
    $clinicalStatuses = $examOptions['allergy_clinical_statuses'] ?? [];
    $severities = $examOptions['allergy_severities'] ?? [];
    $vitalSigns = is_array($record?->vital_signs) ? $record->vital_signs : [];
    $split = array_pad(explode('/', (string) ($vitalSigns['blood_pressure'] ?? ''), 2), 2, '');
    $bpSys = (string) ($vitalSigns['blood_pressure_sys'] ?? ($split[0] !== '' ? $split[0] : ($sys['blood_pressure_sys'] ?? '')));
    $bpDia = (string) ($vitalSigns['blood_pressure_dia'] ?? ($split[1] !== '' ? $split[1] : ($sys['blood_pressure_dia'] ?? '')));
    if (is_string(old('blood_pressure_sys'))) {
        $bpSys = old('blood_pressure_sys');
    }
    if (is_string(old('blood_pressure_dia'))) {
        $bpDia = old('blood_pressure_dia');
    }
@endphp

@if ($canEditAllergy)
    <form
        action="{{ route('care.systemic.update', $visit) }}"
        method="POST"
        data-autosave
        data-allergy-form
        data-systemic-form
        id="{{ $formId }}"
        class="allergy-manager care-form"
    >
        @csrf
        @method('PUT')
        <p class="text-danger small mb-2" data-sys-warning hidden></p>

        <div class="care-form__block mb-3">
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
                    <input class="form-control vital-example" name="blood_pressure_sys" value="{{ $bpSys }}" inputmode="numeric" placeholder="cth: 120" aria-label="Sistole">
                    <span>/</span>
                    <input class="form-control vital-example" name="blood_pressure_dia" value="{{ $bpDia }}" inputmode="numeric" placeholder="cth: 80" aria-label="Diastole">
                    <span class="text-muted">mmHg</span>
                </div>
            </div>
            <div class="exam-reveal mt-2" data-sys-panel="other" @unless (in_array('other', $conditions, true)) hidden @endunless>
                <x-input name="other_detail" label="Keterangan kondisi lain" :value="$sys['other_detail'] ?? null" />
            </div>
        </div>

        <div class="care-form__block mb-3" data-sys-panel="medication" @unless (in_array('medication', $conditions, true) || ($medicationStatus !== '' && $medicationStatus !== 'not_asked')) hidden @endunless>
            <div class="care-form__title">Obat rutin</div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label" for="{{ $formId }}-medication-status">Status</label>
                    <select name="medication_status" id="{{ $formId }}-medication-status" class="form-select">
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

        <div class="care-form__block mb-3">
            <div class="care-form__title">Kehamilan &amp; riwayat lain</div>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label" for="{{ $formId }}-pregnancy">Status kehamilan</label>
                    <select name="pregnancy_status" id="{{ $formId }}-pregnancy" class="form-select">
                        <option value="">—</option>
                        <option value="pregnant" @selected(($sys['pregnancy_status'] ?? '') === 'pregnant')>Hamil</option>
                        <option value="not_pregnant" @selected(($sys['pregnancy_status'] ?? '') === 'not_pregnant')>Tidak hamil</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="{{ $formId }}-family-history">Riwayat penyakit keluarga</label>
                    <textarea name="family_history" id="{{ $formId }}-family-history" rows="2" class="form-control">{{ old('family_history', $sys['family_history'] ?? '') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="{{ $formId }}-dental-history">Riwayat perawatan gigi</label>
                    <textarea name="dental_history" id="{{ $formId }}-dental-history" rows="2" class="form-control">{{ old('dental_history', $sys['dental_history'] ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <div class="care-form__block mb-2">
            <div class="care-form__title">Alergi &amp; golongan darah</div>
            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-4">
                    <label class="form-label" for="{{ $formId }}-status">Status alergi</label>
                    <select name="allergy_status" id="{{ $formId }}-status" class="form-select" data-allergy-status>
                        @foreach ($examOptions['allergy_statuses'] as $value => $label)
                            <option value="{{ $value }}" @selected($allergyStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="{{ $formId }}-blood-type">Golongan darah</label>
                    <select name="blood_type" id="{{ $formId }}-blood-type" class="form-select">
                        <option value="">—</option>
                        @foreach (['A', 'B', 'AB', 'O'] as $abo)
                            <option value="{{ $abo }}" @selected(($sys['blood_type'] ?? '') === $abo)>{{ $abo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="{{ $formId }}-rhesus">Rhesus</label>
                    <select name="rhesus" id="{{ $formId }}-rhesus" class="form-select">
                        <option value="">—</option>
                        <option value="positive" @selected(($sys['rhesus'] ?? '') === 'positive')>Positif</option>
                        <option value="negative" @selected(($sys['rhesus'] ?? '') === 'negative')>Negatif</option>
                    </select>
                </div>
                <div class="col-md-2 text-md-end" data-allergy-actions @unless ($allergyStatus === 'present') hidden @endunless>
                    <button type="button" class="btn btn-sm btn-primary" data-allergy-open-form data-allergy-index="">
                        <i class="bx bx-plus"></i> Tambah
                    </button>
                </div>
            </div>
            <p class="care-form__hint mb-2">Belum ditanyakan tidak diartikan sebagai tidak ada alergi.</p>
        </div>

        <div class="table-responsive" data-allergy-table-wrap @unless ($allergyStatus === 'present') hidden @endunless>
            <table class="table table-sm table-bordered align-middle mb-0 allergy-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:2.5rem">No</th>
                        <th>Kategori</th>
                        <th>Zat pemicu</th>
                        <th>Reaksi</th>
                        <th>Keparahan</th>
                        <th style="width:6rem"></th>
                    </tr>
                </thead>
                <tbody data-allergy-table-body>
                    @forelse ($allergyItems as $index => $item)
                        <tr data-allergy-row data-index="{{ $index }}">
                            <td class="text-muted" data-allergy-no>{{ $index + 1 }}</td>
                            <td>{{ $categories[$item['category']] ?? ($item['category'] ?: '—') }}</td>
                            <td>{{ $item['detail'] !== '' ? $item['detail'] : '—' }}</td>
                            <td>{{ $item['reaction'] !== '' ? $item['reaction'] : '—' }}</td>
                            <td>{{ $severities[$item['severity']] ?? ($item['severity'] ?: '—') }}</td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-soft-secondary" data-allergy-open-form data-allergy-index="{{ $index }}" title="Ubah">Ubah</button>
                                <button type="button" class="btn btn-sm btn-soft-danger" data-allergy-remove data-allergy-index="{{ $index }}" title="Hapus">×</button>
                                <input type="hidden" name="allergies[{{ $index }}][category]" value="{{ $item['category'] }}" data-allergy-field="category">
                                <input type="hidden" name="allergies[{{ $index }}][type]" value="{{ $item['type'] }}" data-allergy-field="type">
                                <input type="hidden" name="allergies[{{ $index }}][clinical_status]" value="{{ $item['clinical_status'] }}" data-allergy-field="clinical_status">
                                <input type="hidden" name="allergies[{{ $index }}][severity]" value="{{ $item['severity'] }}" data-allergy-field="severity">
                                <input type="hidden" name="allergies[{{ $index }}][detail]" value="{{ $item['detail'] }}" data-allergy-field="detail">
                                <input type="hidden" name="allergies[{{ $index }}][reaction]" value="{{ $item['reaction'] }}" data-allergy-field="reaction">
                                <input type="hidden" name="allergies[{{ $index }}][note]" value="{{ $item['note'] }}" data-allergy-field="note">
                            </td>
                        </tr>
                    @empty
                        <tr data-allergy-empty>
                            <td colspan="6" class="text-muted text-center">Belum ada alergi. Klik “Tambah alergi”.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="modal fade" id="{{ $formId }}-item-modal" tabindex="-1" aria-labelledby="{{ $formId }}-item-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $formId }}-item-modal-label" data-allergy-item-title>Tambah alergi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" data-allergy-item-index value="">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Kategori</label>
                            <select class="form-select" data-allergy-item-field="category">
                                <option value="">—</option>
                                @foreach ($categories as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis</label>
                            <select class="form-select" data-allergy-item-field="type">
                                <option value="">—</option>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status klinis</label>
                            <select class="form-select" data-allergy-item-field="clinical_status">
                                <option value="">—</option>
                                @foreach ($clinicalStatuses as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Keparahan</label>
                            <select class="form-select" data-allergy-item-field="severity">
                                <option value="">—</option>
                                @foreach ($severities as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Zat / bahan pemicu</label>
                            <input type="text" class="form-control" data-allergy-item-field="detail" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reaksi / manifestasi</label>
                            <input type="text" class="form-control" data-allergy-item-field="reaction" maxlength="255">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" rows="2" data-allergy-item-field="note" maxlength="1000"></textarea>
                        </div>
                    </div>
                    <p class="text-danger small mt-2 mb-0" data-allergy-item-error hidden></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" data-allergy-item-save>Simpan alergi</button>
                </div>
            </div>
        </div>
    </div>
@else
    @php
        $readonlySysBits = collect($conditions)
            ->filter(fn ($key) => $key !== 'allergy')
            ->map(fn ($key) => $examOptions['systemic'][$key] ?? $key)
            ->values();
    @endphp
    <p class="mb-2">
        Sistemik:
        <strong>{{ $readonlySysBits->isNotEmpty() ? $readonlySysBits->implode(', ') : ($none ? 'Tidak ada' : 'Belum dicatat') }}</strong>
        · Golongan darah:
        <strong>
            {{ filled($sys['blood_type'] ?? null) ? $sys['blood_type'] : '—' }}{{ ($sys['rhesus'] ?? '') === 'positive' ? '+' : (($sys['rhesus'] ?? '') === 'negative' ? '-' : '') }}
        </strong>
    </p>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>Kategori</th>
                    <th>Zat pemicu</th>
                    <th>Reaksi</th>
                    <th>Keparahan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($allergyItems as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $categories[$item['category']] ?? ($item['category'] ?: '—') }}</td>
                        <td>{{ $item['detail'] !== '' ? $item['detail'] : '—' }}</td>
                        <td>{{ $item['reaction'] !== '' ? $item['reaction'] : '—' }}</td>
                        <td>{{ $severities[$item['severity']] ?? ($item['severity'] ?: '—') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center">{{ \App\Support\Clinical\AllergyHistory::summaryLine($sys) }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

@once
@push('scripts')
<script>
(function () {
    var labels = {
        category: @json($categories),
        severity: @json($severities)
    };
    window.careAllergyLabels = labels;

    function textLabel(map, value) {
        if (!value) return '—';
        return map[value] || value;
    }

    function rowData(row) {
        var data = {};
        row.querySelectorAll('[data-allergy-field]').forEach(function (input) {
            data[input.getAttribute('data-allergy-field')] = input.value || '';
        });
        return data;
    }

    function setRowData(row, data, index) {
        ['category', 'type', 'clinical_status', 'severity', 'detail', 'reaction', 'note'].forEach(function (key) {
            var input = row.querySelector('[data-allergy-field="' + key + '"]');
            if (!input) return;
            input.value = data[key] || '';
            input.name = 'allergies[' + index + '][' + key + ']';
        });
        row.setAttribute('data-index', String(index));
        var no = row.querySelector('[data-allergy-no]');
        if (no) no.textContent = String(index + 1);
        var cells = row.querySelectorAll('td');
        if (cells.length >= 5) {
            cells[1].textContent = textLabel(labels.category, data.category || '');
            cells[2].textContent = data.detail ? data.detail : '—';
            cells[3].textContent = data.reaction ? data.reaction : '—';
            cells[4].textContent = textLabel(labels.severity, data.severity || '');
        }
        row.querySelectorAll('[data-allergy-open-form], [data-allergy-remove]').forEach(function (btn) {
            btn.setAttribute('data-allergy-index', String(index));
        });
    }

    function ensureEmptyState(tbody) {
        var rows = tbody.querySelectorAll('[data-allergy-row]');
        var empty = tbody.querySelector('[data-allergy-empty]');
        if (rows.length === 0) {
            if (!empty) {
                empty = document.createElement('tr');
                empty.setAttribute('data-allergy-empty', '');
                empty.innerHTML = '<td colspan="6" class="text-muted text-center">Belum ada alergi. Klik “Tambah alergi”.</td>';
                tbody.appendChild(empty);
            }
        } else if (empty) {
            empty.remove();
        }
    }

    function reindex(tbody) {
        tbody.querySelectorAll('[data-allergy-row]').forEach(function (row, index) {
            setRowData(row, rowData(row), index);
        });
        ensureEmptyState(tbody);
    }

    function createRow(form, data, index) {
        var tr = document.createElement('tr');
        tr.setAttribute('data-allergy-row', '');
        tr.innerHTML = ''
            + '<td class="text-muted" data-allergy-no></td>'
            + '<td></td><td></td><td></td><td></td>'
            + '<td class="text-nowrap">'
            + '<button type="button" class="btn btn-sm btn-soft-secondary" data-allergy-open-form title="Ubah">Ubah</button> '
            + '<button type="button" class="btn btn-sm btn-soft-danger" data-allergy-remove title="Hapus">×</button>'
            + '<input type="hidden" data-allergy-field="category">'
            + '<input type="hidden" data-allergy-field="type">'
            + '<input type="hidden" data-allergy-field="clinical_status">'
            + '<input type="hidden" data-allergy-field="severity">'
            + '<input type="hidden" data-allergy-field="detail">'
            + '<input type="hidden" data-allergy-field="reaction">'
            + '<input type="hidden" data-allergy-field="note">'
            + '</td>';
        setRowData(tr, data || {}, index);
        return tr;
    }

    function syncSheetSummary(form) {
        var target = document.querySelector('[data-allergy-summary]');
        if (!form) return;
        var status = (form.querySelector('[data-allergy-status]') || {}).value || '';
        var rows = form.querySelectorAll('[data-allergy-row]');
        if (target) {
            if (status === 'unknown') {
                target.textContent = 'Tidak diketahui';
                target.classList.remove('text-muted');
            } else if (status === 'none_confirmed') {
                target.textContent = 'Tidak ada alergi';
                target.classList.remove('text-muted');
            } else if (status === 'present') {
                var bits = [];
                Array.prototype.forEach.call(rows, function (row) {
                    var data = rowData(row);
                    if (!data.category && !data.detail && !data.reaction) return;
                    var label = textLabel(labels.category, data.category);
                    if (label === '—') label = 'Alergi';
                    var parts = [];
                    if (data.detail) parts.push(data.detail);
                    if (data.reaction) parts.push('reaksi: ' + data.reaction);
                    bits.push(label + (parts.length ? ': ' + parts.join(' — ') : ''));
                });
                target.textContent = bits.length ? bits.join(' · ') : 'Ada alergi (detail belum diisi)';
                target.classList.remove('text-muted');
            } else {
                target.textContent = '—';
                target.classList.add('text-muted');
            }
        }
        var mirror = document.querySelector('[data-allergy-status-mirror]');
        if (mirror) mirror.value = status;
    }

    function bindManager(form) {
        if (!form || form.dataset.allergyBound === '1') return;
        form.dataset.allergyBound = '1';
        var tbody = form.querySelector('[data-allergy-table-body]');
        var wrap = form.querySelector('[data-allergy-table-wrap]');
        var actions = form.querySelector('[data-allergy-actions]');
        var status = form.querySelector('[data-allergy-status]');
        var itemModalEl = document.getElementById(form.id + '-item-modal');
        if (!tbody || !itemModalEl || !window.bootstrap) return;
        if (itemModalEl.parentElement !== document.body) {
            document.body.appendChild(itemModalEl);
        }
        var itemModal = bootstrap.Modal.getOrCreateInstance(itemModalEl);
        var editingIndex = null;

        function togglePresent() {
            var present = status && status.value === 'present';
            if (wrap) wrap.hidden = !present;
            if (actions) actions.hidden = !present;
        }

        function openForm(index) {
            editingIndex = (index === '' || index === null || typeof index === 'undefined') ? null : Number(index);
            var title = itemModalEl.querySelector('[data-allergy-item-title]');
            var error = itemModalEl.querySelector('[data-allergy-item-error]');
            if (title) title.textContent = editingIndex === null ? 'Tambah alergi' : 'Ubah alergi';
            if (error) { error.hidden = true; error.textContent = ''; }
            var data = { category: '', type: '', clinical_status: '', severity: '', detail: '', reaction: '', note: '' };
            if (editingIndex !== null) {
                var row = tbody.querySelector('[data-allergy-row][data-index="' + editingIndex + '"]');
                if (row) data = rowData(row);
            }
            itemModalEl.querySelectorAll('[data-allergy-item-field]').forEach(function (field) {
                var key = field.getAttribute('data-allergy-item-field');
                field.value = data[key] || '';
            });
            itemModal.show();
        }

        form.addEventListener('click', function (event) {
            var openBtn = event.target.closest('[data-allergy-open-form]');
            if (openBtn) {
                openForm(openBtn.getAttribute('data-allergy-index'));
                return;
            }
            var removeBtn = event.target.closest('[data-allergy-remove]');
            if (removeBtn) {
                var row = tbody.querySelector('[data-allergy-row][data-index="' + removeBtn.getAttribute('data-allergy-index') + '"]');
                if (row) row.remove();
                reindex(tbody);
                form.dispatchEvent(new Event('change', { bubbles: true }));
                syncSheetSummary(form);
            }
        });

        if (status) {
            status.addEventListener('change', function () {
                togglePresent();
                syncSheetSummary(form);
            });
        }

        var saveBtn = itemModalEl.querySelector('[data-allergy-item-save]');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                var data = {};
                itemModalEl.querySelectorAll('[data-allergy-item-field]').forEach(function (field) {
                    data[field.getAttribute('data-allergy-item-field')] = String(field.value || '').trim();
                });
                var error = itemModalEl.querySelector('[data-allergy-item-error]');
                if (!data.category || !data.detail) {
                    if (error) {
                        error.hidden = false;
                        error.textContent = 'Kategori dan zat pemicu wajib diisi.';
                    }
                    return;
                }
                var empty = tbody.querySelector('[data-allergy-empty]');
                if (empty) empty.remove();
                if (editingIndex === null) {
                    tbody.appendChild(createRow(form, data, tbody.querySelectorAll('[data-allergy-row]').length));
                } else {
                    var row = tbody.querySelector('[data-allergy-row][data-index="' + editingIndex + '"]');
                    if (row) setRowData(row, data, editingIndex);
                }
                reindex(tbody);
                itemModal.hide();
                form.dispatchEvent(new Event('change', { bubbles: true }));
                syncSheetSummary(form);
            });
        }

        togglePresent();
        reindex(tbody);
        syncSheetSummary(form);
    }

    document.querySelectorAll('form[data-allergy-form].allergy-manager').forEach(bindManager);

    function bindSystemic(root) {
        if (!root || root.dataset.sysBound === '1') return;
        root.dataset.sysBound = '1';
        var noneBox = root.querySelector('[data-sys-none]');
        var conditionBoxes = root.querySelectorAll('[data-sys-conditions] input[type="checkbox"]');
        var allergyStatus = root.querySelector('[data-allergy-status]');
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
            var allergyBox = boxFor('allergy');
            if (allergyBox && allergyStatus && allergyStatus.value === 'present') allergyBox.checked = true;
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
                    allergyStatus.dispatchEvent(new Event('change', { bubbles: true }));
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
    }

    document.querySelectorAll('form[data-systemic-form].allergy-manager').forEach(bindSystemic);
})();
</script>
@endpush
@endonce
