@extends('layouts.app')

@section('title', 'Pelayanan '.$visit->patient?->name)

@php
    $carePartials = [
        'soap' => 'clinical.care.partials.soap-sheet',
        'odontogram' => 'clinical.care.partials.odontogram',
        'tindakan' => 'clinical.care.partials.plan-procedures',
        'resep' => 'clinical.care.partials.plan-prescriptions',
        'rujukan' => 'clinical.care.partials.plan-referrals',
        'ringkasan' => 'clinical.care.partials.discharge',
    ];
@endphp

@section('content')
    <x-page-header title="Pelayanan" :breadcrumb="['Pelayanan' => route('examinations.initial'), $visit->patient?->name => null]" />
    <x-alert />

    <div class="care-workspace" data-section="{{ $section }}" data-writable="{{ $writable ? '1' : '0' }}">
        @include('clinical.care.partials.header')
        @include('clinical.care.partials.assessment-panel')

        <div class="care-panel">
            <div class="care-nav-bar">
                <ul class="nav nav-tabs nav-tabs-custom care-nav" role="tablist">
                    @foreach ($sections as $key => $item)
                        <li class="nav-item">
                            <a
                                class="nav-link {{ $section === $key ? 'active' : '' }} {{ ! empty($progress[$key]) ? 'is-done' : '' }}"
                                href="{{ route('care.show', array_filter(['visit' => $visit, 'tab' => $key, 'from' => request('from') === 'pelayanan' ? 'pelayanan' : null])) }}"
                                data-care-section="{{ $key }}"
                                role="tab"
                                aria-selected="{{ $section === $key ? 'true' : 'false' }}"
                            >
                                <span class="care-flow-link__mark">{{ ! empty($progress[$key]) ? '✓' : '○' }}</span>
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="care-save-status" data-care-save>Siap diisi</div>
            </div>

            <div class="care-tab-content">
                @foreach ($sections as $key => $item)
                    @continue(! isset($carePartials[$key]))
                    <div class="care-pane-wrap" data-care-pane="{{ $key }}" role="tabpanel" @if ($section !== $key) hidden @endif>
                        @include($carePartials[$key])
                    </div>
                @endforeach
            </div>
        </div>

        @if ($visit->status->isOpen() && auth()->user()?->can('queue.manage'))
            @php
                $finishProcedures = $visit->procedureRecords;
                $finishPrescriptions = $visit->prescriptions;
                $finishRxItems = $finishPrescriptions->flatMap(fn ($rx) => $rx->items);
                $finishDraftRx = $finishPrescriptions->filter(fn ($rx) => $rx->isDraft());
                $assessmentOpen = ($record?->assessment_status ?? null) !== \App\Enums\AssessmentStatus::Completed;
                $finalizeWithVisit = auth()->user()?->can('medical_record.update') && (! $recordFinal || $assessmentOpen);
            @endphp
            <x-modal id="care-complete-modal" title="Verifikasi &amp; selesaikan kunjungan" size="modal-lg" class="care-modal">
                <p class="text-muted mb-3">Periksa kembali tindakan dan obat yang diberikan sebelum menyelesaikan pelayanan.@if ($finalizeWithVisit) Menyelesaikan kunjungan juga memfinalisasi asesmen dan rekam medis. Koreksi isi hanya 2×24 jam setelah ini.@endif</p>

                <div class="care-finish-review mb-3">
                    <h6 class="mb-2">Tindakan ({{ $finishProcedures->count() }})</h6>
                    @if ($finishProcedures->isEmpty())
                        <p class="text-muted small mb-0">Tidak ada tindakan dicatat.</p>
                    @else
                        <x-table>
                            <thead>
                                <tr>
                                    <th>Tindakan</th>
                                    <th>Gigi</th>
                                    <th>Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($finishProcedures as $item)
                                    <tr>
                                        <td>{{ $item->procedure?->name ?: '-' }}@if ($item->icd9_code) <span class="text-muted">({{ $item->icd9_code }})</span>@endif</td>
                                        <td>{{ $item->tooth_number ?: '-' }}</td>
                                        <td>{{ $item->quantity }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table>
                    @endif
                </div>

                <div class="care-finish-review mb-3">
                    <h6 class="mb-2">Obat / resep ({{ $finishRxItems->count() }})</h6>
                    @if ($finishRxItems->isEmpty())
                        <p class="text-muted small mb-0">Tidak ada obat dicatat.</p>
                    @else
                        <x-table>
                            <thead>
                                <tr>
                                    <th>Obat</th>
                                    <th>Dosis</th>
                                    <th>Frekuensi</th>
                                    <th>Aturan pakai</th>
                                    <th>Qty</th>
                                    <th>Status resep</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($finishPrescriptions as $prescription)
                                    @foreach ($prescription->items as $item)
                                        <tr>
                                            <td>{{ $item->label() }}</td>
                                            <td>{{ $item->dosage ?: '-' }}</td>
                                            <td>{{ $item->frequency ?: '-' }}</td>
                                            <td>{{ $item->duration ?: '-' }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td><span class="{{ $prescription->status->badgeClass() }}">{{ $prescription->status->label() }}</span></td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </x-table>
                    @endif
                    @if ($finishDraftRx->isNotEmpty())
                        <div class="alert alert-warning mt-2 mb-0 py-2">
                            Ada {{ $finishDraftRx->count() }} resep masih <strong>draft</strong> (belum dikirim ke farmasi).
                        </div>
                    @endif
                </div>

                <form action="{{ route('care.complete', $visit) }}" method="POST" id="care-complete-form">
                    @csrf
                    <label class="care-complete-confirm" for="care-complete-confirm">
                        <input class="form-check-input" type="checkbox" value="1" id="care-complete-confirm" required>
                        <span>Saya sudah memverifikasi tindakan dan obat di atas, serta menyetujui penyelesaian kunjungan@if ($finalizeWithVisit) dan finalisasi asesmen serta rekam medis@endif ini.</span>
                    </label>
                </form>

                <x-slot:footer>
                    <x-button variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                    <x-button type="submit" variant="success" icon="bx bx-check" form="care-complete-form" id="care-complete-submit" class="btn-lg" disabled>
                        @if ($finalizeWithVisit)
                            Ya, Selesaikan &amp; Finalisasi
                        @else
                            Ya, Selesaikan Kunjungan
                        @endif
                    </x-button>
                </x-slot:footer>
            </x-modal>
        @endif
    </div>
@endsection

@push('styles')
    <link href="{{ theme('libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css">
    <style>
        .care-complete-confirm {
            display: flex;
            align-items: flex-start;
            gap: 0.9rem;
            margin: 0.25rem 0 0;
            padding: 1rem 1.15rem;
            border: 2px solid #34c38f;
            border-radius: 10px;
            background: #f4fbf8;
            cursor: pointer;
            font-size: 1.05rem;
            font-weight: 600;
            line-height: 1.45;
            color: #1a3c2e;
        }

        .care-complete-confirm .form-check-input {
            float: none;
            width: 1.65rem;
            height: 1.65rem;
            margin: 0.12rem 0 0;
            flex-shrink: 0;
            cursor: pointer;
        }

        .care-complete-confirm:has(.form-check-input:checked) {
            background: #e5f7ee;
            box-shadow: 0 0 0 3px rgba(52, 195, 143, 0.2);
        }

        #care-complete-modal .modal-footer #care-complete-submit {
            min-width: 16rem;
            font-weight: 600;
        }

        .care-save-status {
            white-space: normal;
            max-width: 18rem;
            text-align: right;
            line-height: 1.35;
        }

        .care-save-status.is-error {
            color: #f46a6a;
        }

        .exam-readonly__wide {
            grid-column: 1 / -1;
        }

        .ohis-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem 1rem;
            align-items: end;
        }

        .ohis-result {
            min-height: 100%;
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            background: #f8f9fa;
        }

        @media (max-width: 767.98px) {
            .ohis-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .ohis-result__value {
            font-size: 1.05rem;
            font-weight: 600;
            color: #495057;
        }

        .ohis-result__value.is-baik { color: #34c38f; }
        .ohis-result__value.is-sedang { color: #f1b44c; }
        .ohis-result__value.is-buruk { color: #f46a6a; }

        .instruksi-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.45rem;
        }

        @media (max-width: 767.98px) {
            .instruksi-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@push('scripts')
<script src="{{ theme('libs/select2/js/select2.min.js') }}"></script>
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';
    var statusEl = document.querySelector('[data-care-save]');
    var timers = new WeakMap();
    var pending = new WeakMap();
    var $ = window.jQuery;

    function setStatus(text, failed) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.classList.toggle('is-error', !!failed);
    }

    function validationMessage(data) {
        if (data && data.errors) {
            var keys = Object.keys(data.errors);
            for (var i = 0; i < keys.length; i++) {
                var list = data.errors[keys[i]];
                if (list && list[0]) return list[0];
            }
        }
        if (data && data.message && data.message !== 'The given data was invalid.') return data.message;
        return 'Gagal menyimpan';
    }

    function markDone(section, done) {
        var link = document.querySelector('[data-care-section="' + section + '"]');
        if (!link) return;
        var complete = done !== false;
        link.classList.toggle('is-done', complete);
        var mark = link.querySelector('.care-flow-link__mark');
        if (mark) mark.textContent = complete ? '✓' : '○';
    }

    window.careSave = function (form) {
        if (!form) return Promise.resolve();
        if (form.dataset.busy === '1') {
            pending.set(form, true);
            return Promise.resolve();
        }
        form.dataset.busy = '1';
        setStatus('Menyimpan…');
        var body = new FormData(form);
        return fetch(form.action, {
            method: (form.getAttribute('method') || 'POST').toUpperCase() === 'GET' ? 'GET' : 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    var message = validationMessage(data);
                    var error = new Error(message);
                    error.careMessage = message;
                    throw error;
                }
                return data;
            });
        }).then(function (data) {
            setStatus('✓ Tersimpan');
            if (data && data.section) markDone(data.section, data.done);
            if (form.hasAttribute('data-exam-form')) syncExamSummary(form);
            if (form.hasAttribute('data-plan-form')) syncPlanSummary(form);
            if (form.hasAttribute('data-allergy-form')) syncAllergySummary(form);
            if (form.id === 'assessment-evaluasi-form') syncPlanReview();
            return data;
        }).catch(function (error) {
            setStatus(error && error.careMessage ? error.careMessage : 'Gagal menyimpan', true);
        }).finally(function () {
            form.dataset.busy = '0';
            if (pending.get(form)) {
                pending.delete(form);
                window.careSave(form);
            }
        });
    };

    function fieldText(form, name) {
        var input = form.querySelector('[name="' + name + '"]');
        return input ? String(input.value || '').trim() : '';
    }

    var examNameLabels = {
        extraoral: 'Ekstraoral',
        intraoral: 'Intraoral',
        occlusion: 'Occlusi',
        gingiva: 'Gingiva',
        mucosa: 'Mukosa',
        palate: 'Palatum',
        torus_palatinus: 'Torus palatinus',
        torus_mandibularis: 'Torus mandibularis',
        diastema: 'Diastema',
        supernumerary: 'Supernumerary',
        anomaly: 'Anomali'
    };

    function choiceText(input) {
        var choice = input.closest('label');
        var span = choice ? choice.querySelector('span') : null;
        return span ? span.textContent.trim() : input.value;
    }

    function syncExamSummary(form) {
        var target = document.querySelector('[data-exam-summary]');
        if (!target || !form) return;
        var bits = [];
        form.querySelectorAll('.exam-field input:checked').forEach(function (checked) {
            var label = examNameLabels[checked.name];
            if (!label) return;
            var text = choiceText(checked);
            var extra = '';
            if (checked.name === 'extraoral' || checked.name === 'intraoral') extra = fieldText(form, checked.name + '_note');
            if (checked.name === 'diastema') extra = fieldText(form, 'diastema_elements');
            if (checked.name === 'supernumerary') extra = fieldText(form, 'supernumerary_teeth');
            if (checked.name === 'anomaly') extra = fieldText(form, 'anomaly_description');
            if (extra) text += ' — ' + extra;
            bits.push(label + ': ' + text);
        });
        var habits = fieldText(form, 'habits');
        if (habits) bits.push('Kebiasaan: ' + habits);
        if (target.getAttribute('data-exam-extra')) bits.push(target.getAttribute('data-exam-extra'));
        var debris = fieldText(form, 'ohis[debris_index]');
        var calculus = fieldText(form, 'ohis[calculus_index]');
        if (debris && calculus) bits.push('OHIS: DI ' + debris + ' · CI ' + calculus);
        target.textContent = bits.length ? bits.join('; ') : '—';
    }

    function syncAllergySummary(form) {
        var target = document.querySelector('[data-allergy-summary]');
        if (!target || !form) return;
        var status = fieldText(form, 'allergy_status');
        var category = fieldText(form, 'allergy_category');
        var detail = fieldText(form, 'allergy_detail');
        var buckets = [
            ['food', 'Alergi Makanan'],
            ['environment', 'Alergi Udara'],
            ['medication', 'Alergi Obat'],
            ['biologic', 'Alergi lainnya']
        ];
        if (status === 'unknown') {
            target.textContent = 'Tidak diketahui';
            return;
        }
        if (status !== 'none_confirmed' && status !== 'present') {
            target.textContent = 'Belum ditanyakan';
            return;
        }
        target.textContent = buckets.map(function (bucket) {
            if (status === 'present' && category === bucket[0]) {
                return bucket[1] + ' : Ada' + (detail ? ' — ' + detail : '');
            }
            return bucket[1] + ' : Tidak ada';
        }).join(', ');
    }

    function selectedLabel(select) {
        if (!select || !select.value) return '';
        var option = select.options[select.selectedIndex];
        return option ? option.text.trim() : '';
    }

    function syncPlanReview() {
        var target = document.querySelector('[data-plan-review]');
        if (!target) return;
        var bits = [];
        var decision = selectedLabel(document.getElementById('plan_decision'));
        var change = selectedLabel(document.getElementById('indicator-plan'));
        var decisionField = document.getElementById('plan_decision');
        if (decision && decision !== '—') bits.push('Keputusan: ' + decision + '.');
        if (decisionField && decisionField.value === 'control') {
            var controlDate = document.getElementById('follow-up-control-date');
            if (controlDate && controlDate.value) {
                var parts = controlDate.value.split('-');
                bits.push('Tanggal kontrol: ' + (parts.length === 3 ? parts[2] + '-' + parts[1] + '-' + parts[0] : controlDate.value) + '.');
            }
        }
        if (change && change !== '—') bits.push('Dibanding kunjungan lalu: ' + change + '.');
        target.hidden = bits.length === 0;
        target.textContent = bits.join(' ');
    }

    function syncPlanSummary(form) {
        var target = document.querySelector('[data-plan-summary]');
        if (!target || !form) return;
        var bits = [];
        form.querySelectorAll('input[name="plan_instructions[items][]"]:checked').forEach(function (input) {
            var label = input.closest('label');
            var span = label ? label.querySelector('span') : null;
            var text = span ? span.textContent.trim() : input.value;
            if (text) bits.push(text);
        });
        var goal = fieldText(form, 'plan_instructions[goal]');
        var extra = fieldText(form, 'plan_instructions[extra]');
        if (goal) bits.push('Tujuan: ' + goal);
        if (extra) bits.push(extra);
        target.textContent = bits.length ? bits.join('; ') : '—';
    }

    function bindSheetSync(form, sync) {
        if (!form) return;
        form.addEventListener('change', function () { sync(form); });
        form.addEventListener('input', function () { sync(form); });
    }

    var examModal = document.getElementById('pemeriksaan-modal');
    if (examModal) {
        var examForm = examModal.querySelector('[data-exam-form]');
        bindSheetSync(examForm, syncExamSummary);
        examModal.addEventListener('hidden.bs.modal', function () {
            if (examForm) syncExamSummary(examForm);
        });
    }

    document.querySelectorAll('[data-allergy-form]').forEach(function (form) {
        bindSheetSync(form, syncAllergySummary);
    });
    var allergyModal = document.getElementById('soap-allergy-modal');
    if (allergyModal) {
        allergyModal.addEventListener('hidden.bs.modal', function () {
            var form = allergyModal.querySelector('[data-allergy-form]');
            if (form) syncAllergySummary(form);
        });
    }

    document.querySelectorAll('[data-plan-form]').forEach(function (form) {
        syncPlanSummary(form);
        form.addEventListener('change', function () { syncPlanSummary(form); });
        form.addEventListener('input', function () { syncPlanSummary(form); });
    });
    var planModal = document.getElementById('soap-plan-modal');
    if (planModal) {
        planModal.addEventListener('hidden.bs.modal', function () {
            var form = planModal.querySelector('[data-plan-form]');
            if (form) syncPlanSummary(form);
            syncPlanReview();
        });
    }
    function syncControlDate(clearWhenHidden) {
        var decision = document.getElementById('plan_decision');
        var wrap = document.querySelector('[data-control-date-wrap]');
        var input = document.getElementById('follow-up-control-date');
        if (!decision || !wrap) return;
        var show = decision.value === 'control';
        wrap.hidden = !show;
        if (!show && clearWhenHidden && input) input.value = '';
    }

    syncControlDate(false);
    var planDecision = document.getElementById('plan_decision');
    if (planDecision) {
        planDecision.addEventListener('change', function () {
            syncControlDate(true);
            syncPlanReview();
        });
    }
    var controlDateInput = document.getElementById('follow-up-control-date');
    if (controlDateInput) {
        controlDateInput.addEventListener('change', syncPlanReview);
        controlDateInput.addEventListener('input', syncPlanReview);
    }
    ['plan_decision', 'indicator-plan'].forEach(function (id) {
        var field = document.getElementById(id);
        if (!field || id === 'plan_decision') return;
        field.addEventListener('change', syncPlanReview);
    });
    window.careSetStatus = setStatus;
    window.careValidationMessage = validationMessage;

    function showTab(key) {
        document.querySelectorAll('[data-care-pane]').forEach(function (pane) {
            pane.hidden = pane.dataset.carePane !== key;
        });
        document.querySelectorAll('[data-care-section]').forEach(function (link) {
            var on = link.dataset.careSection === key;
            link.classList.toggle('active', on);
            link.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        var url = new URL(window.location.href);
        url.searchParams.set('tab', key);
        history.replaceState({ tab: key }, '', url);
        document.dispatchEvent(new CustomEvent('care:tab', { detail: key }));
    }

    document.querySelectorAll('[data-care-section]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            showTab(link.dataset.careSection);
        });
    });

    window.addEventListener('popstate', function (e) {
        var key = (e.state && e.state.tab) || new URL(window.location.href).searchParams.get('tab');
        if (key) showTab(key);
    });

    document.querySelectorAll('form[data-autosave]').forEach(function (form) {
        function queue() {
            clearTimeout(timers.get(form));
            timers.set(form, setTimeout(function () { window.careSave(form); }, 600));
        }
        form.addEventListener('change', queue);
        form.addEventListener('input', queue);
        if (form.id) {
            document.querySelectorAll('[form="' + form.id + '"]').forEach(function (field) {
                field.addEventListener('change', queue);
                field.addEventListener('input', queue);
            });
        }
    });

    document.querySelectorAll('[data-reveal-group]').forEach(function (group) {
        var key = group.getAttribute('data-reveal-group');
        var pane = group.closest('form, .care-tab-content, .care-section') || document;
        var target = pane.querySelector('[data-reveal="' + key + '"]');
        if (!target) return;
        function sync() {
            var selected = group.querySelector('input:checked');
            target.hidden = !(selected && selected.value === (group.dataset.revealWhen || 'present'));
        }
        group.addEventListener('change', sync);
        sync();
    });

    document.querySelectorAll('[data-toggle-panel]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.querySelector(btn.getAttribute('data-toggle-panel'));
            if (!target) return;
            target.hidden = !target.hidden;
            if (!target.hidden && $) {
                $(target).find('.select2-container').css('width', '100%');
            }
        });
    });

    function renderResults(box, items) {
        var list = box.querySelector('[data-typeahead-results]');
        if (!list) return;
        list.innerHTML = '';
        if (!items.length) {
            list.hidden = true;
            return;
        }
        items.forEach(function (item) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'care-typeahead__item';
            button.textContent = (item.code ? item.code + ' — ' : '') + (item.label || item.description || '');
            button.addEventListener('click', function () {
                var form = box.closest('form');
                var q = box.querySelector('[data-typeahead-q]');
                var id = box.querySelector('[data-typeahead-id]');
                if (q) q.value = button.textContent;
                if (id) {
                    id.value = item.id || '';
                    id.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (form) {
                    var code = form.querySelector('[name="code"]');
                    var desc = form.querySelector('[name="description"]');
                    if (code && item.code) code.value = item.code;
                    if (desc && (item.description || item.label)) desc.value = item.description || item.label;
                }
                list.hidden = true;
            });
            list.appendChild(button);
        });
        list.hidden = false;
    }

    document.querySelectorAll('[data-typeahead]').forEach(function (box) {
        var input = box.querySelector('[data-typeahead-q]');
        if (!input) return;
        var url = box.dataset.typeaheadUrl;
        var local = [];
        try { local = JSON.parse(box.dataset.typeaheadItems || '[]'); } catch (e) { local = []; }
        var timer;
        function search(q) {
            if (url) {
                fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (res) { return res.json(); }).then(function (items) {
                    renderResults(box, items || []);
                }).catch(function () {});
                return;
            }
            var needle = q.toLowerCase();
            renderResults(box, local.filter(function (item) {
                return !needle || (item.label || '').toLowerCase().indexOf(needle) !== -1 || String(item.code || '').toLowerCase().indexOf(needle) !== -1;
            }).slice(0, 12));
        }
        input.addEventListener('input', function () {
            var id = box.querySelector('[data-typeahead-id]');
            if (id) id.value = '';
            clearTimeout(timer);
            timer = setTimeout(function () { search(input.value); }, 180);
        });
        input.addEventListener('focus', function () {
            if (input.value || local.length) search(input.value);
        });
        var form = box.closest('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                var id = box.querySelector('[data-typeahead-id]');
                if (id && id.hasAttribute('required') && !id.value) {
                    e.preventDefault();
                    input.focus();
                }
            });
        }
        document.addEventListener('click', function (e) {
            if (!box.contains(e.target)) {
                var list = box.querySelector('[data-typeahead-results]');
                if (list) list.hidden = true;
            }
        });
    });

    document.querySelectorAll('.care-modal').forEach(function (modal) {
        document.body.appendChild(modal);
    });

    document.addEventListener('show.bs.modal', function (event) {
        var modal = event.target;
        if (!modal || !modal.classList || !modal.classList.contains('modal')) return;
        var zIndex = 1055 + (document.querySelectorAll('.modal.show').length * 20);
        modal.style.zIndex = String(zIndex);
        setTimeout(function () {
            var backdrops = document.querySelectorAll('.modal-backdrop');
            if (!backdrops.length) return;
            backdrops[backdrops.length - 1].style.zIndex = String(zIndex - 10);
        }, 0);
    });

    function selectDropdownParent($el) {
        var modal = $el.closest('.modal');
        return modal.length ? modal : $(document.body);
    }

    function initToothSelects(container) {
        $(container).find('[data-odo-tooth-select]').each(function () {
            var $el = $(this);
            if ($el.data('select2')) return;
            $el.select2({
                width: '100%',
                placeholder: 'Pilih dari temuan odontogram',
                allowClear: true,
                dropdownParent: selectDropdownParent($el),
                language: {
                    noResults: function () { return 'Belum ada temuan odontogram'; }
                }
            });
        });
    }

    function initIcd9Selects(container) {
        $(container).find('[data-icd9-select]').each(function () {
            var el = this;
            var $el = $(el);
            if ($el.data('select2')) return;
            $el.select2({
                width: '100%',
                placeholder: 'Ketik kode atau nama, mis. filling / 23.2',
                allowClear: true,
                minimumInputLength: 1,
                dropdownParent: selectDropdownParent($el),
                ajax: {
                    url: el.getAttribute('data-icd9-url'),
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '', page: params.page || 1 };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.results || [],
                            pagination: { more: !!(data.pagination && data.pagination.more) }
                        };
                    },
                    cache: true
                },
                language: {
                    noResults: function () { return 'ICD-9-CM tidak ditemukan'; },
                    searching: function () { return 'Mencari…'; },
                    loadingMore: function () { return 'Memuat lainnya…'; }
                }
            });
        });
    }

    function initIcdSelects(container) {
        $(container).find('[data-icd-select]').each(function () {
            var icd = this;
            var $icd = $(icd);
            if ($icd.data('select2')) return;
            var icdForm = icd.closest('form');
            var codeInput = icdForm ? icdForm.querySelector('[name="code"]') : null;
            var descInput = icdForm ? icdForm.querySelector('[name="description"]') : null;

            $icd.select2({
                width: '100%',
                placeholder: 'Ketik kode atau nama, mis. karies / K02.1',
                allowClear: true,
                minimumInputLength: 1,
                dropdownParent: selectDropdownParent($icd),
                ajax: {
                    url: icd.getAttribute('data-icd-url'),
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '', page: params.page || 1 };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.results || [],
                            pagination: { more: !!(data.pagination && data.pagination.more) }
                        };
                    },
                    cache: true
                },
                language: {
                    noResults: function () { return 'ICD-10 tidak ditemukan'; },
                    searching: function () { return 'Mencari…'; },
                    loadingMore: function () { return 'Memuat lainnya…'; }
                }
            });

            $icd.on('select2:select', function (e) {
                var data = e.params.data || {};
                if (codeInput) codeInput.value = data.code || data.id || '';
                if (descInput) descInput.value = data.description || '';
            });
            $icd.on('select2:clear', function () {
                if (codeInput) codeInput.value = '';
                if (descInput) descInput.value = '';
            });

            if (icdForm && icdForm.dataset.dxBound !== '1') {
                icdForm.dataset.dxBound = '1';
                icdForm.addEventListener('submit', function (e) {
                    if (icdForm.closest('#soap-icd')) e.preventDefault();
                    var manual = icdForm.querySelector('[data-dx-manual]');
                    if (manual && manual.checked) {
                        var manualCode = icdForm.querySelector('[data-dx-manual-code]');
                        var manualDesc = icdForm.querySelector('[data-dx-manual-desc]');
                        if (codeInput) codeInput.value = manualCode ? manualCode.value.trim() : '';
                        if (descInput) descInput.value = manualDesc ? manualDesc.value.trim() : '';
                    }
                    if (descInput && descInput.value) return;
                    e.preventDefault();
                    if (manual && manual.checked) {
                        var focusDesc = icdForm.querySelector('[data-dx-manual-desc]');
                        if (focusDesc) focusDesc.focus();
                        return;
                    }
                    $icd.select2('open');
                });
            }
        });
    }
    function toothOptionText(tooth) {
        var text = String(tooth.number) + ' — ' + (tooth.status_label || tooth.status || '');
        if (tooth.surface_labels) text += ' (' + tooth.surface_labels + ')';
        return text;
    }

    window.careSyncOdoToothOption = function (tooth) {
        if (!$ || !$.fn || !$.fn.select2 || !tooth) return;
        var val = String(tooth.number);
        $('[data-odo-tooth-select]').each(function () {
            var $el = $(this);
            var $opt = $el.find('option[value="' + val + '"]');
            if (tooth.status === 'healthy') {
                if (String($el.val()) === val) $el.val(null).trigger('change');
                $opt.remove();
                return;
            }
            if ($opt.length) {
                $opt.text(toothOptionText(tooth));
            } else {
                $el.append(new Option(toothOptionText(tooth), val, false, false));
            }
        });
    };

    document.addEventListener('change', function (event) {
        var toggle = event.target;
        if (!toggle || !toggle.matches || !toggle.matches('[data-dx-manual]')) return;
        var scope = toggle.closest('form') || toggle.closest('[data-soap-icd-row]');
        if (!scope) return;
        scope.querySelectorAll('[data-dx-manual-box]').forEach(function (panel) {
            panel.hidden = !toggle.checked;
        });
    });

    function bindSoapIcd(row) {
        if (!row || row.dataset.bound === '1') return;
        row.dataset.bound = '1';
        initIcdSelects(row);
        var select = row.querySelector('[data-icd-select]');
        var root = document.getElementById('soap-icd');
        if (!select || !root || !$) return;

        var saving = false;

        function save(data) {
            var description = (data && data.description) || '';
            if (!description || saving) return;
            saving = true;
            setStatus('Menyimpan…');
            var body = new FormData();
            body.append('_token', token);
            body.append('code', (data && (data.code || data.id)) || '');
            body.append('description', description);
            body.append('kind', 'clinical');
            body.append('rank', 'secondary');
            fetch(root.dataset.store, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (payload) {
                    if (!res.ok) throw new Error(validationMessage(payload));
                    return payload;
                });
            }).then(function () {
                saving = false;
                setStatus('✓ Tersimpan');
            }).catch(function (error) {
                saving = false;
                setStatus(error && error.message ? error.message : 'Gagal menyimpan', true);
            });
        }

        $(select).on('select2:select', function (e) {
            save((e.params && e.params.data) || {});
        });
    }

    function bindOneIcd(select) {
        if (!select || !$ || !$.fn || !$.fn.select2) return;
        var $select = $(select);
        if ($select.data('select2')) return;
        $select.select2({
            width: '100%',
            placeholder: 'Ketik kode atau nama, mis. karies / K02.1',
            allowClear: false,
            minimumInputLength: 1,
            dropdownParent: selectDropdownParent($select),
            ajax: {
                url: select.getAttribute('data-search'),
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '', page: params.page || 1 };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.results || [],
                        pagination: { more: !!(data.pagination && data.pagination.more) }
                    };
                },
                cache: true
            },
            language: {
                noResults: function () { return 'ICD-10 tidak ditemukan'; },
                searching: function () { return 'Mencari…'; }
            }
        });
        $select.on('select2:select', function (e) {
            var data = (e.params && e.params.data) || {};
            var code = data.code || data.id || '';
            var description = data.description || '';
            if (!description || String(code).indexOf('id:') === 0) return;
            if (select.dataset.saving === '1') return;
            select.dataset.saving = '1';
            setStatus('Menyimpan…');
            var body = new FormData();
            body.append('_token', token);
            body.append('code', code);
            body.append('description', description);
            body.append('kind', 'clinical');
            body.append('rank', 'secondary');
            body.append('single', '1');
            var updateUrl = select.getAttribute('data-update');
            if (updateUrl) body.append('_method', 'PUT');
            fetch(updateUrl || select.dataset.store, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (payload) {
                    if (!res.ok) throw new Error(validationMessage(payload));
                    return payload || {};
                });
            }).then(function (payload) {
                if (payload && payload.update_url) select.setAttribute('data-update', payload.update_url);
                if (payload && payload.destroy_url) select.setAttribute('data-destroy', payload.destroy_url);
                var resetButton = select.parentElement ? select.parentElement.querySelector('[data-icd-reset]') : null;
                if (resetButton) resetButton.hidden = false;
                select.dataset.saving = '0';
                setStatus('✓ Tersimpan');
            }).catch(function (error) {
                select.dataset.saving = '0';
                setStatus(error && error.message ? error.message : 'Gagal menyimpan', true);
            });
        });
        var resetButton = select.parentElement ? select.parentElement.querySelector('[data-icd-reset]') : null;
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                if (select.dataset.saving === '1') return;
                var destroyUrl = select.getAttribute('data-destroy');
                function clearDiagnosis() {
                    $select.val(null).trigger('change');
                    select.removeAttribute('data-update');
                    select.removeAttribute('data-destroy');
                    resetButton.hidden = true;
                    select.dataset.saving = '0';
                    setStatus('✓ Tersimpan');
                }
                if (!destroyUrl) {
                    clearDiagnosis();
                    return;
                }
                select.dataset.saving = '1';
                setStatus('Menyimpan…');
                var body = new FormData();
                body.append('_token', token);
                body.append('_method', 'DELETE');
                fetch(destroyUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (payload) {
                        if (!res.ok) throw new Error(validationMessage(payload));
                    });
                }).then(clearDiagnosis).catch(function (error) {
                    select.dataset.saving = '0';
                    setStatus(error && error.message ? error.message : 'Gagal menyimpan', true);
                });
            });
        }
    }

    if ($ && $.fn && $.fn.select2) {
        var workspace = document.querySelector('.care-workspace');
        if (workspace) {
            initToothSelects(workspace);
            initIcdSelects(workspace);
            initIcd9Selects(workspace);
            document.querySelectorAll('[data-soap-icd-row]').forEach(bindSoapIcd);
            document.querySelectorAll('[data-icd-one]').forEach(bindOneIcd);
        }

        document.querySelectorAll('.care-modal').forEach(function (modal) {
            modal.addEventListener('shown.bs.modal', function () {
                initToothSelects(modal);
                initIcdSelects(modal);
                initIcd9Selects(modal);
                $(modal).find('.select2-container').css('width', '100%');
            });
            if (modal.hasAttribute('data-show-on-load') && window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    }

    document.addEventListener('care:tab', function () {
        if ($) $('.care-workspace .select2-container').css('width', '100%');
    });

    (function initCareCompleteConfirm() {
        var modal = document.getElementById('care-complete-modal');
        var confirmCb = document.getElementById('care-complete-confirm');
        var submitBtn = document.getElementById('care-complete-submit');
        var form = document.getElementById('care-complete-form');
        if (!modal || !confirmCb || !submitBtn || !form) return;

        function syncSubmit() {
            submitBtn.disabled = !confirmCb.checked;
        }

        confirmCb.addEventListener('change', syncSubmit);
        modal.addEventListener('hidden.bs.modal', function () {
            confirmCb.checked = false;
            syncSubmit();
        });
        form.addEventListener('submit', function () {
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-busy', '1');
        });
        syncSubmit();
    })();
})();
</script>
@endpush
