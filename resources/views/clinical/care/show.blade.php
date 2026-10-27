@extends('layouts.app')

@section('title', 'Pelayanan '.$visit->patient?->name)

@php
    $carePartials = [
        'keluhan' => 'clinical.care.partials.soap',
        'screening' => 'clinical.care.partials.screening',
        'pemeriksaan' => 'clinical.care.partials.dental-exam',
        'odontogram' => 'clinical.care.partials.odontogram',
        'diagnosis' => 'clinical.care.partials.plan-diagnosis',
        'tindakan' => 'clinical.care.partials.plan-procedures',
        'resep' => 'clinical.care.partials.plan-prescriptions',
        'rujukan' => 'clinical.care.partials.plan-referrals',
        'instruksi' => 'clinical.care.partials.plan-notes',
        'lab' => 'clinical.care.partials.plan-lab',
        'surat' => 'clinical.care.partials.plan-letters',
        'adendum' => 'clinical.care.partials.plan-amendments',
    ];
@endphp

@section('content')
    <x-page-header title="Pelayanan" :breadcrumb="['Pelayanan' => route('examinations.index'), $visit->patient?->name => null]" />
    <x-alert />

    <div class="care-workspace" data-section="{{ $section }}" data-writable="{{ $writable ? '1' : '0' }}">
        @include('clinical.care.partials.header')

        <div class="care-panel">
            <div class="care-nav-bar">
                <ul class="nav nav-tabs nav-tabs-custom care-nav" role="tablist">
                    @foreach ($sections as $key => $item)
                        <li class="nav-item">
                            <a
                                class="nav-link {{ $section === $key ? 'active' : '' }} {{ ! empty($progress[$key]) ? 'is-done' : '' }}"
                                href="{{ route('care.show', ['visit' => $visit, 'tab' => $key]) }}"
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
            @endphp
            <div class="care-finish">
                <x-button type="button" variant="success" icon="bx bx-check" data-bs-toggle="modal" data-bs-target="#care-complete-modal">
                    Simpan &amp; Selesaikan Kunjungan
                </x-button>
            </div>

            <x-modal id="care-complete-modal" title="Verifikasi &amp; selesaikan kunjungan" size="modal-lg" class="care-modal">
                <p class="text-muted mb-3">Periksa kembali tindakan dan obat yang diberikan sebelum menyelesaikan pelayanan.</p>

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
                        <span>Saya sudah memverifikasi tindakan dan obat di atas, serta menyetujui penyelesaian kunjungan ini.</span>
                    </label>
                </form>

                <x-slot:footer>
                    <x-button variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                    <x-button type="submit" variant="success" icon="bx bx-check" form="care-complete-form" id="care-complete-submit" class="btn-lg" disabled>
                        Ya, Selesaikan Kunjungan
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
                if (id) id.value = item.id || '';
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
            var $el = $(this);
            if ($el.data('select2')) return;
            $el.select2({
                width: '100%',
                placeholder: 'Ketik kode atau nama, mis. tumpatan / 23.2',
                allowClear: true,
                dropdownParent: selectDropdownParent($el),
                language: {
                    noResults: function () { return 'ICD-9-CM tidak ditemukan'; },
                    searching: function () { return 'Mencari…'; }
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

            if (icdForm) {
                icdForm.addEventListener('submit', function (e) {
                    if (codeInput && codeInput.value && descInput && descInput.value) return;
                    e.preventDefault();
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

    if ($ && $.fn && $.fn.select2) {
        var workspace = document.querySelector('.care-workspace');
        if (workspace) {
            initToothSelects(workspace);
            initIcdSelects(workspace);
            initIcd9Selects(workspace);
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
