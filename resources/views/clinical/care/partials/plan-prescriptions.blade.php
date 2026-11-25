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

                <x-modal id="rx-dose-calc-modal" title="Kalkulator Dosis Racikan" size="modal-lg" class="care-modal">
                    <div data-dose-calc>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label" for="rx-calc-strength">Kandungan (kekuatan)</label>
                                <input type="number" class="form-control" id="rx-calc-strength" data-calc-strength step="0.0001" min="0" placeholder="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="rx-calc-packs">Jumlah bungkus (Qty)</label>
                                <input type="number" class="form-control" id="rx-calc-packs" data-calc-packs min="1" step="1" placeholder="1">
                            </div>
                        </div>

                        <hr class="my-3">
                        <p class="fw-semibold small mb-2">Dosis pecahan (opsional)</p>
                        <div class="row g-2 align-items-end">
                            <div class="col-4">
                                <label class="form-label" for="rx-calc-num">Pembilang</label>
                                <input type="number" class="form-control" id="rx-calc-num" data-calc-num step="0.0001" min="0">
                            </div>
                            <div class="col-1 text-center pb-2">/</div>
                            <div class="col-4">
                                <label class="form-label" for="rx-calc-den">Penyebut</label>
                                <input type="number" class="form-control" id="rx-calc-den" data-calc-den step="0.0001" min="0" value="1">
                            </div>
                            <div class="col-3">
                                <label class="form-label" for="rx-calc-fraction">Hasil pecahan</label>
                                <input type="text" class="form-control" id="rx-calc-fraction" data-calc-fraction readonly>
                            </div>
                        </div>
                        <p class="form-text mt-1">Contoh: 1/2 = 0,5 maka Dosis Akhir = Kandungan × 0,5</p>

                        <div class="mb-2">
                            <label class="form-label" for="rx-calc-dose">Dosis akhir (per bungkus)</label>
                            <input type="number" class="form-control" id="rx-calc-dose" data-calc-dose step="0.0001" min="0" placeholder="0,00">
                            <p class="form-text mt-1 mb-0">Bisa diisi langsung atau dihitung dari pecahan × kandungan.</p>
                        </div>

                        <hr class="my-3">
                        <p class="fw-semibold small mb-2">Kalkulator dosis racikan (berdasarkan BB &amp; persen)</p>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label" for="rx-calc-weight">Berat badan (kg)</label>
                                <input type="number" class="form-control" id="rx-calc-weight" data-calc-weight step="0.1" min="0" placeholder="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="rx-calc-percent">Persen (%)</label>
                                <input type="number" class="form-control" id="rx-calc-percent" data-calc-percent step="0.1" min="0" placeholder="0">
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-light border w-100" data-dose-calc-bb>
                                    <i class="bx bx-calculator me-1"></i>Hitung Dosis dari BB &amp; Persen
                                </button>
                            </div>
                        </div>
                        <p class="form-text mt-1">Rumus: Dosis = (BB × Persen) / 100</p>

                        <div class="alert alert-light border small mb-0" data-calc-result>
                            <div data-calc-formula>Rumus: Jumlah = (Dosis Akhir × Jumlah Bungkus) / Kandungan</div>
                            <div data-calc-hitung>Hitung: —</div>
                            <div class="d-flex justify-content-between mt-1">
                                <span>Jumlah: <b data-calc-jumlah>0,00</b></span>
                                <span>Pembulatan: <b data-calc-round>0</b></span>
                            </div>
                        </div>
                        <p class="text-danger small mt-2 mb-0" data-calc-warning hidden></p>
                    </div>
                    <x-slot:footer>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" data-dose-calc-apply>
                            <i class="bx bx-save me-1"></i>Simpan
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
                            'strength_value' => $medicine->strength_value,
                            'strength_unit' => $medicine->strength_unit,
                            'generic_name' => $medicine->generic_name,
                            'price' => (float) ($medicine->sell_price ?? $medicine->base_price ?? 0),
                            'sell' => (float) ($medicine->sell_price ?? $medicine->base_price ?? 0),
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
        rxDestroySignaSelects(list);
        list.dispatchEvent(new CustomEvent('care:bind-typeahead', { bubbles: true }));
        list.querySelectorAll('[data-compound-form]').forEach(function (form) {
            syncStrength(form);
            preview(form);
        });
        rxDisableBrowserAutofill(list);
        rxInitMedicineSelects(list);
        rxInitSignaSelects(list);
        list.querySelectorAll('[data-rx-row], [data-rx-draft]').forEach(function (row) {
            if ((row.getAttribute('data-kind') || '') === 'racikan') {
                rxRecalcCompoundQty(row);
                rxRecalcRow(row);
            }
        });
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
            var row = null;
            if (rxSigOpenToggle) row = rxSigOpenToggle.closest('[data-rx-row], [data-rx-draft], form.rx-form');
            if (!row) {
                row = (holder && holder.closest('[data-rx-row], [data-rx-draft]'))
                    || document.querySelector('[data-rx-row].rx-row--active, [data-rx-draft].rx-row--active');
            }
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
                rxHideAllSigPopovers();
                return;
            }
            if (effect === 'dose' && !input && row) {
                input = row.querySelector('[name="dosage"]');
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
                } else if (effect === 'form') {
                    input.value = fillValue;
                    rxApplySigUnit(row, sig.getAttribute('data-sig-unit'));
                } else if (effect === 'dose') {
                    var doseValue = sig.getAttribute('data-sig-dose');
                    input.value = doseValue ? String(doseValue) : fillValue;
                    if (/ml\b/i.test(fillValue) && doseValue) input.value = doseValue + ' ml';
                    rxApplySigUnit(row, sig.getAttribute('data-sig-unit'));
                } else {
                    input.value = fillValue;
                }
                if (row) rxClearQtyManual(row);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                if (row) rxAutoQty(row);
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

    function rxRowKey(row) {
        if (!row) return '';
        return row.getAttribute('data-rx-key') || row.getAttribute('data-item-id') || '';
    }

    function rxComponentRows(row) {
        var key = rxRowKey(row);
        if (!key) return [];
        var tbody = row.closest('tbody') || document.querySelector('[data-rx-rows]');
        return Array.prototype.slice.call((tbody || document).querySelectorAll('[data-rx-component-row]'))
            .filter(function (tr) { return tr.getAttribute('data-rx-component-of') === key; });
    }

    function rxParentRow(componentRow) {
        var key = componentRow ? componentRow.getAttribute('data-rx-component-of') : '';
        if (!key) return null;
        var tbody = componentRow.closest('tbody') || document.querySelector('[data-rx-rows]');
        return (tbody || document).querySelector('[data-rx-row][data-rx-key="' + key + '"], [data-rx-draft][data-rx-key="' + key + '"]');
    }

    function rxClearSigna(row) {
        ['signa_p', 'signa_s', 'signa_sr', 'signa_m'].forEach(function (name) {
            rxSetSignaValue(row, name, '');
        });
    }

    function rxFillFrequencySigna(row, times) {
        var pattern = { 1: [1, 0, 0, 0], 2: [1, 0, 0, 1], 3: [1, 1, 0, 1], 4: [1, 1, 1, 1] }[times];
        if (!pattern) return false;
        ['signa_p', 'signa_s', 'signa_sr', 'signa_m'].forEach(function (name, index) {
            rxSetSignaValue(row, name, pattern[index] ? '1' : '');
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
            if (sig.getAttribute('data-sig-dose') && dosage) dosage.value = String(dose);
            if (!rxFillFrequencySigna(row, times, 1)) rxClearSigna(row);
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
        var plainDose = /^\d+(?:[.,]\d+)?$/.test(dosage);
        var compound = kind === 'racikan' || kind === 'compound';
        var liquidUnit = unit === 'ml' || unit === 'sirup' || dosageLower.indexOf('sirup') !== -1 || dosageLower.indexOf('sediaan cair') !== -1;
        var form = { countable: false, liquid: false, hasMl: false, label: 'tablet' };
        if (ml != null || (plainDose && (unit === 'ml' || unit === 'sirup'))) {
            if (ml == null && plainDose) ml = rxParseAmount(dosage);
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
            var shown = [];
            var labelsSlot = ['Pagi', 'Siang', 'Sore', 'Malam'];
            slots.forEach(function (slot, index) {
                if (slot && slot > 0) sum += slot;
                shown.push(labelsSlot[index] + ' ' + rxTrimNum(slot || 0));
            });
            daily = sum;
            explain = rxTrimNum(sum) + ' kali/hari (' + shown.join(', ') + ')';
        } else {
            var frequency = rxField(row, 'frequency');
            var perDay = null;
            var unitsPerDose = 1;
            var pair = frequency.match(/(\d+(?:[.,]\d+)?)\s*[x×]\s*(\d+(?:[.,]\d+)?)/i);
            var dailyWord = frequency.match(/(\d+)\s*[x×]\s*sehari/i);
            var interval = frequency.match(/tiap\s+(\d+)\s*jam/i);
            if (pair) {
                perDay = Math.round(Number(String(pair[1]).replace(',', '.')));
                unitsPerDose = rxParseAmount(pair[2]) || 1;
            } else if (dailyWord) {
                perDay = Number(dailyWord[1]);
            } else if (interval) {
                var hours = Number(interval[1]);
                if (hours > 0 && 24 % hours === 0) perDay = 24 / hours;
            }
            if (perDay) daily = perDay * unitsPerDose;
            explain = rxTrimNum(daily || 0) + ' kali/hari';
        }

        if (daily === null) {
            base.formula = 'Isi signa atau frekuensi agar Qty terhitung.';
            return base;
        }
        if (daily <= 0) {
            base.mode = 'calculated';
            base.quantity = 1;
            base.liquid = form.liquid;
            base.formula = 'Signa 0 → Qty 1.';
            return base;
        }
        if (!days) {
            base.formula = 'Qty = signa × jumlah hari obat (JHO).';
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
                if (unit.tagName === 'SELECT' && !unit.querySelector('option[value="ml"]')) {
                    var option = document.createElement('option');
                    option.value = 'ml';
                    option.textContent = 'ml';
                    unit.appendChild(option);
                }
                unit.value = 'ml';
                var unitView = row.querySelector('[data-rx-unit-view]');
                if (unitView) unitView.value = 'ml';
            }
        }
        rxRecalcCompoundQty(row);
        rxRecalcRow(row);
    }

    function rxComponentValue(ing, selector) {
        var el = ing.querySelector(selector);
        return el ? String(el.value || '').trim() : '';
    }

    function rxSyncComponentViews(ing) {
        if (!ing) return;
        var doseView = ing.querySelector('[data-ing-dose-view]');
        var qtyView = ing.querySelector('[data-ing-qty-view]');
        var nameView = ing.querySelector('[data-ing-name-view]');
        var dose = rxComponentValue(ing, '[data-ing-dose]');
        var qty = rxComponentValue(ing, '[data-ing-qty]');
        var name = rxComponentValue(ing, '[data-ing-name]');
        if (doseView) doseView.textContent = dose !== '' ? dose : '—';
        if (qtyView) qtyView.textContent = qty !== '' ? qty : '—';
        if (nameView && name !== '') nameView.textContent = name;
    }

    function rxRecalcCompoundQty(row) {
        if (!row) return;
        var kind = row.getAttribute('data-kind') || (row.querySelector('[name="kind"]') || {}).value || 'ready';
        if (kind !== 'racikan') return;
        var packs = Number((row.querySelector('[data-rx-qty]') || {}).value || 0);
        rxComponentRows(row).forEach(function (ing) {
            var role = (ing.getAttribute('data-ingredient-role') || 'active') === 'additive' ? 'additive' : 'active';
            var qtyInput = ing.querySelector('[data-ing-qty]');
            if (!qtyInput) return;
            if (role !== 'additive') {
                if (qtyInput.dataset.manual !== '1') {
                    var dose = rxParseAmount(rxComponentValue(ing, '[data-ing-dose]'));
                    var strength = rxParseAmount(rxComponentValue(ing, '[data-ing-strength]'));
                    if (dose != null && dose > 0 && packs && strength != null && strength > 0) {
                        var raw = dose * packs / strength;
                        qtyInput.value = String(Math.max(1, Math.ceil(Math.round(raw * 10000) / 10000)));
                        var rawEl = ing.querySelector('[data-ing-qty-raw]');
                        if (rawEl) rawEl.textContent = Math.abs(raw - Math.round(raw)) > 0.0001 ? '= ' + rxTrimNum(raw) : '';
                    }
                }
            }
            rxSyncComponentViews(ing);
        });
    }

    function rxCompoundPrice(row) {
        var total = 0;
        rxComponentRows(row).forEach(function (ing) {
            var sub = ing.querySelector('[data-rx-comp-subtotal]');
            if (sub && sub.dataset.raw != null && sub.dataset.raw !== '') {
                total += Number(sub.dataset.raw) || 0;
                return;
            }
            var item = rxCatalogItem((ing.querySelector('[data-ing-medicine-id]') || {}).value || '');
            var qty = Number((ing.querySelector('[data-ing-qty]') || {}).value || 0);
            if (!item || qty < 1) return;
            var unit = item.sell != null ? Number(item.sell) : Number(item.price || 0);
            total += (Number.isFinite(unit) ? unit : 0) * qty;
        });
        return total;
    }

    function rxLineAmount(row) {
        if ((row.getAttribute('data-kind') || '') === 'racikan') return rxCompoundPrice(row);
        var qtyInput = row.querySelector('[data-rx-qty]');
        var qty = Number(qtyInput ? qtyInput.value : 0) || 0;
        return qty * rxPriceOf(row);
    }

    function rxRecalcRow(row) {
        var kind = row.getAttribute('data-kind') || (row.querySelector('[name="kind"]') || {}).value || 'ready';
        if (kind === 'racikan') {
            rxComponentRows(row).forEach(function (ing) {
                var priceCell = ing.querySelector('[data-rx-comp-price]');
                var item = rxCatalogItem((ing.querySelector('[data-ing-medicine-id]') || {}).value || '');
                if (item && priceCell) {
                    var unitPrice = Number(item.sell != null ? item.sell : item.price || 0);
                    priceCell.textContent = rxMoney(unitPrice);
                    priceCell.dataset.raw = String(unitPrice);
                }
                var rawPrice = Number((priceCell && priceCell.dataset.raw) || 0);
                var qty = Number((ing.querySelector('[data-ing-qty]') || {}).value || 0);
                var subCell = ing.querySelector('[data-rx-comp-subtotal]');
                if (subCell) {
                    var lineTotal = rawPrice * (qty > 0 ? qty : 0);
                    subCell.textContent = rxMoney(lineTotal);
                    subCell.dataset.raw = String(lineTotal);
                }
            });
        }
        var amount = rxLineAmount(row);
        if (kind === 'racikan') {
            var priceEl = row.querySelector('[data-rx-price]');
            if (priceEl) {
                priceEl.textContent = rxMoney(amount);
                priceEl.dataset.raw = String(amount);
            }
        }
        var sub = row.querySelector('[data-rx-subtotal]');
        if (sub) sub.textContent = rxMoney(amount);
        rxRecalcEstimate();
    }

    function rxRecalcEstimate() {
        var total = 0;
        document.querySelectorAll('[data-rx-row], [data-rx-draft]').forEach(function (row) {
            total += rxLineAmount(row);
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

    function rxReadCompoundIngredients(row) {
        var ingredients = [];
        if (!row) return ingredients;
        rxComponentRows(row).forEach(function (ing) {
            var name = (ing.querySelector('[data-ing-name]') || {}).value || (ing.querySelector('[data-typeahead-q]') || {}).value || '';
            name = String(name).trim();
            if (!name) return;
            var ingQty = ing.querySelector('[data-ing-qty]');
            var ingManual = ingQty && ingQty.dataset.manual === '1';
            var per = String((ing.querySelector('[data-ing-per]') || {}).value || '').trim();
            var dose = String((ing.querySelector('[data-ing-dose]') || {}).value || '').trim();
            ingredients.push({
                name: name,
                medicine_id: String((ing.querySelector('[data-ing-medicine-id]') || {}).value || '').trim(),
                quantity: (ingQty || {}).value || 1,
                dose_amount: dose,
                per_dose_quantity: per,
                quantity_manual: ingManual ? '1' : '0',
                role: String((ing.querySelector('[data-ing-role]') || {}).value || 'active').trim() || 'active',
                additive_kind: String((ing.querySelector('[data-ing-additive]') || {}).value || '').trim(),
                unit: String((ing.querySelector('[data-ing-unit]') || {}).value || '').trim(),
                strength_value: String((ing.querySelector('[data-ing-strength]') || {}).value || '').trim(),
                strength_unit: String((ing.querySelector('[data-ing-strength-unit]') || {}).value || '').trim()
            });
        });
        return ingredients;
    }

    function rxAppendCompoundIngredients(body, ingredients) {
        ingredients.forEach(function (ing, index) {
            body.append('ingredients[' + index + '][name]', ing.name);
            body.append('ingredients[' + index + '][quantity]', ing.quantity);
            body.append('ingredients[' + index + '][role]', ing.role);
            body.append('ingredients[' + index + '][quantity_manual]', ing.quantity_manual);
            if (ing.medicine_id) body.append('ingredients[' + index + '][medicine_id]', ing.medicine_id);
            if (ing.dose_amount) body.append('ingredients[' + index + '][dose_amount]', ing.dose_amount);
            if (ing.per_dose_quantity) body.append('ingredients[' + index + '][per_dose_quantity]', ing.per_dose_quantity);
            if (ing.additive_kind) body.append('ingredients[' + index + '][additive_kind]', ing.additive_kind);
            if (ing.unit) body.append('ingredients[' + index + '][unit]', ing.unit);
            if (ing.strength_value) body.append('ingredients[' + index + '][strength_value]', ing.strength_value);
            if (ing.strength_unit) body.append('ingredients[' + index + '][strength_unit]', ing.strength_unit);
        });
        body.append('composition_confirmed', '1');
    }

    var rxDoseCalcRow = null;

    function rxDoseCalcEl(modal, selector) {
        return modal ? modal.querySelector(selector) : null;
    }

    function rxDoseCalcValue(modal, selector) {
        var el = rxDoseCalcEl(modal, selector);
        if (!el) return 0;
        var value = Number(String(el.value || '').replace(',', '.'));
        return Number.isFinite(value) ? value : 0;
    }

    function rxDoseCalcSetValue(modal, selector, value) {
        var el = rxDoseCalcEl(modal, selector);
        if (el) el.value = value == null ? '' : String(value);
    }

    function rxDoseCalcUpdate() {
        var modal = document.getElementById('rx-dose-calc-modal');
        if (!modal) return { dose: null, jumlah: null, round: null, valid: false };
        var strength = rxDoseCalcValue(modal, '[data-calc-strength]');
        var packs = rxDoseCalcValue(modal, '[data-calc-packs]');
        var num = rxDoseCalcValue(modal, '[data-calc-num]');
        var den = rxDoseCalcValue(modal, '[data-calc-den]');
        var dose = rxDoseCalcValue(modal, '[data-calc-dose]');
        var fraction = den > 0 ? num / den : null;

        var fractionEl = rxDoseCalcEl(modal, '[data-calc-fraction]');
        if (fractionEl) fractionEl.value = fraction == null ? '—' : fraction.toFixed(4).replace('.', ',');

        var jumlah = (strength > 0 && packs > 0) ? (dose * packs) / strength : null;
        var round = (jumlah != null && Number.isFinite(jumlah)) ? Math.ceil(Math.round(jumlah * 10000) / 10000) : null;

        var warning = '';
        if (!(strength > 0)) warning = 'Kandungan harus lebih besar dari 0.';
        else if (!(packs > 0)) warning = 'Jumlah bungkus harus lebih besar dari 0.';
        else if (num > 0 && den === 0) warning = 'Penyebut pecahan tidak boleh 0.';
        else if (!(dose > 0)) warning = 'Dosis akhir harus lebih besar dari 0.';
        else if (jumlah == null || !Number.isFinite(jumlah) || !Number.isFinite(round)) warning = 'Perhitungan tidak boleh NaN atau Infinity.';
        else if (round < 1) warning = 'Jumlah obat minimal 1.';

        var formula = rxDoseCalcEl(modal, '[data-calc-formula]');
        if (formula) formula.textContent = 'Rumus: Jumlah = (Dosis Akhir × Jumlah Bungkus) / Kandungan';
        var hitung = rxDoseCalcEl(modal, '[data-calc-hitung]');
        if (hitung) {
            hitung.textContent = 'Hitung: (' + rxTrimNum(dose) + ' × ' + rxTrimNum(packs) + ') / ' + rxTrimNum(strength)
                + ' = ' + (jumlah != null && Number.isFinite(jumlah) ? jumlah.toFixed(2) : '—');
        }
        var jumlahEl = rxDoseCalcEl(modal, '[data-calc-jumlah]');
        if (jumlahEl) jumlahEl.textContent = jumlah != null && Number.isFinite(jumlah) ? rxTrimNum(jumlah) : '—';
        var roundEl = rxDoseCalcEl(modal, '[data-calc-round]');
        if (roundEl) roundEl.textContent = round != null ? String(round) : '—';
        var warningEl = rxDoseCalcEl(modal, '[data-calc-warning]');
        if (warningEl) {
            warningEl.textContent = warning;
            warningEl.hidden = warning === '';
        }
        var apply = rxDoseCalcEl(modal, '[data-dose-calc-apply]');
        if (apply) apply.disabled = warning !== '';

        return { dose: dose, jumlah: jumlah, round: round, valid: warning === '' };
    }

    function rxDoseCalcApplyFraction() {
        var modal = document.getElementById('rx-dose-calc-modal');
        if (!modal) return;
        var strength = rxDoseCalcValue(modal, '[data-calc-strength]');
        var num = rxDoseCalcValue(modal, '[data-calc-num]');
        var den = rxDoseCalcValue(modal, '[data-calc-den]');
        if (num > 0 && den > 0 && strength > 0) {
            rxDoseCalcSetValue(modal, '[data-calc-dose]', rxTrimNum(strength * (num / den)));
        }
        rxDoseCalcUpdate();
    }

    function rxOpenDoseCalc(ing) {
        var modal = document.getElementById('rx-dose-calc-modal');
        if (!modal || !window.bootstrap || !ing) return;
        rxDoseCalcRow = ing;
        var row = rxParentRow(ing);
        var strength = rxComponentValue(ing, '[data-ing-strength]');
        var dose = rxComponentValue(ing, '[data-ing-dose]');
        var qty = row ? row.querySelector('[data-rx-qty]') : null;
        rxDoseCalcSetValue(modal, '[data-calc-strength]', strength);
        rxDoseCalcSetValue(modal, '[data-calc-packs]', qty ? qty.value : '1');
        rxDoseCalcSetValue(modal, '[data-calc-num]', '');
        rxDoseCalcSetValue(modal, '[data-calc-den]', '1');
        rxDoseCalcSetValue(modal, '[data-calc-dose]', dose);
        rxDoseCalcSetValue(modal, '[data-calc-fraction]', '');
        rxDoseCalcSetValue(modal, '[data-calc-weight]', '');
        rxDoseCalcSetValue(modal, '[data-calc-percent]', '');
        rxDoseCalcUpdate();
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function rxApplyDoseCalc() {
        if (!rxDoseCalcRow) return;
        var modal = document.getElementById('rx-dose-calc-modal');
        var result = rxDoseCalcUpdate();
        if (!result.valid || result.dose == null) return;

        var packs = rxDoseCalcValue(modal, '[data-calc-packs]');
        var strength = rxDoseCalcValue(modal, '[data-calc-strength]');
        var doseInput = rxDoseCalcRow.querySelector('[data-ing-dose]');
        var qtyInput = rxDoseCalcRow.querySelector('[data-ing-qty]');
        var strengthInput = rxDoseCalcRow.querySelector('[data-ing-strength]');
        var strengthView = rxDoseCalcRow.querySelector('[data-ing-strength-view]');
        if (doseInput) doseInput.value = rxTrimNum(result.dose);
        if (strengthInput && strength > 0) {
            strengthInput.value = rxTrimNum(strength);
            if (strengthView) {
                var unit = rxComponentValue(rxDoseCalcRow, '[data-ing-strength-unit]');
                strengthView.textContent = rxTrimNum(strength) + (unit ? ' ' + unit : '');
            }
        }
        if (qtyInput && result.round != null) {
            qtyInput.value = String(result.round);
            qtyInput.dataset.manual = '1';
        }

        var row = rxParentRow(rxDoseCalcRow);
        if (row) {
            var parentQty = row.querySelector('[data-rx-qty]');
            if (parentQty && packs > 0 && Number(parentQty.value) !== packs) {
                parentQty.value = String(packs);
                parentQty.dataset.manual = '1';
                var flag = row.querySelector('[name="quantity_manual"]');
                if (flag) flag.value = '1';
                rxShowQtyHint(row, rxAssess(row));
            }
            rxRecalcRow(row);
        }
        rxSyncComponentViews(rxDoseCalcRow);
        if (window.careSetStatus) {
            window.careSetStatus('Dosis ' + rxTrimNum(result.dose) + ' · Qty ' + (result.round != null ? result.round : '—') + ' tersimpan ke komponen');
        }
        if (modal && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        if (row) rxCommitRow(row);
    }

    document.addEventListener('click', function (event) {
        var calc = event.target.closest('[data-rx-ing-calc]');
        if (calc) {
            rxOpenDoseCalc(calc.closest('[data-rx-ingredient]'));
            return;
        }
        if (event.target.closest('[data-dose-calc-bb]')) {
            var modal = document.getElementById('rx-dose-calc-modal');
            var weight = rxDoseCalcValue(modal, '[data-calc-weight]');
            var percent = rxDoseCalcValue(modal, '[data-calc-percent]');
            if (weight > 0 && percent > 0) rxDoseCalcSetValue(modal, '[data-calc-dose]', rxTrimNum((weight * percent) / 100));
            rxDoseCalcUpdate();
            return;
        }
        if (event.target.closest('[data-dose-calc-apply]')) rxApplyDoseCalc();
    });

    document.addEventListener('input', function (event) {
        if (!event.target.closest('[data-dose-calc]')) return;
        if (event.target.matches('[data-calc-num], [data-calc-den]')) {
            rxDoseCalcApplyFraction();
            return;
        }
        rxDoseCalcUpdate();
    });

    function rxRefreshList() {
        return fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            credentials: 'same-origin'
        }).then(function (res) {
            return res.text();
        }).then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var list = doc.getElementById('rx-list');
            if (list) replaceRxList(list.innerHTML);
        });
    }

    function rxSaveAll(button) {
        if (!button || button.disabled) return;
        var tbody = document.querySelector('[data-rx-rows]');
        if (!tbody) return;

        var drafts = Array.prototype.slice.call(tbody.querySelectorAll('[data-rx-draft]'));
        var edited = Array.prototype.slice.call(tbody.querySelectorAll('[data-rx-row].is-editing'))
            .filter(function (row) { return row.dataset.dirty === '1'; });

        if (!drafts.length && !edited.length) {
            if (window.careSetStatus) window.careSetStatus('Tidak ada perubahan untuk disimpan.');
            return;
        }

        button.disabled = true;
        if (window.careSetStatus) window.careSetStatus('Menyimpan resep…');

        var chain = Promise.resolve();
        drafts.forEach(function (row) {
            chain = chain.then(function () {
                if (!rxRowShouldSave(row)) {
                    rxComponentRows(row).forEach(function (tr) { tr.remove(); });
                    row.remove();
                    return;
                }
                return rxSaveDraftRow(row, { background: true });
            });
        });
        edited.forEach(function (row) {
            chain = chain.then(function () {
                return rxSaveRow(row, { silent: true });
            });
        });

        chain.then(function () {
            return rxRefreshList();
        }).then(function () {
            if (window.careSetStatus) window.careSetStatus('✓ Resep tersimpan');
        }).catch(function (error) {
            if (window.careSetStatus) window.careSetStatus('Gagal menyimpan resep', true);
            window.alert(error && error.message ? error.message : 'Gagal menyimpan resep');
        }).finally(function () {
            button.disabled = false;
        });
    }

    function rxSaveRow(row, options) {
        options = options || {};
        var url = row.getAttribute('data-update');
        if (!url || row.dataset.busy === '1') return Promise.resolve();
        if (!rxRowShouldSave(row)) return Promise.resolve();
        if ((row.getAttribute('data-kind') || '') === 'racikan') {
            var compoundIngredients = rxReadCompoundIngredients(row);
            if (!compoundIngredients.length) return Promise.reject(new Error('Isi minimal satu bahan racikan.'));
        }
        row.dataset.busy = '1';
        var payload = rxCollectRowPayload(row);
        var body = new FormData();
        Object.keys(payload).forEach(function (key) {
            if (payload[key] != null) body.append(key, payload[key]);
        });
        if ((row.getAttribute('data-kind') || '') === 'racikan') {
            rxAppendCompoundIngredients(body, rxReadCompoundIngredients(row));
        }
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

    function rxMedicineIdOf(row) {
        var el = row && row.querySelector('[data-rx-medicine-id]');
        return el ? String(el.value || '').trim() : '';
    }

    function rxDraftReadyToSave(row) {
        if (!row) return false;
        var kind = row.getAttribute('data-kind') || 'ready';
        if (kind === 'racikan') {
            var named = 0;
            rxComponentRows(row).forEach(function (ing) {
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

    function rxBeginEdit(row) {
        if (!row || row.classList.contains('is-editing')) return;
        var snap = [];
        row.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (input) {
            snap.push({ el: input, value: input.value, manual: input.getAttribute('data-manual') || '' });
        });
        var components = rxComponentRows(row);
        components.forEach(function (tr) {
            tr.querySelectorAll('input, select, textarea').forEach(function (input) {
                snap.push({ el: input, value: input.value, manual: input.dataset ? (input.dataset.manual || '') : '' });
            });
        });
        row._rxSnap = snap;
        row._rxComponents = components;
        row.classList.add('is-editing');
        rxComponentRows(row).forEach(function (tr) { tr.classList.add('is-editing'); });
        void row.offsetWidth;
        rxInitMedicineSelects(row);
        rxComponentRows(row).forEach(rxInitMedicineSelects);
        rxInitSignaSelects(row);
        if (row.querySelector('[data-rx-qty]')) rxShowQtyHint(row, rxAssess(row));
    }

    function rxCancelEdit(row) {
        if (!row) return;
        var components = rxComponentRows(row);
        rxComponentRows(row).forEach(function (tr) {
            if ((row._rxComponents || []).indexOf(tr) === -1) tr.remove();
        });
        components = rxComponentRows(row);
        rxDestroyMedicineSelects(row);
        rxDestroySignaSelects(row);
        components.forEach(rxDestroyMedicineSelects);
        (row._rxSnap || []).forEach(function (item) {
            if (!item.el) return;
            item.el.value = item.value;
            if (item.manual) item.el.setAttribute('data-manual', item.manual);
            else item.el.removeAttribute('data-manual');
        });
        components.forEach(function (tr) {
            tr.classList.remove('is-editing');
            tr.querySelectorAll('input[data-ing-qty]').forEach(function (el) { delete el.dataset.manual; });
            rxSyncComponentViews(tr);
        });
        row.classList.remove('is-editing');
        row._rxSnap = null;
        rxHideAllSigPopovers();
        rxRecalcRow(row);
    }

    function rxCommitRow(row) {
        if (!row || row.dataset.busy === '1') return;
        if (!rxRowShouldSave(row)) {
            window.alert(row.getAttribute('data-kind') === 'racikan' ? 'Isi minimal satu bahan racikan.' : 'Pilih obat terlebih dahulu.');
            return;
        }
        if ((row.getAttribute('data-kind') || '') === 'racikan' && rxReadCompoundIngredients(row).length < 1) {
            window.alert('Isi minimal satu komponen racikan.');
            return;
        }
        var task = row.matches('[data-rx-draft]') ? rxSaveDraftRow(row, {}) : rxSaveRow(row, {});
        if (window.careSetStatus) window.careSetStatus('Menyimpan…');
        Promise.resolve(task).then(function () {
            if (window.careSetStatus) window.careSetStatus('✓ Tersimpan');
        }).catch(function (error) {
            if (window.careSetStatus) window.careSetStatus('Gagal menyimpan', true);
            window.alert(error && error.message ? error.message : 'Gagal menyimpan');
        });
    }

    function rxPromoteDraftRow(row, payload) {
        if (!row || !payload || !payload.item_id) return false;
        var kind = row.getAttribute('data-kind') || 'ready';
        var previousKey = rxRowKey(row);

        var editor = document.querySelector('[data-rx-editor]');
        var storeUrl = editor ? editor.getAttribute('data-store') : '';
        if (!storeUrl) return false;
        var itemUrl = storeUrl.replace(/\/?$/, '') + '/' + payload.item_id;

        if (kind === 'racikan' && previousKey) {
            var tbody = row.closest('tbody') || document;
            Array.prototype.slice.call(tbody.querySelectorAll('[data-rx-component-row]'))
                .filter(function (tr) { return tr.getAttribute('data-rx-component-of') === previousKey; })
                .forEach(function (tr) { tr.setAttribute('data-rx-component-of', String(payload.item_id)); });
        }

        row.classList.remove('rx-row--draft');
        row.removeAttribute('data-rx-draft');
        row.setAttribute('data-rx-row', '');
        row.setAttribute('data-rx-key', String(payload.item_id));
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

    function rxHelpUnit(row) {
        var select = row && row.querySelector('[name="unit"]');
        var unit = select ? String(select.value || '').trim().toLowerCase() : '';
        if (!unit && row) {
            var item = rxCatalogItem(rxMedicineIdOf(row));
            unit = item && item.unit ? String(item.unit).trim().toLowerCase() : '';
        }
        if (unit === 'tab') return 'tablet';
        return unit;
    }

    function rxFilterDoseHelp(html, unit) {
        if (!html || !unit) return html || '';
        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        var tagged = wrap.querySelectorAll('[data-sig-units]');
        if (!tagged.length) return html;
        var kept = 0;
        tagged.forEach(function (btn) {
            var list = (btn.getAttribute('data-sig-units') || '').toLowerCase().split(/\s+/);
            if (list.indexOf(unit) === -1) btn.remove();
            else kept += 1;
        });
        return kept ? wrap.innerHTML : html;
    }

    function rxSigPopoverInstance(toggle) {
        if (!toggle || !window.bootstrap || !window.bootstrap.Popover) return null;
        var existing = window.bootstrap.Popover.getInstance(toggle);
        if (existing) existing.dispose();
        var tip = toggle.closest('.rx-sig-tip');
        var template = tip && tip.querySelector('.rx-sig-tip__template');
        var html = template ? template.innerHTML : '';
        if (tip && tip.getAttribute('data-sig-name') === 'dosage') {
            var row = toggle.closest('[data-rx-row], [data-rx-draft], form.rx-form');
            html = rxFilterDoseHelp(html, rxHelpUnit(row));
        }
        return window.bootstrap.Popover.getOrCreateInstance(toggle, {
            html: true,
            sanitize: false,
            trigger: 'manual',
            placement: 'bottom',
            container: 'body',
            customClass: 'rx-sig-popover',
            content: html,
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
        var componentRow = target.closest('[data-rx-component-row]');
        var draft = target.closest('[data-rx-draft]');
        var row = target.closest('[data-rx-row]');
        var active = componentRow ? rxParentRow(componentRow) : (draft || row);
        if (!active) return;
        if (!target.matches('[data-rx-field], [data-rx-signa], [data-rx-qty], [data-typeahead-q], [data-ing-name], [data-ing-qty], [data-ing-dose], [data-rx-medicine-id], [data-ing-medicine-id]')) {
            return;
        }
        rxSetActiveRow(active, target, { openSig: false });
        if (target.matches('[data-ing-dose]')) {
            rxRecalcCompoundQty(active);
            if (componentRow) rxSyncComponentViews(componentRow);
            rxRecalcRow(active);
        }
        if (target.matches('[data-ing-qty]')) {
            target.dataset.manual = '1';
            if (componentRow) rxSyncComponentViews(componentRow);
            rxRecalcRow(active);
        }
        if (target.matches('[data-ing-name]')) {
            if (componentRow) rxSyncComponentViews(componentRow);
        }
        if (target.matches('[data-rx-qty]')) {
            target.dataset.manual = '1';
            var flag = active.querySelector('[name="quantity_manual"]');
            if (flag) flag.value = '1';
            rxShowQtyHint(active, rxAssess(active));
            rxRecalcCompoundQty(active);
            rxRecalcRow(active);
        } else if (target.matches('[name="dosage"], [name="signa_p"], [name="signa_s"], [name="signa_sr"], [name="signa_m"], [name="jho"], [data-rx-signa], [name="notes"], [name="waktu"], [name="unit"], [name="course"]')) {
            rxClearQtyManual(active);
            rxAutoQty(active);
        }
        if (active && active.matches('[data-rx-row]')) active.dataset.dirty = '1';
    }

    document.addEventListener('focusin', function (event) {
        if (event.target.closest('.rx-sig-popover, [data-sig-fill], [data-rx-sig-popover]')) return;
        var componentRow = event.target.closest('[data-rx-component-row]');
        var row = componentRow ? rxParentRow(componentRow) : event.target.closest('[data-rx-row], [data-rx-draft]');
        if (row && row.closest('[data-rx-editor]')) rxSetActiveRow(row, event.target);
    });

    document.addEventListener('mousedown', function (event) {
        if (event.target.closest('.rx-sig-popover, [data-sig-fill], [data-rx-sig-popover]')) return;
        var componentRow = event.target.closest('[data-rx-component-row]');
        var row = componentRow ? rxParentRow(componentRow) : event.target.closest('[data-rx-row], [data-rx-draft]');
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
        if (tipRow) rxSetActiveRow(tipRow, tipBtn, { openSig: false });
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
        var saveBtn = event.target.closest('[data-rx-save]');
        if (saveBtn) {
            rxCommitRow(saveBtn.closest('[data-rx-row], [data-rx-draft]'));
            return;
        }
        var editBtn = event.target.closest('[data-rx-edit]');
        if (editBtn) {
            rxBeginEdit(editBtn.closest('[data-rx-row]'));
            return;
        }
        var cancelEdit = event.target.closest('[data-rx-cancel-edit]');
        if (cancelEdit) {
            rxCancelEdit(cancelEdit.closest('[data-rx-row]'));
            return;
        }
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

        if (event.target.closest('[data-rx-save-all]')) {
            rxSaveAll(event.target.closest('[data-rx-save-all]'));
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
            var compoundHost = event.target.closest('[data-rx-draft], [data-rx-row].is-editing');
            if (compoundHost && (compoundHost.getAttribute('data-kind') || '') === 'racikan') rxAddIngredient(compoundHost);
            return;
        }
        if (event.target.closest('[data-rx-remove-ingredient]')) {
            var ing = event.target.closest('[data-rx-component-row]');
            var pricedRow = rxParentRow(ing);
            if (ing) ing.remove();
            if (pricedRow) {
                rxRecalcCompoundQty(pricedRow);
                rxRecalcRow(pricedRow);
            }
            return;
        }
        if (event.target.closest('[data-rx-draft-cancel]')) {
            var cancelRow = event.target.closest('[data-rx-draft]');
            if (cancelRow) {
                rxComponentRows(cancelRow).forEach(function (tr) { tr.remove(); });
                cancelRow.remove();
            }
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

    function rxEnsureEmptyState() {
        var tbody = document.querySelector('[data-rx-rows]');
        if (!tbody) return;
        var hasRows = tbody.querySelector('[data-rx-row], [data-rx-draft]');
        var empty = tbody.querySelector('[data-rx-empty]');
        if (hasRows) {
            if (empty) empty.remove();
            return;
        }
        if (!empty) {
            var tr = document.createElement('tr');
            tr.setAttribute('data-rx-empty', '');
            tr.innerHTML = '<td colspan="14" class="text-muted text-center py-2">Belum ada item resep.</td>';
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
            var componentRow = this.closest('[data-rx-component-row]');
            if (componentRow) {
                if (!componentRow.classList.contains('is-editing')) return;
            } else {
                var saved = this.closest('[data-rx-row]');
                if (saved && !saved.classList.contains('is-editing')) return;
            }
            var $el = $(this);
            if ($el.data('select2')) return;
            var isIngredient = $el.is('[data-rx-ing-medicine-select]');
            $el.select2({
                width: '100%',
                placeholder: isIngredient ? 'Pilih bahan stok…' : 'Cari obat…',
                allowClear: true,
                minimumInputLength: 2,
                dropdownParent: $(document.body),
                templateResult: function (data) {
                    if (!data.id) return data.text;
                    var item = rxCatalogItem(data.id);
                    if (!item) return data.text;
                    var wrap = document.createElement('div');
                    var title = document.createElement('div');
                    title.textContent = data.text;
                    var meta = document.createElement('div');
                    meta.className = 'small text-muted';
                    meta.textContent = 'Rp ' + rxMoney(item.price) + ' · stok ' + item.stock;
                    wrap.appendChild(title);
                    wrap.appendChild(meta);
                    return wrap;
                },
                language: {
                    noResults: function () { return 'Obat tidak ditemukan'; },
                    searching: function () { return 'Mencari…'; },
                    inputTooShort: function () { return 'Ketik minimal 2 karakter…'; },
                },
            });
            $el.off('select2:select.rxMed select2:clear.rxMed').on('select2:select.rxMed select2:clear.rxMed', function () {
                rxHandleMedicineSelect(this);
            });
        });
    }

    function rxDestroySignaSelects(scope) {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.select2) return;
        $(scope || document).find('[data-signa-select]').each(function () {
            var $el = $(this);
            if ($el.data('select2')) $el.select2('destroy');
        });
    }

    function rxInitSignaSelects(scope) {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.select2) return;
        $(scope || document).find('[data-signa-select]').each(function () {
            var saved = this.closest('[data-rx-row]');
            if (saved && !saved.classList.contains('is-editing')) return;
            var $el = $(this);
            if ($el.data('select2')) return;
            $el.select2({
                width: '100%',
                tags: true,
                placeholder: '–',
                minimumResultsForSearch: Infinity,
                dropdownParent: $(document.body),
            });
        });
    }

    function rxSetSignaValue(row, name, value) {
        var el = row && row.querySelector('[name="' + name + '"]');
        if (!el) return;
        el.value = value;
        var $ = window.jQuery;
        if ($ && $.fn && $.fn.select2 && $(el).data('select2')) $(el).val(value).trigger('change.select2');
    }

    function rxBindDraft(row) {
        if (!row) return row;
        row.querySelectorAll('[data-typeahead]').forEach(function (box) {
            box.dataset.typeaheadBound = '';
        });
        rxDisableBrowserAutofill(row);
        rxInitMedicineSelects(row);
        rxInitSignaSelects(row);
        row.dispatchEvent(new CustomEvent('care:bind-typeahead', { bubbles: true }));
        return row;
    }

    var rxDraftSeq = 0;

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
            row.setAttribute('data-rx-key', 'draft-' + (++rxDraftSeq));
            rxAddIngredient(row);
        }
        rxBindDraft(row);
        if (options.focus !== false) {
            rxSetActiveRow(row);
            var focus = row.querySelector('[data-rx-medicine-select]:not([data-rx-ing-medicine-select])')
                || row.querySelector('[data-rx-medicine-select]')
                || row.querySelector('input[name="name"]');
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
        var key = rxRowKey(draftRow);
        var tpl = document.getElementById('rx-draft-ingredient-template');
        var tbody = draftRow ? draftRow.closest('tbody') : null;
        if (!key || !tpl || !tbody) return;
        var wrap = document.createElement('tbody');
        wrap.innerHTML = tpl.innerHTML.trim().split('__key__').join(key);
        var row = wrap.firstElementChild;
        if (!row) return;
        row.classList.add('is-editing');
        row.setAttribute('data-rx-component-of', key);
        var components = rxComponentRows(draftRow);
        var last = components.length ? components[components.length - 1] : draftRow;
        last.insertAdjacentElement('afterend', row);
        rxBindDraft(row);
        rxRecalcCompoundQty(draftRow);
        rxRecalcRow(draftRow);
    }

    function rxApplySigUnit(row, unit) {
        if (!row || !unit) return;
        var select = row.querySelector('[name="unit"]');
        if (!select) return;
        if (select.tagName !== 'SELECT') {
            select.value = String(unit);
            var view = row.querySelector('[data-rx-unit-view]');
            if (view) view.value = String(unit);
            return;
        }
        var value = String(unit);
        var found = false;
        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.value === value) found = true;
        });
        if (!found) {
            var option = document.createElement('option');
            option.value = value;
            option.textContent = value === 'bungkus' ? 'Bungkus/puyer' : value;
            select.appendChild(option);
        }
        select.value = value;
    }

    function rxSetRowUnit(row, item) {
        if (!row || !item || !item.unit) return;
        var hidden = row.querySelector('[name="unit"]');
        if (hidden) hidden.value = String(item.unit);
        var view = row.querySelector('[data-rx-unit-view]');
        if (view) view.value = item.unit_label || item.unit;
    }

    function rxFillMedicine(row, medicineId) {
        var item = rxCatalogItem(medicineId);
        if (!item) return;
        var strength = row.querySelector('[data-rx-strength]');
        var stock = row.querySelector('[data-rx-stock]');
        var price = row.querySelector('[data-rx-price]');
        var kind = row.getAttribute('data-kind') || 'ready';
        if (strength) strength.textContent = item.strength || '—';
        if (stock) {
            stock.innerHTML = item.stock == null
                ? '—'
                : '<span class="badge ' + (item.stock > 0 ? 'bg-success' : 'bg-danger') + '">' + item.stock + '</span>';
        }
        if (price) {
            price.textContent = rxMoney(item.price || 0);
            price.dataset.raw = String(item.price || 0);
        }
        rxSetRowUnit(row, item);
        var dosage = row.querySelector('[name="dosage"]');
        if (dosage && kind !== 'racikan') {
            if (item.strength) {
                dosage.value = item.strength;
                dosage.readOnly = true;
                dosage.title = 'Dosis mengikuti kekuatan obat';
            } else {
                dosage.readOnly = false;
                dosage.title = '';
                dosage.placeholder = 'cth: 1 tab';
            }
        }
        rxClearQtyManual(row);
        rxAutoQty(row);
        var ingName = row.querySelector('[data-ing-name]');
        if (ingName && item.name) ingName.value = item.name;
        if (kind === 'racikan') rxRecalcCompoundQty(row);
    }

    function rxSaveDraftRow(row, options) {
        options = options || {};
        if (!row || row.dataset.busy === '1') return Promise.resolve();
        if (!rxRowShouldSave(row)) return Promise.resolve();
        var editor = document.querySelector('[data-rx-editor]');
        var storeUrl = editor ? editor.getAttribute('data-store') : '';
        if (!storeUrl) return Promise.reject(new Error('URL simpan tidak ditemukan'));

        var qtyInput = row.querySelector('[data-rx-qty]');
        if (!qtyInput || qtyInput.dataset.manual !== '1') rxAutoQty(row);

        var kind = row.getAttribute('data-kind') || 'ready';
        var body = new FormData();
        body.append('_token', rxCsrf());
        body.append('_form', 'rx-editor-create');
        body.append('kind', kind === 'racikan' ? 'racikan' : 'ready');
        body.append('medication_type', kind === 'racikan' ? 'EP' : 'NC');
        body.append('quantity', (row.querySelector('[name="quantity"]') || {}).value || 1);

        ['dosage', 'frequency', 'waktu', 'jho', 'unit', 'notes', 'course', 'signa_p', 'signa_s', 'signa_sr', 'signa_m', 'quantity_manual', 'name', 'jenis_racikan'].forEach(function (name) {
            var input = row.querySelector('[name="' + name + '"]');
            if (input && String(input.value || '').trim() !== '') body.append(name, input.value);
        });

        var jho = Number((row.querySelector('[name="jho"]') || {}).value || 0);
        if (jho > 0) body.append('duration', jho + ' hari');

        if (kind === 'racikan') {
            var ingredients = rxReadCompoundIngredients(row);
            if (ingredients.length < 1) return Promise.resolve();
            rxAppendCompoundIngredients(body, ingredients);
            rxRecalcRow(row);
            var compoundPrice = row.querySelector('[data-rx-price]');
            if (compoundPrice && compoundPrice.dataset.raw) body.append('unit_price', compoundPrice.dataset.raw);
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

    function rxHandleMedicineSelect(medicineId) {
        if (!medicineId || medicineId.dataset.rxMedLock === '1') return;
        medicineId.dataset.rxMedLock = '1';
        setTimeout(function () { medicineId.dataset.rxMedLock = ''; }, 40);
        var draft = medicineId.closest('[data-rx-draft], [data-rx-component-row]');
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
            return;
        }
        if (!draft || !medicineId.value) return;
        var catalogItem = rxCatalogItem(medicineId.value);
        var name = draft.querySelector('[data-ing-name]');
        if (catalogItem && name) name.value = catalogItem.name || name.value;
        var compoundRow = draft.hasAttribute('data-rx-component-row') ? rxParentRow(draft) : draft.closest('[data-rx-draft], [data-rx-row]');
        if (catalogItem) {
            var nameView = draft.querySelector('[data-ing-name-view]');
            var unitView = draft.querySelector('[data-ing-unit-view]');
            if (nameView) nameView.textContent = catalogItem.name || nameView.textContent;
            if (unitView) unitView.textContent = catalogItem.unit_label || catalogItem.unit || '—';
            var strengthView = draft.querySelector('[data-ing-strength-view]');
            var strengthInput = draft.querySelector('[data-ing-strength]');
            var strengthUnit = draft.querySelector('[data-ing-strength-unit]');
            var unitInput = draft.querySelector('[data-ing-unit]');
            var stockEl = draft.querySelector('[data-ing-stock]');
            if (strengthView) strengthView.textContent = catalogItem.strength || '—';
            if (strengthInput) strengthInput.value = catalogItem.strength_value != null ? String(catalogItem.strength_value) : '';
            if (strengthUnit) strengthUnit.value = catalogItem.strength_unit || '';
            if (unitInput) unitInput.value = catalogItem.unit_label || catalogItem.unit || '';
            if (stockEl) {
                stockEl.textContent = catalogItem.stock == null ? '—' : String(catalogItem.stock);
                stockEl.className = catalogItem.stock > 0 ? 'text-success' : 'text-danger';
            }
        }
        if (compoundRow) {
            rxRecalcCompoundQty(compoundRow);
            rxRecalcRow(compoundRow);
        }
    }

    document.addEventListener('change', function (event) {
        var medicineId = event.target.closest && event.target.closest('[data-rx-medicine-id], [data-ing-medicine-id]');
        if (medicineId) rxHandleMedicineSelect(medicineId);
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
        if ((row.getAttribute('data-kind') || '') === 'racikan') {
            rxRecalcCompoundQty(row);
            rxRecalcRow(row);
        }
    });

    function rxBoot() {
        rxDisableBrowserAutofill(document.getElementById('rx-list'));
        rxInitMedicineSelects(document.getElementById('rx-list'));
        rxInitSignaSelects(document.getElementById('rx-list'));
    }

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        rxBoot();
    } else {
        document.addEventListener('DOMContentLoaded', rxBoot);
    }
})();
</script>
