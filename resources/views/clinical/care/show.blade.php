@extends('layouts.app')

@section('title', 'Pelayanan '.$visit->patient?->name)

@php
    $carePartials = [
        'soap' => 'clinical.care.partials.soap-sheet',
        'odontogram' => 'clinical.care.partials.odontogram',
        'tindakan' => 'clinical.care.partials.plan-procedures',
        'resep' => 'clinical.care.partials.plan-prescriptions',
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

        @if ($visit->status->isOpen() && auth()->user()?->isDentist() && auth()->user()?->can('queue.manage'))
            @php
                $finishProcedures = $visit->procedureRecords;
                $finishDraftRx = $visit->prescriptions->filter(fn ($rx) => $rx->isDraft());
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

                @include('clinical.care.partials.care-finish-rx', ['visit' => $visit])

                <form action="{{ route('care.complete', $visit) }}" method="POST" id="care-complete-form" data-finalize="{{ $finalizeWithVisit ? '1' : '0' }}">
                    @csrf
                    <label class="care-complete-confirm" for="care-complete-confirm">
                        <input class="form-check-input" type="checkbox" value="1" id="care-complete-confirm" required>
                        <span data-care-finish-confirm-text>
                            Saya sudah memverifikasi tindakan dan obat di atas, serta menyetujui penyelesaian kunjungan
                            @if ($finishDraftRx->isNotEmpty())
                                dan pengiriman resep ke farmasi
                            @endif
                            @if ($finalizeWithVisit)
                                serta finalisasi asesmen dan rekam medis
                            @endif
                            ini.
                        </span>
                    </label>
                </form>

                <x-slot:footer>
                    <x-button variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                    <x-button type="submit" variant="success" icon="bx bx-check" form="care-complete-form" id="care-complete-submit" class="btn-lg" disabled>
                        <span data-care-finish-submit-text>
                            @if ($finalizeWithVisit && $finishDraftRx->isNotEmpty())
                                Ya, Kirim Resep &amp; Finalisasi
                            @elseif ($finishDraftRx->isNotEmpty())
                                Ya, Kirim Resep &amp; Selesaikan
                            @elseif ($finalizeWithVisit)
                                Ya, Selesaikan &amp; Finalisasi
                            @else
                                Ya, Selesaikan Kunjungan
                            @endif
                        </span>
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

        .soap-plan .instruksi-grid {
            grid-template-columns: 1fr;
        }

        @media (max-width: 767.98px) {
            .instruksi-grid {
                grid-template-columns: 1fr;
            }
        }

        .vital-example::placeholder,
        .soap-vital-grid input::placeholder,
        .vital-grid input::placeholder,
        .vital-inline input::placeholder {
            color: #a7b1be;
            opacity: 0.55;
            font-weight: 400;
        }

        .soap-vital-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(10.5rem, 1fr));
            gap: 0.5rem 0.75rem;
            width: 100%;
        }

        .soap-vital-grid label {
            display: grid;
            gap: 0.2rem;
            margin: 0;
            font-size: 0.8rem;
            font-weight: 600;
            min-width: 0;
        }

        .soap-vital-grid__wide {
            grid-column: span 2;
        }

        .soap-vital-grid .input-group-text {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.45rem;
        }

        .soap-icd {
            display: grid;
            gap: 0.65rem 1rem;
            width: 100%;
            min-width: 0;
            grid-template-columns: 1fr 1fr;
            align-items: start;
        }

        @media (max-width: 991.98px) {
            .soap-icd {
                grid-template-columns: 1fr;
            }
        }

        .soap-icd__block {
            display: grid;
            gap: 0.3rem;
            min-width: 0;
        }

        .soap-icd__add {
            width: 2.5rem;
            min-width: 2.5rem;
            height: 2.5rem;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .soap-icd__add .bx {
            font-size: 1.15rem;
        }

        .soap-plan {
            display: grid;
            gap: 0.65rem 1rem;
            width: 100%;
            min-width: 0;
            grid-template-columns: 1fr 1fr;
            align-items: start;
        }

        @media (max-width: 991.98px) {
            .soap-plan {
                grid-template-columns: 1fr;
            }
        }

        .soap-plan__block {
            display: grid;
            gap: 0.3rem;
            min-width: 0;
        }

        .soap-plan__label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: #546e7a;
            margin-bottom: 0;
        }

        .soap-plan__fields {
            display: grid;
            gap: 0.5rem;
        }

        .soap-plan__pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        @media (max-width: 575.98px) {
            .soap-plan__pair {
                grid-template-columns: 1fr;
            }
        }

        .soap-icd__secondary {
            display: grid;
            gap: 0.45rem;
        }

        .soap-icd__row,
        .soap-icd__one {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            width: 100%;
            min-width: 0;
        }

        .soap-icd__control {
            flex: 1 1 auto;
            min-width: 0;
        }

        .soap-icd__control .select2-container,
        .soap-icd__row .select2-container {
            width: 100% !important;
            max-width: 100%;
        }

        .soap-icd__label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: #546e7a;
            margin-bottom: 0;
        }

        .soap-icd__action {
            flex: 0 0 auto;
            min-width: 2.5rem;
        }

        #resep.care-section { margin-bottom: 0; }
        .rx-editor {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .rx-toolbar-mini {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: center;
            gap: 0.35rem;
        }
        .rx-editor-table-wrap {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            overflow: visible;
            background: #fff;
        }
        .rx-editor-table { font-size: 0.78rem; margin: 0; }
        .rx-editor-table th {
            white-space: nowrap;
            background: #f1f5f9;
            color: #455a64;
            font-weight: 600;
            vertical-align: middle;
            padding: 0.35rem 0.4rem;
        }
        .rx-editor-table td { vertical-align: middle; padding: 0.3rem 0.35rem; }
        .rx-editor-table .form-control-sm,
        .rx-editor-table .form-select-sm {
            min-height: 1.7rem;
            padding-top: 0.15rem;
            padding-bottom: 0.15rem;
            font-size: 0.78rem;
        }
        .rx-signa { display: grid; grid-template-columns: repeat(4, 3.1rem); gap: 0.25rem; }
        .rx-signa__slot { display: flex; flex-direction: column; align-items: stretch; gap: 0.1rem; margin: 0; }
        .rx-signa__slot span { font-size: 0.62rem; font-weight: 600; line-height: 1.1; text-align: center; color: #00796b; }
        .rx-signa input { text-align: center; padding-left: 0.1rem; padding-right: 0.1rem; }
        .rx-qty-hint { display: none; }
        .rx-sig {
            display: flex;
            flex-wrap: wrap;
            gap: 0.2rem;
            margin-bottom: 0.25rem;
        }
        .rx-sig .btn {
            padding: 0.05rem 0.35rem;
            font-size: 0.68rem;
            line-height: 1.2;
            color: #455a64;
        }
        .rx-sig-field {
            position: relative;
            padding-right: 1.35rem;
        }
        .rx-sig-tip {
            position: absolute;
            top: 0;
            right: 0;
            z-index: 3;
            display: none;
        }
        .rx-row--active .rx-sig-tip,
        .rx-sig-field:focus-within .rx-sig-tip,
        .rx-sig-field.is-sig-open .rx-sig-tip { display: block; }
        .rx-sig-tip__toggle {
            width: 1.2rem;
            height: 1.2rem;
            padding: 0;
            line-height: 1;
            font-size: 0.75rem;
            color: #00897b;
            border-color: #b2dfdb !important;
            background: #e0f2f1 !important;
        }
        .rx-sig-field.is-sig-open .rx-sig-tip__toggle,
        .rx-sig-tip__toggle[aria-expanded="true"] {
            color: #004d40;
            border-color: #26a69a !important;
            background: #b2dfdb !important;
        }
        .rx-sig-tip__toggle .bx { font-size: 0.85rem; vertical-align: middle; }
        .rx-sig--tip {
            margin-bottom: 0;
            max-height: 9rem;
            overflow: auto;
        }
        .rx-sig-popover {
            --bs-popover-max-width: 16rem;
            z-index: 1085;
        }
        .rx-sig-popover .popover-body {
            padding: 0.45rem;
        }
        .rx-sig-popover .rx-sig {
            margin-bottom: 0;
        }
        .rx-row--compound { background: #f8fafc; }
        .rx-row[data-rx-row]:not(.is-editing) .rx-when-edit { display: none !important; }
        .rx-row[data-rx-row].is-editing .rx-when-view { display: none !important; }
        .rx-row--active { outline: 1px solid #80cbc4; background: #e0f2f1; }
        .rx-row--draft.rx-row--active { background: #fff3cd; }
        .rx-editor-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0;
        }
        .rx-editor-footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: center;
            gap: 0.65rem;
            padding: 0.25rem 0;
            border-top: 1px solid #eceff1;
            background: #fff;
        }
        .rx-estimate { color: #455a64; font-size: 0.9rem; }
        .rx-estimate strong { color: #00695c; font-size: 1rem; }
        .rx-draft-ingredients { display: grid; gap: 0.25rem; }
        .rx-components { display: grid; gap: 0.3rem; }
        .rx-editor-table .rx-col-money { display: none !important; }
        .rx-component-row { background: #fbfcfd; }
        .rx-component-row [data-hidden-additive] { display: none !important; }
        .rx-component-row > td { font-size: 0.74rem; vertical-align: middle; }
        .rx-component-row:not(.is-editing) .rx-when-edit { display: none !important; }
        .rx-component-row.is-editing .rx-when-view { display: none !important; }
        .rx-component {
            display: grid;
            gap: 0.25rem;
            padding: 0.3rem;
            border: 1px dashed #cfd8dc;
            border-radius: 0.375rem;
            background: #fff;
        }
        .rx-component__meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.7rem;
            color: #607d8b;
        }
        .rx-component__field { display: flex; align-items: center; gap: 0.25rem; margin: 0; }
        .rx-component__field .form-control-sm { width: 4.5rem; }
        .rx-signa select { text-align: center; padding-left: 0.15rem; padding-right: 1.1rem; }
        .rx-signa .select2-container { min-width: 0 !important; }
        .rx-signa .select2-container .select2-selection--single { min-height: 1.7rem; height: auto; }
        .rx-signa .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 0.2rem;
            padding-right: 0.9rem;
            font-size: 0.72rem;
            line-height: 1.6;
        }
        .rx-signa .select2-container--default .select2-selection--single .select2-selection__arrow { height: 1.6rem; width: 0.9rem; }
        .rx-draft-ingredient {
            display: grid;
            grid-template-columns: minmax(7rem, 1.3fr) minmax(5rem, 1fr) 3.5rem 1.6rem;
            gap: 0.2rem;
            align-items: start;
        }
        .rx-editor-table .select2-container {
            width: 100% !important;
            min-width: 9rem;
        }
        .rx-editor-table .select2-container .select2-selection--single {
            min-height: 1.7rem;
            height: auto;
            border-color: #ced4da;
        }
        .rx-editor-table .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0.4rem;
            font-size: 0.78rem;
            color: #455a64;
        }
        .rx-editor-table .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 1.6rem;
        }
        .rx-row--draft { background: #fff8e1; }
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
            if (form.hasAttribute('data-systemic-form') || ((form.getAttribute('action') || '').indexOf('/systemic') !== -1)) {
                syncSystemicSummary(form);
            }
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

    function allergyField(row, key) {
        var input = row.querySelector('[data-allergy-field="' + key + '"]');
        return input ? String(input.value || '').trim() : '';
    }

    function syncSystemicSummary(form) {
        var target = document.querySelector('[data-systemic-summary]');
        if (!target || !form) return;
        var labels = @json($examOptions['systemic'] ?? []);
        var bits = [];
        form.querySelectorAll('[data-sys-conditions] input[type="checkbox"]:checked').forEach(function (box) {
            if (box.value === 'allergy') return;
            bits.push(labels[box.value] || box.value);
        });
        var medStatus = form.querySelector('[name="medication_status"]');
        var medDetail = form.querySelector('[name="medication_detail"]');
        if (medStatus && medStatus.value === 'present') {
            bits.push('Obat rutin' + (medDetail && medDetail.value ? ': ' + medDetail.value.trim() : ''));
        }
        var blood = form.querySelector('[name="blood_type"]');
        var rhesus = form.querySelector('[name="rhesus"]');
        if (blood && blood.value) {
            var rh = rhesus && rhesus.value === 'positive' ? '+' : (rhesus && rhesus.value === 'negative' ? '-' : '');
            bits.push('Gol. darah ' + blood.value + rh);
        }
        target.textContent = bits.join(' · ');
        target.hidden = bits.length === 0;
    }

    function syncAllergySummary(form) {
        var target = document.querySelector('[data-allergy-summary]');
        if (!target || !form) return;
        var statusField = form.querySelector('[name="allergy_status"]');
        var status = statusField ? statusField.value : '';
        var categoryLabels = (window.careAllergyLabels && window.careAllergyLabels.category) || {
            food: 'Alergi Makanan',
            environment: 'Alergi Udara',
            medication: 'Alergi Obat',
            biologic: 'Alergi lainnya'
        };
        if (status === 'unknown') {
            target.textContent = 'Tidak diketahui';
            target.classList.remove('text-muted');
            return;
        }
        if (status === 'none_confirmed') {
            target.textContent = 'Tidak ada alergi';
            target.classList.remove('text-muted');
            return;
        }
        if (status !== 'present') {
            target.textContent = '—';
            target.classList.add('text-muted');
            return;
        }
        var bits = [];
        form.querySelectorAll('[data-allergy-row]').forEach(function (row) {
            var category = allergyField(row, 'category') || (row.querySelector('[data-allergy-category]') || {}).value || '';
            var detail = allergyField(row, 'detail') || (row.querySelector('[data-allergy-detail]') || {}).value || '';
            var reaction = allergyField(row, 'reaction');
            if (!reaction) {
                var reactionField = row.querySelector('[name*="[reaction]"]');
                reaction = reactionField ? String(reactionField.value || '').trim() : '';
            }
            if (!category && !detail && !reaction) return;
            var label = categoryLabels[category] || category || 'Alergi';
            var parts = [];
            if (detail) parts.push(detail);
            if (reaction) parts.push('reaksi: ' + reaction);
            bits.push(label + (parts.length ? ': ' + parts.join(' — ') : ''));
        });
        target.textContent = bits.length ? bits.join(' · ') : 'Ada alergi (detail belum diisi)';
        target.classList.remove('text-muted');
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
        var decisionField = document.getElementById('plan_decision');
        if (decision && decision !== '—') bits.push('Keputusan: ' + decision + '.');
        if (decisionField && decisionField.value === 'control') {
            var controlDate = document.getElementById('follow-up-control-date');
            if (controlDate && controlDate.value) {
                var parts = controlDate.value.split('-');
                bits.push('Tanggal kontrol: ' + (parts.length === 3 ? parts[2] + '-' + parts[1] + '-' + parts[0] : controlDate.value) + '.');
            }
        }
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
        if (document.querySelector('[data-plan-summary]')) {
            syncPlanSummary(form);
            form.addEventListener('change', function () { syncPlanSummary(form); });
            form.addEventListener('input', function () { syncPlanSummary(form); });
        }
    });
    function syncControlDate(clearWhenHidden) {
        var decision = document.getElementById('plan_decision');
        var wrap = document.querySelector('[data-control-date-wrap]');
        var input = document.getElementById('follow-up-control-date');
        var letterBtn = document.querySelector('[data-control-letter-btn]');
        if (!decision || !wrap) return;
        var show = decision.value === 'control';
        wrap.hidden = !show;
        if (!show && clearWhenHidden && input) input.value = '';
        if (letterBtn) {
            var base = letterBtn.getAttribute('data-letter-base') || letterBtn.href;
            try {
                var url = new URL(base, window.location.origin);
                if (show && input && input.value) {
                    url.searchParams.set('control_date', input.value);
                } else {
                    url.searchParams.delete('control_date');
                }
                letterBtn.href = url.pathname + url.search;
            } catch (e) {}
        }
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
        controlDateInput.addEventListener('change', function () {
            syncControlDate(false);
            syncPlanReview();
        });
        controlDateInput.addEventListener('input', function () {
            syncControlDate(false);
            syncPlanReview();
        });
    }
    ['plan_decision'].forEach(function (id) {
        var field = document.getElementById(id);
        if (!field || id === 'plan_decision') return;
        field.addEventListener('change', syncPlanReview);
    });
    window.careSetStatus = setStatus;
    window.careValidationMessage = validationMessage;
    window.careMarkDone = markDone;

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

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-toggle-panel]');
        if (!btn) return;
        var target = document.querySelector(btn.getAttribute('data-toggle-panel'));
        if (!target) return;
        target.hidden = !target.hidden;
        if (!target.hidden && $) {
            $(target).find('.select2-container').css('width', '100%');
        }
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

    function bindTypeahead(box) {
        if (!box || box.dataset.typeaheadBound === '1') return;
        var input = box.querySelector('[data-typeahead-q]');
        if (!input) return;
        box.dataset.typeaheadBound = '1';
        var url = box.dataset.typeaheadUrl;
        var local = [];
        try {
            if (box.dataset.typeaheadSource) {
                var source = document.getElementById(box.dataset.typeaheadSource);
                local = JSON.parse(source ? source.textContent || '[]' : '[]');
            } else {
                local = JSON.parse(box.dataset.typeaheadItems || '[]');
            }
        } catch (e) { local = []; }
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
    }

    document.querySelectorAll('[data-typeahead]').forEach(bindTypeahead);
    document.addEventListener('care:bind-typeahead', function (event) {
        var root = event.target;
        if (!root || !root.querySelectorAll) return;
        if (root.matches && root.matches('[data-typeahead]')) bindTypeahead(root);
        root.querySelectorAll('[data-typeahead]').forEach(bindTypeahead);
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
        var removeBtn = row.querySelector('[data-icd-secondary-remove]');
        if (!select || !root || !$) return;

        var saving = false;

        function save(data) {
            var description = (data && data.description) || '';
            var code = (data && (data.code || data.id)) || '';
            if (!description || String(code).indexOf('id:') === 0 || saving) return;
            saving = true;
            setStatus('Menyimpan…');
            var body = new FormData();
            body.append('_token', token);
            body.append('code', code);
            body.append('description', description);
            body.append('kind', 'clinical');
            body.append('rank', 'secondary');
            var updateUrl = select.getAttribute('data-update');
            if (updateUrl) body.append('_method', 'PUT');
            fetch(updateUrl || root.dataset.store, {
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
                    return payload || {};
                });
            }).then(function (payload) {
                if (payload.update_url) select.setAttribute('data-update', payload.update_url);
                if (payload.destroy_url) select.setAttribute('data-destroy', payload.destroy_url);
                if (removeBtn) removeBtn.hidden = false;
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

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                if (saving) return;
                var destroyUrl = select.getAttribute('data-destroy');
                var list = row.parentElement;
                function removeRow() {
                    if (list && list.querySelectorAll('[data-soap-icd-row]').length <= 1) {
                        $(select).val(null).trigger('change');
                        select.removeAttribute('data-update');
                        select.removeAttribute('data-destroy');
                        removeBtn.hidden = true;
                        syncSecondaryAddButtons(list);
                        return;
                    }
                    row.remove();
                    syncSecondaryAddButtons(list);
                }
                if (!destroyUrl) {
                    removeRow();
                    return;
                }
                saving = true;
                setStatus('Menyimpan…');
                var body = new FormData();
                body.append('_token', token);
                body.append('_method', 'DELETE');
                fetch(destroyUrl, {
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
                    });
                }).then(function () {
                    saving = false;
                    removeRow();
                    setStatus('✓ Tersimpan');
                }).catch(function (error) {
                    saving = false;
                    setStatus(error && error.message ? error.message : 'Gagal menyimpan', true);
                });
            });
        }
    }

    function syncSecondaryAddButtons(list) {
        if (!list) return;
        var rows = list.querySelectorAll('[data-soap-icd-row]');
        rows.forEach(function (row, index) {
            var addBtn = row.querySelector('[data-icd-secondary-add]');
            if (addBtn) addBtn.hidden = index !== rows.length - 1;
        });
    }

    function bindSecondaryAdd() {
        var root = document.getElementById('soap-icd');
        var list = document.querySelector('[data-icd-secondary-list]');
        var tpl = document.getElementById('soap-icd-secondary-template');
        if (!root || !list || !tpl || list.dataset.addBound === '1') return;
        list.dataset.addBound = '1';
        list.addEventListener('click', function (event) {
            var addBtn = event.target.closest('[data-icd-secondary-add]');
            if (!addBtn || !list.contains(addBtn)) return;
            event.preventDefault();
            var wrap = document.createElement('div');
            wrap.innerHTML = tpl.innerHTML.trim();
            var row = wrap.firstElementChild;
            if (!row) return;
            list.appendChild(row);
            bindSoapIcd(row);
            syncSecondaryAddButtons(list);
        });
        syncSecondaryAddButtons(list);
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
            body.append('rank', select.getAttribute('data-rank') || 'primary');
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
                var resetButton = select.closest('.soap-icd__row') ? select.closest('.soap-icd__row').querySelector('[data-icd-reset]') : null;
                if (resetButton) resetButton.hidden = false;
                select.dataset.saving = '0';
                setStatus('✓ Tersimpan');
            }).catch(function (error) {
                select.dataset.saving = '0';
                setStatus(error && error.message ? error.message : 'Gagal menyimpan', true);
            });
        });
        var resetButton = select.closest('.soap-icd__row') ? select.closest('.soap-icd__row').querySelector('[data-icd-reset]') : null;
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
            bindSecondaryAdd();
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
            var finish = document.getElementById('care-finish-rx');
            var ssBlocked = finish && finish.getAttribute('data-ss-block') === '1';
            submitBtn.disabled = !confirmCb.checked || !!ssBlocked;
            if (ssBlocked) {
                submitBtn.title = 'Perbaiki kelengkapan resep (SIG / tipe NC·EP·SD) sebelum kirim ke farmasi';
            } else {
                submitBtn.removeAttribute('title');
            }
        }

        confirmCb.addEventListener('change', syncSubmit);
        modal.addEventListener('shown.bs.modal', syncSubmit);
        modal.addEventListener('hidden.bs.modal', function () {
            confirmCb.checked = false;
            syncSubmit();
        });
        form.addEventListener('submit', function (event) {
            var finish = document.getElementById('care-finish-rx');
            if (finish && finish.getAttribute('data-ss-block') === '1') {
                event.preventDefault();
                syncSubmit();
                return;
            }
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-busy', '1');
        });
        syncSubmit();
    })();
})();
</script>
@endpush
