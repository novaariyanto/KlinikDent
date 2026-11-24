@php
    $embedded = $embedded ?? false;
    $prescriptionTemplates = $prescriptionTemplates ?? collect();
    $reopenRx = old('_form') === 'prescription-create';
    $reopenCompound = old('_form') === 'compound-create';
    $reopenTemplate = old('_form') === 'template-create';
    $compoundRows = $reopenCompound ? array_values(old('ingredients', [])) : [];
    $templateRows = $reopenTemplate ? array_values(old('items', [])) : [['quantity' => 1]];
@endphp
<section class="care-section rx-compact{{ $embedded ? ' care-section--embedded' : '' }}" id="resep">
    <div class="care-section__body p-0">
        <div id="rx-list">
            @include('clinical.care.partials.plan-prescriptions-list')
        </div>

        @can('prescription.create')
            @if ($writable)
                <x-modal id="rx-create-modal" title="Tambah obat" size="modal-lg" class="care-modal" :data-show-on-load="$reopenRx ? '' : null">
                    <form action="{{ route('care.prescription-item.store', $visit) }}" method="POST" class="rx-form" data-rx-ajax data-rx-reset>
                        @csrf
                        <input type="hidden" name="_form" value="prescription-create">
                        <input type="hidden" name="medication_type" value="NC">
                        <p class="form-text mb-2">Klik pilihan atau ketik. AC, PC, PRN, HS, SOS, cth, csd bukan kode SATUSEHAT.</p>
                        <div class="care-typeahead mb-2" data-typeahead data-typeahead-items="{{ json_encode($medicines->map(fn ($medicine) => ['id' => $medicine->id, 'label' => $medicine->catalogLabel()])->values()) }}">
                            <label class="form-label">Cari obat</label>
                            <input type="search" class="form-control" data-typeahead-q placeholder="Nama obat" autocomplete="off">
                            <input type="hidden" name="medicine_id" data-typeahead-id value="{{ $reopenRx ? old('medicine_id') : '' }}" required>
                            <div class="care-typeahead__list" data-typeahead-results hidden></div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label" for="rx-create-dosage">Dosis</label>
                                @include('clinical.care.partials.prescription-sig', ['group' => 'doses', 'target' => 'rx-create-dosage'])
                                <x-input name="dosage" id="rx-create-dosage" label="" :value="$reopenRx ? old('dosage') : ''" required />
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label" for="rx-create-frequency">Frekuensi</label>
                                @include('clinical.care.partials.prescription-sig', ['group' => 'frequencies', 'target' => 'rx-create-frequency'])
                                <x-input name="frequency" id="rx-create-frequency" label="" :value="$reopenRx ? old('frequency') : ''" required />
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label class="form-label" for="rx-create-duration">Aturan pakai</label>
                                @include('clinical.care.partials.prescription-sig', ['group' => 'instructions', 'target' => 'rx-create-duration'])
                                <x-input name="duration" id="rx-create-duration" label="" :value="$reopenRx ? old('duration') : ''" required />
                            </div>
                            <div class="col-6 col-md-3 col-lg-2">
                                <label class="form-label" for="rx-create-course">Durasi</label>
                                @include('clinical.care.partials.prescription-sig', ['group' => 'courses', 'target' => 'rx-create-course'])
                                <x-input name="course" id="rx-create-course" label="" :value="$reopenRx ? old('course') : ''" />
                            </div>
                            <div class="col-6 col-md-3 col-lg-1">
                                <x-input name="quantity" id="rx-create-quantity" type="number" min="1" label="Qty" :value="$reopenRx ? old('quantity', 1) : 1" required />
                            </div>
                        </div>
                        <div class="rx-toolbar justify-content-end mt-1">
                            <button type="submit" class="btn btn-sm btn-primary" title="Simpan" aria-label="Simpan obat">
                                <i class="bx bx-save me-1"></i>Simpan
                            </button>
                        </div>
                    </form>
                </x-modal>

                <x-modal id="rx-compound-modal" title="Tambah racikan" size="modal-lg" class="care-modal" :data-show-on-load="$reopenCompound ? '' : null">
                    <form action="{{ route('care.prescription-item.store', $visit) }}" method="POST" class="rx-form" data-rx-ajax data-rx-reset data-compound-form>
                        @csrf
                        <input type="hidden" name="_form" value="compound-create">
                        <input type="hidden" name="kind" value="racikan">
                        @include('clinical.care.partials.prescription-compound-fields', [
                            'sigPrefix' => 'compound-create',
                            'ingredientRows' => $compoundRows,
                            'medicines' => $medicines,
                            'medicationType' => $reopenCompound ? old('medication_type', 'EP') : 'EP',
                            'dosage' => $reopenCompound ? old('dosage') : '',
                            'frequency' => $reopenCompound ? old('frequency') : '',
                            'duration' => $reopenCompound ? old('duration') : '',
                            'course' => $reopenCompound ? old('course') : '',
                            'quantity' => $reopenCompound ? old('quantity', 1) : 1,
                        ])
                        <div class="rx-toolbar justify-content-end">
                            <button type="submit" class="btn btn-sm btn-primary" title="Simpan" aria-label="Simpan racikan">
                                <i class="bx bx-save me-1"></i>Simpan
                            </button>
                        </div>
                    </form>
                </x-modal>

                <x-modal id="rx-ingredient-modal" title="Bahan obat" size="modal-lg" class="care-modal">
                    <div data-ingredient-editor>
                        <input type="hidden" data-ing-role value="active">
                        <input type="hidden" data-ing-additive-kind value="">

                        <div class="mb-2" data-ing-additive-helpers hidden>
                            <label class="form-label mb-1">Jenis bahan tambahan</label>
                            <div class="rx-sig">
                                @foreach (\App\Support\Clinical\PrescriptionSigCatalog::additives() as $additive)
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light border"
                                        data-ing-preset-additive="{{ $additive['kind'] }}"
                                        data-additive-name="{{ $additive['name'] }}"
                                        data-additive-unit="{{ $additive['unit'] }}"
                                        title="{{ $additive['hint'] }}"
                                        aria-label="{{ $additive['hint'] }}"
                                    >{{ $additive['label'] }}</button>
                                @endforeach
                            </div>
                            <p class="form-text mb-0">Sakarin, perasa, dan pengisi opsional. Pilih jenis untuk mengisi nama &amp; satuan cepat.</p>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label" for="rx-ing-name">Nama bahan</label>
                                <input type="text" class="form-control" id="rx-ing-name" data-ing-name required maxlength="120" placeholder="Nama bahan">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="rx-ing-medicine">Obat stok</label>
                                <select class="form-select" id="rx-ing-medicine" data-ing-medicine>
                                    <option value="">Tidak dipotong stok</option>
                                    @foreach ($medicines as $medicine)
                                        <option
                                            value="{{ $medicine->id }}"
                                            data-name="{{ $medicine->name }}"
                                            data-unit="{{ $medicine->unit }}"
                                            data-strength-value="{{ $medicine->strength_value }}"
                                            data-strength-unit="{{ $medicine->strength_unit }}"
                                        >{{ $medicine->catalogLabel() }}</option>
                                    @endforeach
                                </select>
                                <p class="form-text mb-0">Opsional. Isi jika stok bahan ikut dipotong.</p>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="rx-ing-per">Per bungkus</label>
                                @include('clinical.care.partials.prescription-sig', ['group' => 'amounts', 'target' => 'rx-ing-per'])
                                <input type="number" class="form-control" id="rx-ing-per" data-ing-per min="1" placeholder="Opsional">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="rx-ing-total">Total</label>
                                @include('clinical.care.partials.prescription-sig', ['group' => 'amounts', 'target' => 'rx-ing-total'])
                                <input type="number" class="form-control" id="rx-ing-total" data-ing-total min="1" value="1" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="rx-ing-unit">Satuan</label>
                                @include('clinical.care.partials.prescription-sig', ['group' => 'units', 'target' => 'rx-ing-unit'])
                                <input type="text" class="form-control" id="rx-ing-unit" data-ing-unit maxlength="30" placeholder="mg / ml">
                            </div>
                            <div class="col-6 col-md-3" data-ing-strength-wrap>
                                <label class="form-label" for="rx-ing-strength">Kekuatan</label>
                                <input type="number" class="form-control" id="rx-ing-strength" data-ing-strength step="0.0001">
                            </div>
                            <div class="col-6 col-md-3" data-ing-strength-wrap>
                                <label class="form-label" for="rx-ing-strength-unit">Unit kekuatan</label>
                                @include('clinical.care.partials.prescription-sig', ['group' => 'units', 'target' => 'rx-ing-strength-unit'])
                                <input type="text" class="form-control" id="rx-ing-strength-unit" data-ing-strength-unit maxlength="30">
                            </div>
                        </div>
                        <p class="form-text mb-0 mt-2">Total stok = per bungkus × jumlah bungkus. Boleh dikoreksi manual. Klik pilihan cepat atau ketik sendiri.</p>
                    </div>
                    <x-slot:footer>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" data-ingredient-save>
                            <i class="bx bx-save me-1"></i>Simpan bahan
                        </button>
                    </x-slot:footer>
                </x-modal>

                @php
                    $rxCatalogStock = app(\App\Support\Pharmacy\MedicineStockQuery::class)->availableByMedicines(
                        (int) $visit->tenant_id,
                        (int) $visit->branch_id,
                        $medicines->pluck('id')->all(),
                    );
                    $rxMedicineCatalog = $medicines->map(function ($medicine) use ($rxCatalogStock) {
                        return [
                            'id' => $medicine->id,
                            'label' => $medicine->catalogLabel(),
                            'name' => $medicine->name,
                            'unit' => $medicine->unit,
                            'unit_label' => $medicine->unitLabel(),
                            'strength' => $medicine->strengthLabel(),
                            'price' => (float) ($medicine->base_price ?? 0),
                            'stock' => (int) ($rxCatalogStock[$medicine->id] ?? 0),
                        ];
                    })->values();
                @endphp
                <script type="application/json" id="rx-medicine-catalog">@json($rxMedicineCatalog)</script>

                <x-modal id="rx-template-modal" title="Template resep" size="modal-lg" class="care-modal">
                    <div class="rx-toolbar justify-content-between align-items-center mb-2">
                        <p class="form-text mb-0">Pilih template untuk diterapkan ke resep draft. Item tetap bisa diubah setelah diterapkan.</p>
                        <button type="button" class="btn btn-sm btn-primary text-nowrap" data-rx-open-template-create title="Tambah template baru" aria-label="Tambah template baru">
                            <i class="bx bx-plus me-1"></i>Tambah baru
                        </button>
                    </div>
                    <div id="rx-template-list">
                        @include('clinical.care.partials.plan-prescriptions-templates')
                    </div>
                </x-modal>

                <x-modal id="rx-template-create-modal" title="Template baru" size="modal-xl" class="care-modal" :data-show-on-load="$reopenTemplate ? '' : null">
                    <p class="form-text mb-2">Isi nama dan daftar obat. Setelah disimpan, template muncul di daftar dan bisa diterapkan ke resep draft.</p>
                    <form action="{{ route('care.prescription-templates.store', $visit) }}" method="POST" class="rx-form" data-template-editor data-rx-ajax data-rx-reset data-rx-reopen-templates>
                        @csrf
                        <input type="hidden" name="_form" value="template-create">
                        <x-input name="name" label="Nama template" :value="$reopenTemplate ? old('name') : ''" placeholder="Resep pasca pencabutan gigi" required />
                        <div data-template-rows>
                            @foreach ($templateRows as $index => $row)
                                @if (($row['kind'] ?? '') === 'racikan')
                                    @include('clinical.care.partials.prescription-template-compound-row', [
                                        'index' => $index,
                                        'prefix' => 'tpl-cmp-'.$index,
                                        'row' => $row,
                                        'medicines' => $medicines,
                                    ])
                                @else
                                    @include('clinical.care.partials.prescription-template-row', [
                                        'index' => $index,
                                        'prefix' => 'tpl-new-'.$index,
                                        'row' => $row,
                                        'medicines' => $medicines,
                                    ])
                                @endif
                            @endforeach
                        </div>
                        <template data-template-row-template>
                            @include('clinical.care.partials.prescription-template-row', [
                                'index' => '__i__',
                                'prefix' => 'tpl-new-__i__',
                                'row' => ['quantity' => 1],
                                'medicines' => $medicines,
                            ])
                        </template>
                        <template data-template-compound-template>
                            @include('clinical.care.partials.prescription-template-compound-row', [
                                'index' => '__i__',
                                'prefix' => 'tpl-cmp-__i__',
                                'row' => [
                                    'medication_type' => 'EP',
                                    'quantity' => 1,
                                    'ingredients' => [
                                        ['role' => 'active', 'quantity' => 1],
                                        ['role' => 'active', 'quantity' => 1],
                                    ],
                                ],
                                'medicines' => $medicines,
                            ])
                        </template>
                        <div class="rx-toolbar justify-content-between mt-1">
                            <div class="rx-toolbar">
                                <button type="button" class="btn btn-sm btn-light border" data-add-template-row title="Tambah obat" aria-label="Tambah obat ke template">
                                    <i class="bx bx-plus me-1"></i>Tambah obat
                                </button>
                                <button type="button" class="btn btn-sm btn-light border" data-add-template-compound title="Tambah racikan" aria-label="Tambah racikan ke template">
                                    <i class="bx bx-capsule me-1"></i>Tambah racikan
                                </button>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary" title="Simpan template" aria-label="Simpan template baru">
                                <i class="bx bx-save me-1"></i>Simpan
                            </button>
                        </div>
                    </form>
                </x-modal>
            @endif
        @endcan
    </div>
</section>
<script>
(function () {
    function hideRxModal(form) {
        var modal = form.closest('.modal');
        if (modal && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
    }

    function resetRxForm(form) {
        if (!form.hasAttribute('data-rx-reset')) return;
        form.reset();
        form.querySelectorAll('[data-typeahead-id]').forEach(function (input) {
            input.value = '';
        });
        form.querySelectorAll('[data-typeahead-q]').forEach(function (input) {
            input.value = '';
        });
        form.querySelectorAll('[data-total-qty]').forEach(function (input) {
            input.dataset.manual = '';
        });
        if (form.hasAttribute('data-compound-form') || form.querySelector('[data-compound-form]')) {
            var compound = form.matches('[data-compound-form]') ? form : form.querySelector('[data-compound-form]');
            if (compound) {
                syncStrength(compound);
                preview(compound);
            }
        }
    }

    function rxDisableBrowserAutofill(scope) {
        var root = scope || document;
        root.querySelectorAll('.rx-sig-field input, .rx-sig-field select').forEach(function (el) {
            el.setAttribute('autocomplete', 'off');
            el.setAttribute('autocapitalize', 'off');
            el.setAttribute('autocorrect', 'off');
            el.setAttribute('spellcheck', 'false');
            el.setAttribute('data-lpignore', 'true');
            el.setAttribute('data-1p-ignore', 'true');
            el.setAttribute('data-form-type', 'other');
        });
    }

    function replaceRxList(html, options) {
        options = options || {};
        document.querySelectorAll('body > [id^="rx-save-template-"]').forEach(function (el) {
            el.remove();
        });
        var list = document.getElementById('rx-list');
        if (!list || html == null) return;
        list.innerHTML = html;
        list.querySelectorAll('.care-modal').forEach(function (modal) {
            document.body.appendChild(modal);
        });
        rxDestroyMedicineSelects(list);
        list.dispatchEvent(new CustomEvent('care:bind-typeahead', { bubbles: true }));
        list.querySelectorAll('[data-compound-form]').forEach(function (form) {
            syncStrength(form);
            preview(form);
        });
        rxDisableBrowserAutofill(list);
        rxInitMedicineSelects(list);
        rxSeedDefaultDraft({ focus: false });
        if (options.focusItemId) {
            var focusRow = list.querySelector('[data-rx-row][data-item-id="' + options.focusItemId + '"]');
            if (focusRow) {
                rxSetActiveRow(focusRow, null, { openSig: options.openSig !== false });
                var focusField = options.focusName
                    ? focusRow.querySelector('[name="' + options.focusName + '"]')
                    : null;
                if (!focusField) {
                    focusField = focusRow.querySelector('input:not([type="hidden"]), select, textarea');
                }
                if (focusField) {
                    focusField.focus({ preventScroll: true });
                    if (typeof options.selectionStart === 'number' && focusField.setSelectionRange) {
                        try {
                            var pos = options.selectionStart;
                            focusField.setSelectionRange(pos, pos);
                        } catch (e) {}
                    }
                }
            }
        }
    }

    function replaceTemplatesList(html) {
        var list = document.getElementById('rx-template-list');
        if (!list || html == null) return;
        list.innerHTML = html;
        list.dispatchEvent(new CustomEvent('care:bind-typeahead', { bubbles: true }));
        list.querySelectorAll('[data-compound-form]').forEach(function (form) {
            syncStrength(form);
            preview(form);
        });
    }

    function syncFinishConfirm() {
        var finish = document.getElementById('care-finish-rx');
        var form = document.getElementById('care-complete-form');
        if (!finish || !form) return;
        var draft = Number(finish.getAttribute('data-draft-count') || 0) > 0;
        var finalize = form.getAttribute('data-finalize') === '1';
        var confirmText = form.querySelector('[data-care-finish-confirm-text]');
        var submitText = document.querySelector('[data-care-finish-submit-text]');
        if (confirmText) {
            confirmText.textContent = 'Saya sudah memverifikasi tindakan dan obat di atas, serta menyetujui penyelesaian kunjungan'
                + (draft ? ' dan pengiriman resep ke farmasi' : '')
                + (finalize ? ' serta finalisasi asesmen dan rekam medis' : '')
                + ' ini.';
        }
        if (submitText) {
            if (finalize && draft) submitText.textContent = 'Ya, Kirim Resep & Finalisasi';
            else if (draft) submitText.textContent = 'Ya, Kirim Resep & Selesaikan';
            else if (finalize) submitText.textContent = 'Ya, Selesaikan & Finalisasi';
            else submitText.textContent = 'Ya, Selesaikan Kunjungan';
        }
        var submitBtn = document.getElementById('care-complete-submit');
        var confirmCb = document.getElementById('care-complete-confirm');
        if (submitBtn && confirmCb) {
            var ssBlocked = finish.getAttribute('data-ss-block') === '1';
            submitBtn.disabled = !confirmCb.checked || ssBlocked;
            if (ssBlocked) {
                submitBtn.title = 'Perbaiki kelengkapan resep (SIG / tipe NC·EP·SD) sebelum kirim ke farmasi';
            } else {
                submitBtn.removeAttribute('title');
            }
        }
    }

    function replaceFinishRx(html) {
        var current = document.getElementById('care-finish-rx');
        if (!current || html == null) return;
        current.outerHTML = html;
        syncFinishConfirm();
    }

    function reopenTemplatesModal() {
        if (!window.bootstrap) return;
        var listModal = document.getElementById('rx-template-modal');
        if (listModal) window.bootstrap.Modal.getOrCreateInstance(listModal).show();
    }

    document.addEventListener('click', function (event) {
        var openCreate = event.target.closest('[data-rx-open-template-create]');
        if (!openCreate || !window.bootstrap) return;
        var listModal = document.getElementById('rx-template-modal');
        var createModal = document.getElementById('rx-template-create-modal');
        if (!createModal) return;
        if (listModal) window.bootstrap.Modal.getOrCreateInstance(listModal).hide();
        window.bootstrap.Modal.getOrCreateInstance(createModal).show();
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-rx-ajax]');
        if (!form) return;
        event.preventDefault();
        if (form.dataset.busy === '1') return;
        var confirmMsg = form.getAttribute('data-rx-confirm');
        if (confirmMsg && !window.confirm(confirmMsg)) return;
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            form.reportValidity();
            return;
        }
        var compoundRoot = form.matches('[data-compound-form]') ? form : form.querySelector('[data-compound-form]');
        if (compoundRoot) {
            var namedIngredients = 0;
            compoundRoot.querySelectorAll('[data-ingredient-row] [data-ingredient-name]').forEach(function (input) {
                if ((input.value || '').trim()) namedIngredients += 1;
            });
            if (namedIngredients < 1) {
                if (window.careSetStatus) window.careSetStatus('Tambahkan minimal satu bahan racikan.', true);
                return;
            }
        }
        form.dataset.busy = '1';
        var reopenTemplates = form.hasAttribute('data-rx-reopen-templates');
        var body = new FormData(form);
        var token = body.get('_token') || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        if (window.careSetStatus) window.careSetStatus('Menyimpan…');
        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: body
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    var message = window.careValidationMessage
                        ? window.careValidationMessage(data)
                        : ((data && data.message) || 'Gagal menyimpan');
                    throw new Error(message);
                }
                return data;
            });
        }).then(function (data) {
            if (data && data.html != null) replaceRxList(data.html);
            if (data && data.templates_html != null) replaceTemplatesList(data.templates_html);
            if (data && data.finish_html != null) replaceFinishRx(data.finish_html);
            if (data && data.section && window.careMarkDone) window.careMarkDone(data.section, data.done);
            var stayInTemplateList = form.closest('#rx-template-list') && String(form.action || '').indexOf('/apply') === -1;
            if (!stayInTemplateList) hideRxModal(form);
            resetRxForm(form);
            if (reopenTemplates) reopenTemplatesModal();
            if (window.careSetStatus) window.careSetStatus('✓ Tersimpan');
        }).catch(function (error) {
            if (window.careSetStatus) window.careSetStatus(error.message || 'Gagal menyimpan', true);
        }).finally(function () {
            form.dataset.busy = '0';
        });
    });

    function preview(form) {
        var body = form.querySelector('[data-compound-preview-body]');
        if (!body) return;
        var packs = form.querySelector('[data-pack-qty]') ? form.querySelector('[data-pack-qty]').value : '-';
        var lines = [];
        form.querySelectorAll('[data-ingredient-row]').forEach(function (row) {
            var name = (row.querySelector('[data-ingredient-name]') || {}).value || '(tanpa nama)';
            var per = (row.querySelector('[data-per-dose]') || {}).value;
            var total = (row.querySelector('[data-total-qty]') || {}).value || '-';
            var unit = (row.querySelector('[data-ingredient-unit]') || {}).value || '';
            var role = row.getAttribute('data-ingredient-role') === 'additive' ? 'tambahan' : 'bahan';
            var perText = per ? (per + ' ' + unit + '/bungkus × ' + packs + ' = ') : '';
            lines.push(role + ': ' + name + ' — ' + perText + 'total ' + total + (unit ? ' ' + unit : ''));
        });
        body.style.whiteSpace = 'pre-line';
        body.textContent = lines.join('\n') || 'Isi bahan untuk melihat total.';
    }

    function syncStrength(form) {
        var type = form.querySelector('[data-compound-type]');
        var dtd = type && type.value === 'SD';
        form.querySelectorAll('[data-ingredient-row]').forEach(function (row) {
            var additive = row.getAttribute('data-ingredient-role') === 'additive';
            row.querySelectorAll('[data-compound-strength-field]').forEach(function (input) {
                if (dtd && !additive) input.setAttribute('required', 'required');
                else input.removeAttribute('required');
            });
        });
        syncIngredientModalStrength(dtd);
    }

    function syncIngredientModalStrength(dtd) {
        var editor = document.querySelector('[data-ingredient-editor]');
        if (!editor) return;
        var role = (editor.querySelector('[data-ing-role]') || {}).value || 'active';
        var show = !!dtd && role !== 'additive';
        editor.querySelectorAll('[data-ing-strength-wrap]').forEach(function (wrap) {
            wrap.hidden = !show;
        });
        var strength = editor.querySelector('[data-ing-strength]');
        var strengthUnit = editor.querySelector('[data-ing-strength-unit]');
        if (strength) {
            if (show) strength.setAttribute('required', 'required');
            else strength.removeAttribute('required');
        }
        if (strengthUnit && !show) strengthUnit.removeAttribute('required');
    }

    function suggestTotal(form, onlyRow) {
        var packs = Number((form.querySelector('[data-pack-qty]') || {}).value || 0);
        form.querySelectorAll('[data-ingredient-row]').forEach(function (row) {
            if (onlyRow && row !== onlyRow) return;
            var per = row.querySelector('[data-per-dose]');
            var total = row.querySelector('[data-total-qty]');
            if (!per || !total || total.dataset.manual === '1') return;
            var amount = Number(per.value);
            if (per.value && packs > 0 && amount > 0) {
                total.value = String(Math.round(amount * packs));
                var flag = row.querySelector('[data-ingredient-qty-manual]');
                if (flag) flag.value = '0';
            }
            refreshIngredientDisplay(row);
        });
        preview(form);
    }

    function refreshIngredientDisplay(row) {
        if (!row) return;
        var name = ((row.querySelector('[data-ingredient-name]') || {}).value || '').trim();
        var unit = ((row.querySelector('[data-ingredient-unit]') || {}).value || '').trim();
        var per = ((row.querySelector('[data-per-dose]') || {}).value || '').trim();
        var total = ((row.querySelector('[data-total-qty]') || {}).value || '').trim() || '1';
        var role = row.getAttribute('data-ingredient-role') === 'additive' ? 'Tambahan' : 'Bahan obat';
        var nameEl = row.querySelector('[data-ingredient-display-name]');
        var detailEl = row.querySelector('[data-ingredient-display-detail]');
        if (nameEl) nameEl.textContent = name || 'Bahan belum diisi';
        if (detailEl) {
            var bits = [role];
            if (per) bits.push('Per bks ' + per + (unit ? ' ' + unit : ''));
            bits.push('Total ' + total + (unit ? ' ' + unit : ''));
            detailEl.textContent = bits.join(' · ');
        }
    }

    function syncIngredientEmpty(form) {
        if (!form) return;
        var empty = form.querySelector('[data-ingredient-empty]');
        var count = form.querySelectorAll('[data-ingredient-row]').length;
        if (empty) empty.hidden = count > 0;
    }

    function reindexIngredients(form) {
        if (!form) return;
        form.querySelectorAll('[data-ingredient-row]').forEach(function (item, index) {
            item.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/ingredients\[[^\]]+\]/, 'ingredients[' + index + ']');
            });
            refreshIngredientDisplay(item);
        });
        syncIngredientEmpty(form);
        syncStrength(form);
        preview(form);
    }

    var ingredientState = { form: null, row: null };

    function openIngredientModal(form, row, preset) {
        if (!form || !window.bootstrap) return;
        var modal = document.getElementById('rx-ingredient-modal');
        var editor = modal && modal.querySelector('[data-ingredient-editor]');
        if (!modal || !editor) return;
        ingredientState.form = form;
        ingredientState.row = row || null;
        var role = (preset && preset.role) || (row && row.getAttribute('data-ingredient-role')) || 'active';
        var title = modal.querySelector('.modal-title');
        if (title) title.textContent = row ? 'Ubah bahan' : (role === 'additive' ? 'Tambah bahan tambahan' : 'Tambah bahan obat');
        editor.querySelector('[data-ing-role]').value = role;
        editor.querySelector('[data-ing-additive-kind]').value = (preset && preset.additive_kind) || ((row && row.querySelector('[data-additive-kind]')) || {}).value || '';
        editor.querySelector('[data-ing-name]').value = (preset && preset.name) || ((row && row.querySelector('[data-ingredient-name]')) || {}).value || '';
        editor.querySelector('[data-ing-medicine]').value = ((row && row.querySelector('[data-ingredient-medicine]')) || {}).value || '';
        editor.querySelector('[data-ing-per]').value = ((row && row.querySelector('[data-per-dose]')) || {}).value || '';
        editor.querySelector('[data-ing-total]').value = ((row && row.querySelector('[data-total-qty]')) || {}).value || '1';
        editor.querySelector('[data-ing-unit]').value = (preset && preset.unit) || ((row && row.querySelector('[data-ingredient-unit]')) || {}).value || '';
        editor.querySelector('[data-ing-strength]').value = ((row && row.querySelector('[data-strength-value]')) || {}).value || '';
        editor.querySelector('[data-ing-strength-unit]').value = ((row && row.querySelector('[data-strength-unit]')) || {}).value || '';
        editor.querySelector('[data-ing-total]').dataset.manual = row && row.querySelector('[data-total-qty]') && row.querySelector('[data-total-qty]').dataset.manual === '1' ? '1' : '';
        var additiveHelpers = editor.querySelector('[data-ing-additive-helpers]');
        if (additiveHelpers) additiveHelpers.hidden = role !== 'additive';
        markAdditivePreset(editor.querySelector('[data-ing-additive-kind]').value || '');
        var type = form.querySelector('[data-compound-type]');
        syncIngredientModalStrength(type && type.value === 'SD');
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
        setTimeout(function () {
            var nameInput = editor.querySelector('[data-ing-name]');
            if (nameInput) nameInput.focus();
        }, 150);
    }

    function markAdditivePreset(kind) {
        var editor = document.querySelector('[data-ingredient-editor]');
        if (!editor) return;
        editor.querySelectorAll('[data-ing-preset-additive]').forEach(function (btn) {
            var on = String(btn.getAttribute('data-ing-preset-additive') || '') === String(kind || '');
            btn.classList.toggle('btn-primary', on);
            btn.classList.toggle('btn-light', !on);
            btn.classList.toggle('border', !on);
        });
    }

    function applyAdditivePreset(btn) {
        var editor = document.querySelector('[data-ingredient-editor]');
        if (!editor || !btn) return;
        var kind = btn.getAttribute('data-ing-preset-additive') || 'other';
        var name = btn.getAttribute('data-additive-name') || '';
        var unit = btn.getAttribute('data-additive-unit') || '';
        editor.querySelector('[data-ing-role]').value = 'additive';
        editor.querySelector('[data-ing-additive-kind]').value = kind;
        var nameInput = editor.querySelector('[data-ing-name]');
        var unitInput = editor.querySelector('[data-ing-unit]');
        if (nameInput && (kind !== 'other' || !nameInput.value)) nameInput.value = name;
        if (unitInput && unit) unitInput.value = unit;
        markAdditivePreset(kind);
        syncIngredientModalStrength(false);
        if (kind === 'other' && nameInput) nameInput.focus();
    }

    function saveIngredientModal() {
        var form = ingredientState.form;
        var editor = document.querySelector('[data-ingredient-editor]');
        if (!form || !editor) return;
        var name = (editor.querySelector('[data-ing-name]').value || '').trim();
        var total = editor.querySelector('[data-ing-total]').value;
        if (!name) {
            editor.querySelector('[data-ing-name]').focus();
            return;
        }
        if (!total || Number(total) < 1) {
            editor.querySelector('[data-ing-total]').focus();
            return;
        }
        var type = form.querySelector('[data-compound-type]');
        var dtd = type && type.value === 'SD';
        var role = editor.querySelector('[data-ing-role]').value || 'active';
        if (dtd && role !== 'additive' && !(editor.querySelector('[data-ing-strength]').value || '').trim()) {
            editor.querySelector('[data-ing-strength]').focus();
            return;
        }

        var row = ingredientState.row;
        if (!row) {
            var tpl = form.querySelector('[data-ingredient-template]');
            var list = form.querySelector('[data-ingredient-list]');
            if (!tpl || !list) return;
            var index = list.querySelectorAll('[data-ingredient-row]').length;
            var wrap = document.createElement('div');
            wrap.innerHTML = tpl.innerHTML.split('__i__').join(String(index)).trim();
            row = wrap.firstElementChild;
            if (!row) return;
            list.appendChild(row);
        }

        row.setAttribute('data-ingredient-role', role);
        var roleInput = row.querySelector('[data-ingredient-role-input]');
        var kindInput = row.querySelector('[data-additive-kind]');
        var nameInput = row.querySelector('[data-ingredient-name]');
        var medicineInput = row.querySelector('[data-ingredient-medicine]');
        var perInput = row.querySelector('[data-per-dose]');
        var totalInput = row.querySelector('[data-total-qty]');
        var unitInput = row.querySelector('[data-ingredient-unit]');
        var strengthInput = row.querySelector('[data-strength-value]');
        var strengthUnitInput = row.querySelector('[data-strength-unit]');
        if (roleInput) roleInput.value = role;
        if (kindInput) kindInput.value = editor.querySelector('[data-ing-additive-kind]').value || '';
        if (nameInput) nameInput.value = name;
        if (medicineInput) medicineInput.value = editor.querySelector('[data-ing-medicine]').value || '';
        if (perInput) perInput.value = editor.querySelector('[data-ing-per]').value || '';
        if (totalInput) {
            totalInput.value = total;
            totalInput.dataset.manual = editor.querySelector('[data-ing-total]').dataset.manual || '';
            var totalFlag = row.querySelector('[data-ingredient-qty-manual]');
            if (totalFlag) totalFlag.value = totalInput.dataset.manual === '1' ? '1' : '0';
        }
        if (unitInput) unitInput.value = editor.querySelector('[data-ing-unit]').value || '';
        if (strengthInput) strengthInput.value = editor.querySelector('[data-ing-strength]').value || '';
        if (strengthUnitInput) strengthUnitInput.value = editor.querySelector('[data-ing-strength-unit]').value || '';

        reindexIngredients(form);
        var modal = document.getElementById('rx-ingredient-modal');
        if (modal && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        ingredientState = { form: null, row: null };
    }

    document.addEventListener('click', function (event) {
        var sig = event.target.closest('[data-sig-fill]');
        if (sig) {
            var holder = sig.closest('[data-sig-for], [data-sig-name]');
            var sigName = sig.getAttribute('data-sig-name') || (holder && holder.getAttribute('data-sig-name')) || '';
            var sigMode = sig.getAttribute('data-sig-mode') || (holder && holder.getAttribute('data-sig-mode')) || '';
            if (!holder && !sigName && !sig.getAttribute('data-sig-for')) return;
            var fillValue = sig.getAttribute('data-sig-fill') || '';
            var row = (holder && holder.closest('[data-rx-row], [data-rx-draft]'))
                || document.querySelector('[data-rx-row].rx-row--active, [data-rx-draft].rx-row--active');
            var input = null;
            if (holder && holder.hasAttribute('data-sig-for')) {
                input = document.getElementById(holder.getAttribute('data-sig-for'));
            } else if (row && sigName) {
                var tipNode = (holder && holder.closest('.rx-sig-tip')) || row.querySelector('.rx-sig-tip[data-sig-name="' + sigName + '"]');
                var fieldWrap = tipNode ? tipNode.closest('.rx-sig-field') : null;
                input = (fieldWrap || row).querySelector('[name="' + sigName + '"]');
            }
            var effect = sig.getAttribute('data-sig-effect') || '';
            var scope = row || (input && input.closest('form.rx-form'));
            if (scope && (effect === 'frequency' || effect === 'prn' || (row && sigMode === 'signa'))) {
                rxApplySig(scope, sig, fillValue, effect || 'frequency');
                if (row && row.matches('[data-rx-row]')) row.dataset.dirty = '1';
                if (row) rxScheduleAutoSave(row);
                rxHideAllSigPopovers();
                return;
            }
            if (input) {
                if (row && (effect === 'duration' || sigName === 'jho')) {
                    var daysFromHelp = sig.getAttribute('data-sig-days') || rxParseDays(fillValue) || rxParseAmount(fillValue);
                    if (daysFromHelp) input.value = String(daysFromHelp);
                    var course = row.querySelector('[name="course"]');
                    if (course) course.value = fillValue;
                } else if (row && (effect === 'prn' || effect === 'until_finished')) {
                    input.value = fillValue;
                    var coursePrn = row.querySelector('[name="course"]');
                    if (coursePrn && effect === 'until_finished') coursePrn.value = fillValue;
                } else if (effect === 'dose' && input && String(input.value || '').trim() !== '') {
                    /* dosis yang sudah diisi dokter tidak ditimpa */
                } else {
                    input.value = fillValue;
                }
                if (row) rxClearQtyManual(row);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                if (row) rxAutoQty(row);
                rxScheduleAutoSave(row);
            }
            rxHideAllSigPopovers();
            return;
        }

        var additivePreset = event.target.closest('[data-ing-preset-additive]');
        if (additivePreset) {
            applyAdditivePreset(additivePreset);
            return;
        }

        if (event.target.closest('[data-ingredient-save]')) {
            saveIngredientModal();
            return;
        }

        var add = event.target.closest('[data-add-ingredient], [data-add-additive]');
        if (add) {
            var form = add.closest('[data-compound-form]');
            if (!form) return;
            var preset = null;
            if (add.hasAttribute('data-add-additive')) {
                preset = {
                    role: 'additive',
                    additive_kind: add.getAttribute('data-add-additive') || '',
                    name: add.getAttribute('data-additive-name') || '',
                    unit: add.getAttribute('data-additive-unit') || '',
                };
            }
            openIngredientModal(form, null, preset);
            return;
        }

        var edit = event.target.closest('[data-edit-ingredient]');
        if (edit) {
            var editRow = edit.closest('[data-ingredient-row]');
            var editForm = edit.closest('[data-compound-form]');
            if (editRow && editForm) openIngredientModal(editForm, editRow, null);
            return;
        }

        var remove = event.target.closest('[data-remove-ingredient]');
        if (remove) {
            var row = remove.closest('[data-ingredient-row]');
            var form = remove.closest('[data-compound-form]');
            if (!row || !form) return;
            if (!window.confirm('Hapus bahan ini dari racikan?')) return;
            row.remove();
            reindexIngredients(form);
            return;
        }

        function hydrateTemplateItemHtml(html, index) {
            return String(html || '')
                .split('items[__i__]').join('items[' + index + ']')
                .replace(/tpl-new-__i__/g, 'tpl-new-' + index)
                .replace(/tpl-cmp-__i__/g, 'tpl-cmp-' + index)
                .replace(/tpl-cmp-rx-(\d+)-__i__/g, 'tpl-cmp-rx-$1-' + index)
                .replace(/tpl-cmp-(\d+)-__i__/g, 'tpl-cmp-$1-' + index)
                .replace(/tpl-rx-(\d+)-__i__/g, 'tpl-rx-$1-' + index)
                .replace(/tpl-(\d+)-__i__/g, 'tpl-$1-' + index);
        }

        function appendTemplateItem(editor, templateSelector) {
            var tpl = editor && editor.querySelector(templateSelector);
            var list = editor && editor.querySelector('[data-template-rows]');
            if (!tpl || !list) return;
            var next = list.querySelectorAll('[data-template-row]').length;
            var holder = document.createElement('div');
            holder.innerHTML = hydrateTemplateItemHtml(tpl.innerHTML, next).trim();
            var added = holder.firstElementChild;
            if (!added) return;
            list.appendChild(added);
            added.dispatchEvent(new CustomEvent('care:bind-typeahead', { bubbles: true }));
            var compound = added.matches('[data-compound-form]') ? added : added.querySelector('[data-compound-form]');
            if (compound) {
                syncStrength(compound);
                preview(compound);
            }
        }

        var addRow = event.target.closest('[data-add-template-row]');
        if (addRow) {
            appendTemplateItem(addRow.closest('[data-template-editor]'), '[data-template-row-template]');
            return;
        }

        var addCompound = event.target.closest('[data-add-template-compound]');
        if (addCompound) {
            appendTemplateItem(addCompound.closest('[data-template-editor]'), '[data-template-compound-template]');
            return;
        }

        var removeRow = event.target.closest('[data-remove-template-row]');
        if (removeRow) {
            var templateRow = removeRow.closest('[data-template-row]');
            var rows = templateRow && templateRow.parentElement;
            if (!templateRow || !rows || rows.querySelectorAll('[data-template-row]').length <= 1) return;
            templateRow.remove();
            rows.querySelectorAll('[data-template-row]').forEach(function (item, index) {
                item.querySelectorAll('[name]').forEach(function (input) {
                    input.name = input.name.replace(/items\[[^\]]+\]/, 'items[' + index + ']');
                });
                item.querySelectorAll('[id]').forEach(function (el) {
                    el.id = el.id
                        .replace(/tpl-new-\d+/g, 'tpl-new-' + index)
                        .replace(/tpl-cmp-\d+/g, 'tpl-cmp-' + index)
                        .replace(/tpl-cmp-rx-(\d+)-\d+/g, 'tpl-cmp-rx-$1-' + index)
                        .replace(/tpl-cmp-(\d+)-\d+/g, 'tpl-cmp-$1-' + index)
                        .replace(/tpl-rx-(\d+)-\d+/g, 'tpl-rx-$1-' + index)
                        .replace(/tpl-(\d+)-\d+/g, 'tpl-$1-' + index);
                });
            });
        }
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-ing-total]')) {
            event.target.dataset.manual = '1';
            return;
        }
        if (event.target.matches('[data-ing-per]')) {
            var editor = event.target.closest('[data-ingredient-editor]');
            var packsInput = ingredientState.form && ingredientState.form.querySelector('[data-pack-qty]');
            var packs = Number((packsInput || {}).value || 0);
            var total = editor && editor.querySelector('[data-ing-total]');
            if (total) total.dataset.manual = '';
            var amount = Number(event.target.value);
            if (total && event.target.value && packs > 0 && amount > 0) total.value = String(Math.round(amount * packs));
            return;
        }

        var form = event.target.closest('[data-compound-form]');
        if (!form) return;
        if (event.target.matches('[data-pack-qty]')) {
            suggestTotal(form);
        }
    });

    document.addEventListener('change', function (event) {
        var sigSelect = event.target.closest('[data-sig-select]');
        if (sigSelect) {
            var target = document.getElementById(sigSelect.getAttribute('data-sig-select'));
            if (target && sigSelect.value) target.value = sigSelect.value;
            sigSelect.value = '';
        }
        var form = event.target.closest('[data-compound-form]');
        if (form && event.target.matches('[data-compound-type]')) syncStrength(form);

        if (event.target.matches('[data-ing-medicine]')) {
            var option = event.target.options[event.target.selectedIndex];
            var editor = event.target.closest('[data-ingredient-editor]');
            if (!option || !editor || !option.value) return;
            var name = editor.querySelector('[data-ing-name]');
            var unit = editor.querySelector('[data-ing-unit]');
            var strength = editor.querySelector('[data-ing-strength]');
            var strengthUnit = editor.querySelector('[data-ing-strength-unit]');
            if (name && !name.value && option.dataset.name) name.value = option.dataset.name;
            if (unit && !unit.value && option.dataset.unit) unit.value = option.dataset.unit;
            if (strength && option.dataset.strengthValue) strength.value = option.dataset.strengthValue;
            if (strengthUnit && option.dataset.strengthUnit) strengthUnit.value = option.dataset.strengthUnit;
        }

        if (event.target.matches('[data-template-medicine-id]')) {
            var catalogNode = document.getElementById('rx-medicine-catalog');
            var catalog = [];
            try { catalog = JSON.parse(catalogNode ? catalogNode.textContent || '[]' : '[]'); } catch (error) { catalog = []; }
            var picked = catalog.find(function (item) { return String(item.id) === String(event.target.value); });
            var unitField = event.target.closest('[data-template-row]') && event.target.closest('[data-template-row]').querySelector('[data-template-unit]');
            if (unitField && picked && picked.unit) unitField.value = picked.unit;
        }
    });

    document.querySelectorAll('[data-compound-form]').forEach(function (form) {
        syncStrength(form);
        syncIngredientEmpty(form);
        form.querySelectorAll('[data-ingredient-row]').forEach(refreshIngredientDisplay);
        preview(form);
    });

    function rxCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function rxMoney(value) {
        var num = Number(value || 0);
        return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function rxField(row, name) {
        var input = row.querySelector('[name="' + name + '"]');
        return input ? String(input.value || '').trim() : '';
    }

    function rxClearSigna(row) {
        ['signa_p', 'signa_s', 'signa_sr', 'signa_m'].forEach(function (name) {
            var input = row.querySelector('[name="' + name + '"]');
            if (input) input.value = '';
        });
    }

    function rxFillFrequencySigna(row, times, dose) {
        var pattern = { 1: [1, 0, 0, 0], 2: [1, 0, 0, 1], 3: [1, 1, 0, 1], 4: [1, 1, 1, 1] }[times];
        if (!pattern) return false;
        ['signa_p', 'signa_s', 'signa_sr', 'signa_m'].forEach(function (name, index) {
            var input = row.querySelector('[name="' + name + '"]');
            if (!input) return;
            input.value = pattern[index] ? rxTrimNum(dose) : '';
        });
        return true;
    }

    function rxClearQtyManual(row) {
        var qty = row.querySelector('[data-rx-qty]');
        var flag = row.querySelector('[name="quantity_manual"]');
        if (qty) delete qty.dataset.manual;
        if (flag) flag.value = '0';
    }

    function rxApplySig(row, sig, fillValue, effect) {
        var freq = row.querySelector('[data-rx-frequency]') || row.querySelector('[name="frequency"]');
        if (effect === 'prn' || effect === 'until_finished') {
            var waktu = row.querySelector('[name="waktu"]');
            if (waktu) waktu.value = fillValue;
            var course = row.querySelector('[name="course"]');
            if (course && effect === 'until_finished') course.value = fillValue;
            if (freq && effect === 'prn') freq.value = fillValue;
            rxClearSigna(row);
        } else {
            if (freq) freq.value = fillValue;
            var dose = Number(sig.getAttribute('data-sig-dose') || 1);
            if (!dose || dose <= 0) dose = 1;
            var times = Number(sig.getAttribute('data-sig-times') || 0);
            var dosage = row.querySelector('[name="dosage"]');
            if (sig.getAttribute('data-sig-dose') && dosage && !String(dosage.value || '').trim()) dosage.value = String(dose);
            if (!rxFillFrequencySigna(row, times, dose)) rxClearSigna(row);
        }
        rxClearQtyManual(row);
        rxAutoQty(row);
    }

    function rxSyncSigna(row) {
        return rxField(row, 'frequency');
    }

    function rxTrimNum(value) {
        var text = String(Math.round(value * 100) / 100);
        return text.replace(/\.0+$/, '').replace(/(\.\d*[1-9])0+$/, '$1');
    }

    function rxBlob(row) {
        return ['dosage', 'frequency', 'duration', 'course', 'waktu', 'notes'].map(function (name) {
            var text = rxField(row, name);
            return text && text !== '-' ? text : '';
        }).join(' ').toLowerCase();
    }

    function rxIsPrn(text) {
        return /\bprn\b|\bsos\b|bila perlu|jika diperlukan|pro re nata|si opus sit/.test(text);
    }

    function rxIsUntilFinished(text) {
        return /sampai\s*habis|s\s*\/\s*d\s*habis|\bsd\s*habis/.test(text);
    }

    function rxDays(row) {
        var jho = rxParseAmount(rxField(row, 'jho'));
        if (jho && jho > 0) return Math.round(jho);
        var keys = ['duration', 'course', 'notes'];
        for (var i = 0; i < keys.length; i++) {
            var text = rxField(row, keys[i]);
            if (!text || text === '-' || rxIsUntilFinished(text.toLowerCase()) || rxIsPrn(text.toLowerCase())) continue;
            var match = text.match(/^(\d+)\s*hari$/i);
            if (match) return Number(match[1]);
        }
        return null;
    }

    function rxSlots(row) {
        var names = ['signa_p', 'signa_s', 'signa_sr', 'signa_m'];
        var filled = false;
        var slots = names.map(function (name) {
            var raw = rxField(row, name);
            if (!raw) return null;
            filled = true;
            return rxParseAmount(raw);
        });
        if (filled) return slots;
        var frequency = rxField(row, 'frequency');
        if (/^(\d+(?:[.,]\d+)?)(?:\s*-\s*(\d+(?:[.,]\d+)?)){3}$/.test(frequency)) {
            return frequency.split(/\s*-\s*/).map(rxParseAmount);
        }
        return null;
    }

    function rxAssess(row) {
        var text = rxBlob(row);
        var prn = rxIsPrn(text);
        var untilFinished = rxIsUntilFinished(text);
        var days = rxDays(row);
        var slots = rxSlots(row);
        var unit = rxField(row, 'unit').toLowerCase();
        var kindInput = row.querySelector('[name="kind"]');
        var kind = (row.getAttribute('data-kind') || (kindInput && kindInput.value) || 'ready').toLowerCase();
        var dosage = rxField(row, 'dosage');
        var dosageLower = dosage.toLowerCase();
        var mlMatch = dosage.match(/(\d+(?:[.,]\d+)?)\s*ml\b/i);
        var ml = mlMatch ? rxParseAmount(mlMatch[1]) : null;
        var compound = kind === 'racikan' || kind === 'compound';
        var liquidUnit = unit === 'ml' || unit === 'sirup' || dosageLower.indexOf('sirup') !== -1 || dosageLower.indexOf('sediaan cair') !== -1;
        var form = { countable: false, liquid: false, hasMl: false, label: 'tablet' };
        if (ml != null) {
            form = { countable: false, liquid: true, hasMl: true, label: 'ml' };
        } else if (compound && (dosageLower.indexOf('salep') !== -1 || dosageLower.indexOf('tetes') !== -1 || dosageLower.indexOf('krim') !== -1)) {
            form = { countable: false, liquid: false, hasMl: false, label: unit };
        } else if (compound && !liquidUnit) {
            form = { countable: true, liquid: false, hasMl: false, label: 'bungkus' };
        } else if (liquidUnit) {
            form = { countable: false, liquid: true, hasMl: false, label: 'ml' };
        } else if (['salep', 'krim', 'tetes', 'tetes_mata', 'tetes_telinga', 'inhaler', 'semprot', 'injeksi', 'infus', 'nebul', 'botol', 'tube', 'ampul'].indexOf(unit) !== -1) {
            form = { countable: false, liquid: false, hasMl: false, label: unit };
        } else if (['tablet', 'tab', 'kapsul', 'kaplet', 'sachet', 'bungkus', 'suppositoria', 'unit', 'pcs', ''].indexOf(unit) !== -1) {
            var labels = { kapsul: 'kapsul', kaplet: 'kaplet', sachet: 'sachet', bungkus: 'bungkus', suppositoria: 'suppositoria' };
            form = { countable: true, liquid: false, hasMl: false, label: labels[unit] || 'tablet' };
        }

        var base = { mode: 'incomplete', quantity: null, formula: '', liquid: form.liquid };
        if (prn) {
            base.mode = 'manual';
            base.formula = 'PRN (bila perlu): Qty tidak dihitung otomatis. Isi jumlah yang disetujui dokter.';
            return base;
        }
        if (!form.countable && !form.liquid) {
            base.mode = 'manual';
            base.formula = 'Sediaan ini tidak memakai rumus tablet. Isi Qty sesuai instruksi dokter.';
            return base;
        }
        if (form.liquid && !form.hasMl) {
            base.mode = 'manual';
            base.formula = 'Obat cair dihitung dalam ml. Isi dosis ml, atau tentukan Qty manual.';
            return base;
        }
        if (untilFinished && !days) {
            base.mode = 'manual';
            base.formula = 'Sampai habis: isi jumlah hari obat (JHO) agar Qty terhitung, atau tentukan Qty manual.';
            return base;
        }

        var daily = null;
        var explain = '';
        if (slots) {
            var sum = 0;
            var administrations = 0;
            var shown = [];
            var labelsSlot = ['Pagi', 'Siang', 'Sore', 'Malam'];
            slots.forEach(function (slot, index) {
                if (slot && slot > 0) {
                    sum += slot;
                    administrations += 1;
                }
                shown.push(labelsSlot[index] + ' ' + rxTrimNum(slot || 0));
            });
            if (ml != null && administrations > 0) {
                daily = ml * administrations;
                explain = rxTrimNum(ml) + ' ml × ' + administrations + ' kali (' + shown.join(', ') + ')';
            } else if (sum > 0) {
                daily = sum;
                explain = '(' + slots.map(function (slot) { return rxTrimNum(slot || 0); }).join(' + ') + ')';
            }
        } else {
            var frequency = rxField(row, 'frequency');
            var times = null;
            var paired = null;
            var pair = frequency.match(/(\d+(?:[.,]\d+)?)\s*[x×]\s*(\d+(?:[.,]\d+)?)/i);
            var dailyWord = frequency.match(/(\d+)\s*[x×]\s*sehari/i);
            var interval = frequency.match(/tiap\s+(\d+)\s*jam/i);
            if (pair) {
                times = Math.round(Number(String(pair[1]).replace(',', '.')));
                paired = rxParseAmount(pair[2]);
            } else if (dailyWord) {
                times = Number(dailyWord[1]);
            } else if (interval) {
                var hours = Number(interval[1]);
                if (hours > 0 && 24 % hours === 0) times = 24 / hours;
            }
            var dose = ml != null ? ml : (rxParseAmount(dosage) != null ? rxParseAmount(dosage) : paired);
            var looksLiquid = unit === 'ml' || unit === 'sirup' || dosageLower.indexOf('ml') !== -1 || dosageLower.indexOf('sirup') !== -1;
            if (dose == null && times && ml == null && !looksLiquid) dose = paired != null ? paired : 1;
            if (dose != null && times) daily = dose * times;
            explain = rxTrimNum(dose || 0) + (ml != null ? ' ml' : '') + ' × ' + (times || 0) + ' kali/hari';
        }

        if (!daily || daily <= 0 || !days) {
            base.formula = 'Qty = dosis tiap kali × frekuensi per hari × jumlah hari obat.';
            return base;
        }
        var quantity = Math.ceil(Math.round(daily * days * 10000) / 10000);
        base.mode = 'calculated';
        base.quantity = quantity;
        base.liquid = form.liquid;
        base.formula = explain + ' × ' + days + ' hari = ' + quantity + ' ' + form.label;
        return base;
    }

    function rxShowQtyHint(row, result) {
        var hint = row.querySelector('[data-rx-qty-hint]');
        var qty = row.querySelector('[data-rx-qty]');
        if (!hint) return;
        var manual = qty && qty.dataset.manual === '1';
        hint.classList.remove('is-warn');
        if (result.mode === 'calculated' && qty && Number(qty.value) !== result.quantity) {
            hint.classList.add('is-warn');
            hint.textContent = result.formula + (manual
                ? '. Selisih dengan Qty yang diketik. Simpan untuk mengunci Qty manual.'
                : '. Qty saat ini berbeda dari rumus.');
            return;
        }
        hint.textContent = result.formula || '';
    }

    function rxPriceOf(row) {
        var priceText = row.querySelector('[data-rx-price]');
        if (!priceText) return 0;
        if (priceText.dataset.raw != null && priceText.dataset.raw !== '') {
            return Number(priceText.dataset.raw) || 0;
        }
        return Number(String(priceText.textContent || '0').replace(/\./g, '').replace(',', '.')) || 0;
    }

    function rxParseAmount(value) {
        var text = String(value || '').trim().replace(',', '.');
        if (!text) return null;
        if (/^(½|1\/2)/.test(text)) return 0.5;
        if (/^(¼|1\/4)/.test(text)) return 0.25;
        if (/^(¾|3\/4)/.test(text)) return 0.75;
        var frac = text.match(/^(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)/);
        if (frac) {
            var den = Number(frac[2]);
            return den ? Number(frac[1]) / den : null;
        }
        var num = text.match(/^(\d+(?:\.\d+)?)/);
        return num ? Number(num[1]) : null;
    }

    function rxParseDays(value) {
        var text = String(value || '').trim().toLowerCase();
        if (!text) return null;
        var match = text.match(/(\d+)\s*hari/);
        if (match) return Number(match[1]);
        if (/^(\d+)$/.test(text)) return Number(text);
        return null;
    }

    function rxAutoQty(row) {
        if (!row) return;
        var qtyInput = row.querySelector('[data-rx-qty]');
        var result = rxAssess(row);
        rxShowQtyHint(row, result);
        if (!qtyInput || qtyInput.dataset.manual === '1' || result.mode !== 'calculated' || !result.quantity) {
            rxRecalcRow(row);
            return;
        }
        var qty = result.quantity;
        if (qty < 1) qty = 1;
        if (qty > 1000) qty = 1000;
        if (String(qtyInput.value) !== String(qty)) qtyInput.value = String(qty);
        var flag = row.querySelector('[name="quantity_manual"]');
        if (flag) flag.value = '0';
        if (result.liquid) {
            var unit = row.querySelector('[name="unit"]');
            if (unit) {
                if (!unit.querySelector('option[value="ml"]')) {
                    var option = document.createElement('option');
                    option.value = 'ml';
                    option.textContent = 'ml';
                    unit.appendChild(option);
                }
                unit.value = 'ml';
            }
        }
        row.querySelectorAll('[data-ing-qty]').forEach(function (ing) {
            if (ing.dataset.manual === '1') return;
            ing.value = String(qty);
        });
        rxRecalcRow(row);
    }

    function rxRecalcRow(row) {
        var qtyInput = row.querySelector('[data-rx-qty]');
        var qty = Number(qtyInput ? qtyInput.value : 0) || 0;
        var price = rxPriceOf(row);
        var sub = row.querySelector('[data-rx-subtotal]');
        if (sub) sub.textContent = rxMoney(qty * price);
        rxRecalcEstimate();
    }

    function rxRecalcEstimate() {
        var total = 0;
        document.querySelectorAll('[data-rx-row], [data-rx-draft]').forEach(function (row) {
            var qtyInput = row.querySelector('[data-rx-qty]');
            var qty = Number(qtyInput ? qtyInput.value : 0) || 0;
            total += qty * rxPriceOf(row);
        });
        var target = document.querySelector('[data-rx-estimate]');
        if (target) target.textContent = 'Rp ' + rxMoney(total);
    }

    function rxCollectRowPayload(row) {
        var qtyInput = row.querySelector('[data-rx-qty]');
        if (!qtyInput || qtyInput.dataset.manual !== '1') rxAutoQty(row);
        var result = rxAssess(row);
        if (qtyInput && result.mode === 'manual') {
            qtyInput.dataset.manual = '1';
            var manualFlag = row.querySelector('[name="quantity_manual"]');
            if (manualFlag) manualFlag.value = '1';
        }
        var data = {
            _token: rxCsrf(),
            _method: 'PUT',
            _form: 'rx-editor-row',
            quantity: 1,
            medication_type: 'NC'
        };
        row.querySelectorAll('[data-rx-field], [data-rx-frequency], [data-rx-medicine-id], input[type="hidden"][name], select[name]').forEach(function (input) {
            if (!input.name || input.disabled) return;
            data[input.name] = input.value;
        });
        if (qtyInput && result.mode === 'calculated' && qtyInput.dataset.manual !== '1' && result.quantity) {
            data.quantity = String(result.quantity);
            data.quantity_manual = '0';
        }
        var jho = Number(data.jho || 0);
        if (jho > 0 && (!data.duration || data.duration === '-')) data.duration = jho + ' hari';
        if (!data.dosage || data.dosage === '-') data.dosage = '';
        if (!data.frequency || data.frequency === '-') data.frequency = '';
        var priceEl = row.querySelector('[data-rx-price]');
        if (priceEl && priceEl.dataset.raw != null && priceEl.dataset.raw !== '' && data.unit_price == null) {
            data.unit_price = priceEl.dataset.raw;
        }
        return data;
    }

    function rxSaveRow(row, options) {
        options = options || {};
        var url = row.getAttribute('data-update');
        if (!url || row.dataset.busy === '1') return Promise.resolve();
        if (!rxRowShouldSave(row)) return Promise.resolve();
        row.dataset.busy = '1';
        var payload = rxCollectRowPayload(row);
        var body = new FormData();
        Object.keys(payload).forEach(function (key) {
            if (payload[key] != null) body.append(key, payload[key]);
        });
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': rxCsrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (payload) {
                if (!res.ok) {
                    var msg = (payload && payload.message) || (payload && payload.errors && Object.values(payload.errors)[0] && Object.values(payload.errors)[0][0]) || 'Gagal menyimpan baris resep';
                    throw new Error(msg);
                }
                row.dataset.dirty = '0';
                if (!options.silent && payload.html != null) replaceRxList(payload.html);
                if (payload.finish_html != null) replaceFinishRx(payload.finish_html);
            });
        }).finally(function () {
            row.dataset.busy = '0';
        });
    }

    var rxAutoTimers = new WeakMap();

    function rxMedicineIdOf(row) {
        var el = row && row.querySelector('[data-rx-medicine-id]');
        return el ? String(el.value || '').trim() : '';
    }

    function rxDraftReadyToSave(row) {
        if (!row) return false;
        var kind = row.getAttribute('data-kind') || 'ready';
        if (kind === 'racikan') {
            var named = 0;
            row.querySelectorAll('[data-rx-ingredient]').forEach(function (ing) {
                var name = (ing.querySelector('[data-ing-name]') || {}).value
                    || (ing.querySelector('[data-typeahead-q]') || {}).value
                    || '';
                if (String(name).trim()) named += 1;
            });
            return named >= 1;
        }
        return !!rxMedicineIdOf(row);
    }

    function rxRowShouldSave(row) {
        if (!row || !row.isConnected) return false;
        if (row.matches('[data-rx-draft]')) return rxDraftReadyToSave(row);
        if (!row.matches('[data-rx-row]')) return false;
        var kind = row.getAttribute('data-kind') || 'ready';
        if (kind === 'racikan') return true;
        // Baris non-racik tanpa obat = kosong → jangan disimpan.
        if (row.querySelector('[data-rx-medicine-id]')) return !!rxMedicineIdOf(row);
        return true;
    }

    function rxCaptureFocusState(row) {
        var active = document.activeElement;
        if (!active || !row.contains(active)) {
            return { focusName: null, selectionStart: null };
        }
        return {
            focusName: active.getAttribute('name') || null,
            selectionStart: typeof active.selectionStart === 'number' ? active.selectionStart : null,
        };
    }

    function rxScheduleAutoSave(row) {
        if (!row) return;
        var prev = rxAutoTimers.get(row);
        if (prev) clearTimeout(prev);
        // Baris kosong (tanpa obat / tanpa bahan) tidak dijadwalkan sama sekali.
        if (!rxRowShouldSave(row)) {
            rxAutoTimers.delete(row);
            return;
        }
        var timer = setTimeout(function () {
            rxAutoTimers.delete(row);
            if (!rxRowShouldSave(row) || row.dataset.busy === '1') return;
            if (row.matches('[data-rx-draft]')) {
                var focusState = rxCaptureFocusState(row);
                if (window.careSetStatus) window.careSetStatus('Menyimpan…');
                rxSaveDraftRow(row, {
                    background: true,
                    focusItemId: true,
                    focusName: focusState.focusName,
                    selectionStart: focusState.selectionStart,
                }).then(function () {
                    if (window.careSetStatus) window.careSetStatus('✓ Tersimpan');
                }).catch(function () {
                    if (window.careSetStatus) window.careSetStatus('Gagal menyimpan', true);
                });
                return;
            }
            if (row.matches('[data-rx-row]')) {
                if (window.careSetStatus) window.careSetStatus('Menyimpan…');
                rxSaveRow(row, { silent: true, background: true }).then(function () {
                    if (window.careSetStatus) window.careSetStatus('✓ Tersimpan');
                }).catch(function () {
                    if (window.careSetStatus) window.careSetStatus('Gagal menyimpan', true);
                });
            }
        }, 1200);
        rxAutoTimers.set(row, timer);
    }

    function rxPromoteDraftRow(row, payload) {
        if (!row || !payload || !payload.item_id) return false;
        var kind = row.getAttribute('data-kind') || 'ready';
        if (kind === 'racikan') return false;

        var editor = document.querySelector('[data-rx-editor]');
        var storeUrl = editor ? editor.getAttribute('data-store') : '';
        if (!storeUrl) return false;
        var itemUrl = storeUrl.replace(/\/?$/, '') + '/' + payload.item_id;

        row.classList.remove('rx-row--draft');
        row.removeAttribute('data-rx-draft');
        row.setAttribute('data-rx-row', '');
        row.setAttribute('data-item-id', payload.item_id);
        row.setAttribute('data-update', itemUrl);
        row.setAttribute('data-destroy', itemUrl);
        row.dataset.dirty = '0';
        row.dataset.busy = '0';

        var actions = row.querySelector('td:last-child');
        if (actions) {
            actions.innerHTML = '<button type="button" class="btn btn-sm btn-danger" data-rx-delete title="Hapus"><i class="bx bx-trash"></i></button>';
        }

        if (payload.finish_html != null) replaceFinishRx(payload.finish_html);
        rxEnsureEmptyReadyDraft();
        return true;
    }

    var rxSigOpenToggle = null;
    var rxSigIgnoreToggleUntil = 0;
    var rxSigShowToken = 0;

    function rxHideAllSigPopovers(exceptToggle) {
        document.querySelectorAll('[data-rx-sig-popover]').forEach(function (toggle) {
            if (exceptToggle && toggle === exceptToggle) return;
            toggle.setAttribute('aria-expanded', 'false');
            var field = toggle.closest('.rx-sig-field');
            if (field) field.classList.remove('is-sig-open');
            if (!window.bootstrap || !window.bootstrap.Popover) return;
            var inst = window.bootstrap.Popover.getInstance(toggle);
            if (inst) inst.hide();
        });
        if (!exceptToggle || rxSigOpenToggle !== exceptToggle) rxSigOpenToggle = exceptToggle || null;
    }

    function rxSigPopoverInstance(toggle) {
        if (!toggle || !window.bootstrap || !window.bootstrap.Popover) return null;
        var existing = window.bootstrap.Popover.getInstance(toggle);
        if (existing) return existing;
        var tip = toggle.closest('.rx-sig-tip');
        var template = tip && tip.querySelector('.rx-sig-tip__template');
        return window.bootstrap.Popover.getOrCreateInstance(toggle, {
            html: true,
            sanitize: false,
            trigger: 'manual',
            placement: 'bottom',
            container: 'body',
            customClass: 'rx-sig-popover',
            content: template ? template.innerHTML : '',
        });
    }

    function rxShowSigPopover(toggle) {
        if (!toggle) return;
        if (rxSigOpenToggle === toggle && toggle.getAttribute('aria-describedby')) return;

        var field = toggle.closest('.rx-sig-field');
        document.querySelectorAll('.rx-sig-field.is-sig-open').forEach(function (el) {
            if (field && el === field) return;
            el.classList.remove('is-sig-open');
        });
        if (field) field.classList.add('is-sig-open');
        toggle.setAttribute('aria-expanded', 'true');
        rxHideAllSigPopovers(toggle);
        rxSigOpenToggle = toggle;
        rxSigIgnoreToggleUntil = Date.now() + 400;

        var token = ++rxSigShowToken;
        requestAnimationFrame(function () {
            if (token !== rxSigShowToken || rxSigOpenToggle !== toggle) return;
            var inst = rxSigPopoverInstance(toggle);
            if (inst) inst.show();
        });
    }

    function rxShowSigPopoverForSource(row, sourceEl) {
        if (!row) {
            rxHideAllSigPopovers();
            return;
        }
        if (sourceEl && sourceEl.closest && sourceEl.closest('.rx-sig-popover, [data-sig-fill], [data-rx-sig-popover]')) {
            return;
        }
        var field = sourceEl && sourceEl.closest ? sourceEl.closest('.rx-sig-field') : null;
        if (field && row.contains(field)) {
            var toggle = field.querySelector('[data-rx-sig-popover]');
            if (toggle) {
                rxShowSigPopover(toggle);
                return;
            }
        }
        if (!field) rxHideAllSigPopovers();
    }

    function rxSetActiveRow(row, sourceEl, options) {
        options = options || {};
        var sameRow = !!(row && row.classList.contains('rx-row--active'));
        if (!sameRow) {
            document.querySelectorAll('[data-rx-row].rx-row--active, [data-rx-draft].rx-row--active').forEach(function (el) {
                el.classList.remove('rx-row--active');
            });
        }
        if (!row) {
            rxHideAllSigPopovers();
            return;
        }
        row.classList.add('rx-row--active');
        if (options.openSig !== false) rxShowSigPopoverForSource(row, sourceEl || null);
    }

    function rxOnFieldEdit(event) {
        var target = event.target;
        if (!target || !target.matches) return;
        var draft = target.closest('[data-rx-draft]');
        var row = target.closest('[data-rx-row]');
        var active = draft || row;
        if (!active) return;
        if (!target.matches('[data-rx-field], [data-rx-signa], [data-rx-qty], [data-typeahead-q], [data-ing-name], [data-ing-qty], [data-rx-medicine-id], [data-ing-medicine-id]')) {
            return;
        }
        rxSetActiveRow(active, target, { openSig: false });
        if (target.matches('[data-ing-qty]')) target.dataset.manual = '1';
        if (target.matches('[data-rx-qty]')) {
            target.dataset.manual = '1';
            var flag = active.querySelector('[name="quantity_manual"]');
            if (flag) flag.value = '1';
            rxShowQtyHint(active, rxAssess(active));
            rxRecalcRow(active);
        } else if (target.matches('[name="dosage"], [data-rx-signa], [name="jho"], [name="notes"], [name="waktu"], [name="unit"], [name="course"]')) {
            rxClearQtyManual(active);
            rxAutoQty(active);
        }
        if (row) row.dataset.dirty = '1';
        rxScheduleAutoSave(active);
    }

    document.addEventListener('focusin', function (event) {
        if (event.target.closest('.rx-sig-popover, [data-sig-fill], [data-rx-sig-popover]')) return;
        var row = event.target.closest('[data-rx-row], [data-rx-draft]');
        if (row && row.closest('[data-rx-editor]')) rxSetActiveRow(row, event.target);
    });

    document.addEventListener('mousedown', function (event) {
        if (event.target.closest('.rx-sig-popover, [data-sig-fill], [data-rx-sig-popover]')) return;
        var row = event.target.closest('[data-rx-row], [data-rx-draft]');
        if (row && row.closest('[data-rx-editor]')) {
            // Hanya tandai row aktif; buka popover di focusin agar tidak ketutup oleh click yang sama.
            rxSetActiveRow(row, event.target, { openSig: false });
            return;
        }
        if (!event.target.closest('[data-rx-editor]')) rxSetActiveRow(null);
    });

    document.addEventListener('click', function (event) {
        var tipBtn = event.target.closest('[data-rx-sig-popover]');
        if (!tipBtn) return;
        event.preventDefault();
        event.stopPropagation();
        if (Date.now() < rxSigIgnoreToggleUntil && rxSigOpenToggle === tipBtn) return;
        var tipRow = tipBtn.closest('[data-rx-row], [data-rx-draft]');
        if (tipRow) tipRow.classList.add('rx-row--active');
        var open = tipBtn.getAttribute('aria-describedby') || (rxSigOpenToggle === tipBtn);
        if (open) rxHideAllSigPopovers();
        else rxShowSigPopover(tipBtn);
    });

    document.addEventListener('input', rxOnFieldEdit);
    document.addEventListener('change', function (event) {
        rxOnFieldEdit(event);
        var templateSelect = event.target.closest('[data-rx-template-select]');
        if (templateSelect && templateSelect.value) {
            var applyUrl = '{{ url('/visits/'.$visit->id.'/care/prescription-templates') }}/' + templateSelect.value + '/apply';
            var body = new FormData();
            body.append('_token', rxCsrf());
            fetch(applyUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': rxCsrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (payload) {
                    if (!res.ok) throw new Error((payload && payload.message) || 'Gagal menerapkan template');
                    if (payload.html != null) replaceRxList(payload.html);
                    if (payload.finish_html != null) replaceFinishRx(payload.finish_html);
                    templateSelect.value = '';
                });
            }).catch(function (error) {
                window.alert(error && error.message ? error.message : 'Gagal menerapkan template');
                templateSelect.value = '';
            });
        }
    });

    document.addEventListener('click', function (event) {
        var del = event.target.closest('[data-rx-delete]');
        if (del) {
            var row = del.closest('[data-rx-row]');
            if (!row || !window.confirm('Hapus item resep ini?')) return;
            var destroyUrl = row.getAttribute('data-destroy');
            if (!destroyUrl) return;
            var body = new FormData();
            body.append('_token', rxCsrf());
            body.append('_method', 'DELETE');
            fetch(destroyUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': rxCsrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (payload) {
                    if (!res.ok) throw new Error((payload && payload.message) || 'Gagal menghapus');
                    if (payload.html != null) replaceRxList(payload.html);
                    if (payload.finish_html != null) replaceFinishRx(payload.finish_html);
                });
            }).catch(function (error) {
                window.alert(error && error.message ? error.message : 'Gagal menghapus');
            });
            return;
        }

        if (event.target.closest('[data-rx-add-ready]')) {
            var readyRow = rxAddDraftRow('ready');
            if (readyRow) rxSetActiveRow(readyRow);
            return;
        }
        if (event.target.closest('[data-rx-add-compound]')) {
            var compoundRow = rxAddDraftRow('racikan');
            if (compoundRow) rxSetActiveRow(compoundRow);
            return;
        }
        if (event.target.closest('[data-rx-add-ingredient]')) {
            var draftCompound = event.target.closest('[data-rx-draft]');
            if (draftCompound) rxAddIngredient(draftCompound);
            return;
        }
        if (event.target.closest('[data-rx-remove-ingredient]')) {
            var ing = event.target.closest('[data-rx-ingredient]');
            var wrap = ing && ing.parentElement;
            if (ing) ing.remove();
            if (wrap && !wrap.querySelector('[data-rx-ingredient]')) {
                var parentDraft = wrap.closest('[data-rx-draft]');
                if (parentDraft) rxAddIngredient(parentDraft);
            }
            return;
        }
        if (event.target.closest('[data-rx-draft-cancel]')) {
            var cancelRow = event.target.closest('[data-rx-draft]');
            if (cancelRow) cancelRow.remove();
            rxEnsureEmptyState();
            return;
        }
    });

    function rxCatalog() {
        var node = document.getElementById('rx-medicine-catalog');
        try { return JSON.parse(node ? node.textContent || '[]' : '[]'); } catch (e) { return []; }
    }

    function rxCatalogItem(id) {
        return rxCatalog().find(function (item) { return String(item.id) === String(id); }) || null;
    }

    function rxEditorRoot() {
        return document.querySelector('[data-rx-editor]');
    }

    function rxCanEdit() {
        var editor = rxEditorRoot();
        return !!(editor && editor.getAttribute('data-editable') === '1');
    }

    function rxHasOpenReadyDraft() {
        var tbody = document.querySelector('[data-rx-rows]');
        if (!tbody) return false;
        return !!tbody.querySelector('[data-rx-draft][data-kind="ready"]');
    }

    function rxHasEmptyReadyDraft() {
        var tbody = document.querySelector('[data-rx-rows]');
        if (!tbody) return false;
        var found = false;
        tbody.querySelectorAll('[data-rx-draft][data-kind="ready"]').forEach(function (row) {
            if (!rxDraftReadyToSave(row)) found = true;
        });
        return found;
    }

    function rxEnsureEmptyReadyDraft() {
        if (!rxCanEdit()) return null;
        if (rxHasEmptyReadyDraft()) return null;
        return rxAddDraftRow('ready', { focus: false });
    }

    function rxSeedDefaultDraft(options) {
        options = options || {};
        var tbody = document.querySelector('[data-rx-rows]');
        if (!tbody || !rxCanEdit()) return;
        var empty = tbody.querySelector('[data-rx-empty]');
        if (empty) empty.remove();
        if (options.requireEmpty) {
            rxEnsureEmptyReadyDraft();
            return;
        }
        if (rxHasOpenReadyDraft()) return;
        rxAddDraftRow('ready', { focus: options.focus !== false });
    }

    function rxEnsureEmptyState() {
        var tbody = document.querySelector('[data-rx-rows]');
        if (!tbody) return;
        var hasRows = tbody.querySelector('[data-rx-row], [data-rx-draft]');
        var empty = tbody.querySelector('[data-rx-empty]');
        if (rxCanEdit()) {
            if (empty) empty.remove();
            if (!rxHasOpenReadyDraft()) rxAddDraftRow('ready', { focus: false });
            return;
        }
        if (hasRows) {
            if (empty) empty.remove();
            return;
        }
        if (!empty) {
            var tr = document.createElement('tr');
            tr.setAttribute('data-rx-empty', '');
            tr.innerHTML = '<td colspan="13" class="text-muted text-center py-2">Belum ada item resep.</td>';
            tbody.appendChild(tr);
        }
    }

    function rxDestroyMedicineSelects(scope) {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.select2) return;
        $(scope || document).find('[data-rx-medicine-select]').each(function () {
            var $el = $(this);
            if ($el.data('select2')) $el.select2('destroy');
        });
    }

    function rxInitMedicineSelects(scope) {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.select2) return;
        $(scope || document).find('[data-rx-medicine-select]').each(function () {
            var $el = $(this);
            if ($el.data('select2')) return;
            var isIngredient = $el.is('[data-rx-ing-medicine-select]');
            $el.select2({
                width: '100%',
                placeholder: isIngredient ? 'Pilih bahan stok…' : 'Cari obat…',
                allowClear: true,
                dropdownParent: $(document.body),
                language: {
                    noResults: function () { return 'Obat tidak ditemukan'; },
                    searching: function () { return 'Mencari…'; },
                },
            });
        });
    }

    function rxBindDraft(row) {
        if (!row) return row;
        row.querySelectorAll('[data-typeahead]').forEach(function (box) {
            box.dataset.typeaheadBound = '';
        });
        rxDisableBrowserAutofill(row);
        rxInitMedicineSelects(row);
        row.dispatchEvent(new CustomEvent('care:bind-typeahead', { bubbles: true }));
        return row;
    }

    function rxAddDraftRow(kind, options) {
        options = options || {};
        var tbody = document.querySelector('[data-rx-rows]');
        var tpl = document.getElementById(kind === 'racikan' ? 'rx-draft-compound-template' : 'rx-draft-ready-template');
        if (!tbody || !tpl) return null;
        var empty = tbody.querySelector('[data-rx-empty]');
        if (empty) empty.remove();
        var wrap = document.createElement('tbody');
        wrap.innerHTML = tpl.innerHTML.trim();
        var row = wrap.firstElementChild;
        if (!row) return null;
        tbody.appendChild(row);
        if (kind === 'racikan') {
            var count = tbody.querySelectorAll('[data-rx-draft][data-kind="racikan"]').length;
            var label = row.querySelector('[data-rx-racik-label]');
            if (label) label.textContent = '-' + String(count).padStart(2, '0');
            rxAddIngredient(row);
            rxAddIngredient(row);
        }
        rxBindDraft(row);
        if (options.focus !== false) {
            rxSetActiveRow(row);
            var focus = row.querySelector('[data-rx-medicine-select]:not([data-rx-ing-medicine-select])')
                || row.querySelector('[data-rx-medicine-select]');
            var $ = window.jQuery;
            if (focus && $ && $.fn && $.fn.select2 && $(focus).data('select2')) {
                $(focus).select2('open');
            } else if (focus) {
                focus.focus();
            }
        }
        return row;
    }

    function rxAddIngredient(draftRow) {
        var list = draftRow.querySelector('[data-rx-ingredients]');
        var tpl = document.getElementById('rx-draft-ingredient-template');
        if (!list || !tpl) return;
        var wrap = document.createElement('div');
        wrap.innerHTML = tpl.innerHTML.trim();
        var row = wrap.firstElementChild;
        if (!row) return;
        list.appendChild(row);
        rxBindDraft(row);
    }

    function rxFillMedicine(row, medicineId) {
        var item = rxCatalogItem(medicineId);
        if (!item) return;
        var strength = row.querySelector('[data-rx-strength]');
        var stock = row.querySelector('[data-rx-stock]');
        var price = row.querySelector('[data-rx-price]');
        var unit = row.querySelector('[data-rx-unit]');
        if (strength) strength.textContent = item.strength || '—';
        if (stock) stock.innerHTML = '<span class="badge ' + (item.stock > 0 ? 'bg-success' : 'bg-danger') + '">' + (item.stock || 0) + '</span>';
        if (price) {
            price.textContent = rxMoney(item.price || 0);
            price.dataset.raw = String(item.price || 0);
        }
        if (unit && item.unit) unit.value = item.unit;
        rxAutoQty(row);
        var ingName = row.querySelector('[data-ing-name]');
        if (ingName && item.name) ingName.value = item.name;
        rxScheduleAutoSave(row.closest('[data-rx-draft]') || row);
    }

    function rxSaveDraftRow(row, options) {
        options = options || {};
        if (!row || row.dataset.busy === '1') return Promise.resolve();
        if (!rxRowShouldSave(row)) return Promise.resolve();
        var editor = document.querySelector('[data-rx-editor]');
        var storeUrl = editor ? editor.getAttribute('data-store') : '';
        if (!storeUrl) return Promise.reject(new Error('URL simpan tidak ditemukan'));

        var kind = row.getAttribute('data-kind') || 'ready';
        var body = new FormData();
        body.append('_token', rxCsrf());
        body.append('_form', 'rx-editor-create');
        body.append('kind', kind === 'racikan' ? 'racikan' : 'ready');
        body.append('medication_type', kind === 'racikan' ? 'EP' : 'NC');
        body.append('quantity', (row.querySelector('[name="quantity"]') || {}).value || 1);

        ['dosage', 'frequency', 'waktu', 'jho', 'unit', 'notes', 'course', 'signa_p', 'signa_s', 'signa_sr', 'signa_m', 'quantity_manual'].forEach(function (name) {
            var input = row.querySelector('[name="' + name + '"]');
            if (input && String(input.value || '').trim() !== '') body.append(name, input.value);
        });

        var jho = Number((row.querySelector('[name="jho"]') || {}).value || 0);
        if (jho > 0) body.append('duration', jho + ' hari');

        if (kind === 'racikan') {
            var ingredients = [];
            row.querySelectorAll('[data-rx-ingredient]').forEach(function (ing) {
                var name = (ing.querySelector('[data-ing-name]') || {}).value || (ing.querySelector('[data-typeahead-q]') || {}).value || '';
                name = String(name).trim();
                if (!name) return;
                var ingQty = ing.querySelector('[data-ing-qty]');
                var ingManual = ingQty && ingQty.dataset.manual === '1';
                ingredients.push({
                    name: name,
                    medicine_id: (ing.querySelector('[data-ing-medicine-id]') || {}).value || '',
                    quantity: (ingQty || {}).value || 1,
                    per_dose_quantity: ingManual ? '' : '1',
                    quantity_manual: ingManual ? '1' : '0',
                    role: 'active'
                });
            });
            if (ingredients.length < 1) return Promise.resolve();
            ingredients.forEach(function (ing, index) {
                body.append('ingredients[' + index + '][name]', ing.name);
                body.append('ingredients[' + index + '][medicine_id]', ing.medicine_id);
                body.append('ingredients[' + index + '][quantity]', ing.quantity);
                body.append('ingredients[' + index + '][role]', 'active');
                body.append('ingredients[' + index + '][quantity_manual]', ing.quantity_manual);
                if (ing.per_dose_quantity) body.append('ingredients[' + index + '][per_dose_quantity]', ing.per_dose_quantity);
            });
            body.append('composition_confirmed', '1');
        } else {
            var medicineId = rxMedicineIdOf(row);
            if (!medicineId) return Promise.resolve();
            body.append('medicine_id', medicineId);
            var priceEl = row.querySelector('[data-rx-price]');
            if (priceEl && priceEl.dataset.raw) body.append('unit_price', priceEl.dataset.raw);
        }

        row.dataset.busy = '1';
        return fetch(storeUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': rxCsrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (payload) {
                if (!res.ok) {
                    var msg = (payload && payload.message)
                        || (payload && payload.errors && Object.values(payload.errors)[0] && Object.values(payload.errors)[0][0])
                        || 'Gagal menyimpan item';
                    throw new Error(msg);
                }
                if (options.background && rxPromoteDraftRow(row, payload)) {
                    return payload;
                }
                if (payload.html != null) {
                    replaceRxList(payload.html, options.focusItemId ? {
                        focusItemId: payload.item_id,
                        focusName: options.focusName || null,
                        selectionStart: options.selectionStart,
                        openSig: false,
                    } : {});
                }
                if (payload.finish_html != null) replaceFinishRx(payload.finish_html);
                return payload;
            });
        }).finally(function () {
            if (row.isConnected) row.dataset.busy = '0';
        });
    }

    document.addEventListener('change', function (event) {
        var medicineId = event.target.closest('[data-rx-medicine-id], [data-ing-medicine-id]');
        if (!medicineId) return;
        var draft = medicineId.closest('[data-rx-draft], [data-rx-ingredient]');
        var savedRow = medicineId.closest('[data-rx-row]');
        if (medicineId.hasAttribute('data-rx-medicine-id')) {
            if (!medicineId.value) return;
            var targetRow = (draft && draft.closest('[data-rx-draft]')) || draft || savedRow;
            if (!targetRow) return;
            rxFillMedicine(targetRow, medicineId.value);
            if (savedRow) {
                var priceEl = savedRow.querySelector('[data-rx-price]');
                var item = rxCatalogItem(medicineId.value);
                if (priceEl && item) {
                    priceEl.textContent = rxMoney(item.price || 0);
                    priceEl.dataset.raw = String(item.price || 0);
                    var unitPrice = savedRow.querySelector('[name="unit_price"]');
                    if (unitPrice) unitPrice.value = String(item.price || 0);
                    else {
                        var hiddenPrice = document.createElement('input');
                        hiddenPrice.type = 'hidden';
                        hiddenPrice.name = 'unit_price';
                        hiddenPrice.value = String(item.price || 0);
                        savedRow.appendChild(hiddenPrice);
                    }
                }
                savedRow.dataset.dirty = '1';
            }
            rxScheduleAutoSave(targetRow);
            rxEnsureEmptyReadyDraft();
            return;
        }
        if (!draft || !medicineId.value) return;
        var catalogItem = rxCatalogItem(medicineId.value);
        var name = draft.querySelector('[data-ing-name]');
        if (catalogItem && name) name.value = catalogItem.name || name.value;
        rxScheduleAutoSave(medicineId.closest('[data-rx-draft]'));
    });

    document.querySelectorAll('form.rx-form').forEach(function (form) {
        if (form.dataset.qtyBound === '1') return;
        form.dataset.qtyBound = '1';
        var qty = form.querySelector('[name="quantity"]');
        if (!qty || qty.hasAttribute('data-rx-qty')) return;
        qty.setAttribute('data-rx-qty', '');
        var hint = document.createElement('div');
        hint.className = 'rx-qty-hint';
        hint.setAttribute('data-rx-qty-hint', '');
        qty.insertAdjacentElement('afterend', hint);
        form.addEventListener('input', function (event) {
            if (!event.target || !event.target.matches) return;
            if (event.target === qty) {
                qty.dataset.manual = '1';
                var flag = form.querySelector('[name="quantity_manual"]');
                if (flag) flag.value = '1';
                rxShowQtyHint(form, rxAssess(form));
                return;
            }
            if (event.target.matches('[name="dosage"], [name="frequency"], [name="duration"], [name="course"], [name="jho"], [name="unit"], [name="waktu"], [name="signa_p"], [name="signa_s"], [name="signa_sr"], [name="signa_m"]')) {
                rxClearQtyManual(form);
                rxAutoQty(form);
            }
        });
        form.addEventListener('submit', function () {
            var flag = form.querySelector('[name="quantity_manual"]');
            if (!flag) {
                flag = document.createElement('input');
                flag.type = 'hidden';
                flag.name = 'quantity_manual';
                form.appendChild(flag);
            }
            flag.value = qty.dataset.manual === '1' ? '1' : '0';
        });
    });

    document.querySelectorAll('[data-rx-row], [data-rx-draft]').forEach(function (row) {
        if (row.querySelector('[data-rx-qty]')) rxShowQtyHint(row, rxAssess(row));
    });

    function rxBoot() {
        rxDisableBrowserAutofill(document.getElementById('rx-list'));
        rxInitMedicineSelects(document.getElementById('rx-list'));
        rxSeedDefaultDraft();
    }

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        rxBoot();
    } else {
        document.addEventListener('DOMContentLoaded', rxBoot);
    }
})();
</script>
