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
    <div class="{{ $embedded ? 'rx-toolbar justify-content-end mb-2' : 'care-section__head d-flex justify-content-between align-items-start gap-2' }}">
        @unless ($embedded)
        <div>
            <h5 class="care-section__title">Resep</h5>
            <p class="care-section__hint">Draft bisa diubah. Resep dikirim ke farmasi saat kunjungan diselesaikan (harus lengkap SIG + tipe NC/EP/SD; KFA agar ikut SATUSEHAT). Stok belum dipotong.</p>
        </div>
        @endunless
        <div class="rx-toolbar">
            @can('prescription.view')
                <a href="{{ route('care.prescriptions.pdf', $visit) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border" title="Cetak resep" aria-label="Cetak resep" id="rx-print-all">
                    <i class="bx bx-printer me-1"></i>Cetak resep
                </a>
            @endcan
            @can('prescription.create')
                @if ($writable)
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#rx-create-modal" title="Tambah obat" aria-label="Tambah obat">
                        <i class="bx bx-plus me-1"></i>Tambah obat
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#rx-compound-modal" title="Tambah racikan" aria-label="Tambah racikan">
                        <i class="bx bx-capsule me-1"></i>Tambah racikan
                    </button>
                    <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#rx-template-modal" title="Template resep" aria-label="Template resep">
                        <i class="bx bx-copy me-1"></i>Template resep
                    </button>
                @endif
            @endcan
        </div>
    </div>
    <div class="care-section__body">
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

                <script type="application/json" id="rx-medicine-catalog">@json($medicines->map(fn ($medicine) => ['id' => $medicine->id, 'label' => $medicine->catalogLabel(), 'unit' => $medicine->unitLabel()])->values())</script>

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

    function replaceRxList(html) {
        document.querySelectorAll('body > [id^="rx-save-template-"]').forEach(function (el) {
            el.remove();
        });
        var list = document.getElementById('rx-list');
        if (!list || html == null) return;
        list.innerHTML = html;
        list.querySelectorAll('.care-modal').forEach(function (modal) {
            document.body.appendChild(modal);
        });
        list.dispatchEvent(new CustomEvent('care:bind-typeahead', { bubbles: true }));
        list.querySelectorAll('[data-compound-form]').forEach(function (form) {
            syncStrength(form);
            preview(form);
        });
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
            if (per.value && packs > 0 && amount > 0) total.value = String(Math.round(amount * packs));
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
            var holder = sig.closest('[data-sig-for]');
            var input = holder && document.getElementById(holder.getAttribute('data-sig-for'));
            if (input) {
                input.value = sig.getAttribute('data-sig-fill');
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
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
})();
</script>
