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
            <div class="care-finish">
                <form action="{{ route('care.complete', $visit) }}" method="POST" onsubmit="return confirm('Selesaikan pelayanan kunjungan ini?')">
                    @csrf
                    <x-button type="submit" variant="success" icon="bx bx-check">Simpan &amp; Selesaikan Kunjungan</x-button>
                </form>
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <link href="{{ theme('libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css">
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

    function setStatus(text) {
        if (statusEl) statusEl.textContent = text;
    }

    function markDone(section) {
        var link = document.querySelector('[data-care-section="' + section + '"]');
        if (!link) return;
        link.classList.add('is-done');
        var mark = link.querySelector('.care-flow-link__mark');
        if (mark) mark.textContent = '✓';
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
            if (!res.ok) throw new Error('save failed');
            return res.json();
        }).then(function (data) {
            setStatus('✓ Tersimpan');
            if (data && data.section) markDone(data.section);
            return data;
        }).catch(function () {
            setStatus('Gagal menyimpan');
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

    var queueSearch = document.getElementById('care-queue-search');
    var queueList = document.querySelector('[data-queue-list]');
    if (queueSearch && queueList) {
        var queueItems = queueList.querySelectorAll('[data-queue-item]');
        var queueMiss = queueList.querySelector('[data-queue-miss]');
        var queueEmpty = queueList.querySelector('[data-queue-empty]');
        queueSearch.addEventListener('input', function () {
            var q = queueSearch.value.trim().toLowerCase();
            var shown = 0;
            queueItems.forEach(function (item) {
                var on = !q || (item.dataset.search || '').indexOf(q) !== -1;
                item.hidden = !on;
                if (on) shown += 1;
            });
            if (queueMiss) queueMiss.hidden = !(q && shown === 0);
            if (queueEmpty) queueEmpty.hidden = q !== '' || queueItems.length > 0;
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
        $('[data-odo-tooth-select]').each(function () {
            $(this).select2({
                width: '100%',
                placeholder: 'Pilih dari temuan odontogram',
                allowClear: true,
                dropdownParent: $(document.body),
                language: {
                    noResults: function () { return 'Belum ada temuan odontogram'; }
                }
            });
        });

        $('[data-icd-select]').each(function () {
            var icd = this;
            var $icd = $(icd);
            var icdForm = icd.closest('form');
            var codeInput = icdForm ? icdForm.querySelector('[name="code"]') : null;
            var descInput = icdForm ? icdForm.querySelector('[name="description"]') : null;

            $icd.select2({
                width: '100%',
                placeholder: 'Ketik kode atau nama, mis. karies / K02.1',
                allowClear: true,
                minimumInputLength: 1,
                dropdownParent: $(document.body),
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

    document.addEventListener('care:tab', function () {
        if ($) $('.care-workspace .select2-container').css('width', '100%');
    });
})();
</script>
@endpush
