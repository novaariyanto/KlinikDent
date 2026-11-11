<section class="care-section" id="tindakan">
    <div class="care-section__head d-flex justify-content-between align-items-start gap-2">
        <div>
            <h5 class="care-section__title">Tindakan</h5>
            <p class="care-section__hint">Cari master tindakan. Gigi opsional dari temuan odontogram. Tarif di-snapshot saat dicatat.</p>
        </div>
        @can('procedure.manage')
            @if ($writable)
                <x-button type="button" data-bs-toggle="modal" data-bs-target="#procedure-create-modal" icon="bx bx-plus">Tambah</x-button>
            @endif
        @endcan
    </div>
    <div class="care-section__body">
        <x-table>
            <thead>
                <tr>
                    <th>Tindakan</th>
                    <th>Gigi</th>
                    <th>Qty</th>
                    <th>Tarif pasien</th>
                    <th>Penyesuaian</th>
                    <th>Status tagihan</th>
                    @can('procedure.manage')<th></th>@endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($visit->procedureRecords as $item)
                    <tr>
                        <td>{{ $item->procedure?->name }}@if ($item->icd9_code) <span class="text-muted">({{ $item->icd9_code }})</span>@endif</td>
                        <td>{{ $item->tooth_number ?: '-' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>
                            <strong>Rp {{ number_format((float) $item->price_at_time, 0, ',', '.') }}</strong>
                            @if ((float) $item->tariff_price_at_time > 0 && (float) $item->tariff_price_at_time !== (float) $item->price_at_time)
                                <div class="text-muted small">Normal Rp {{ number_format((float) $item->tariff_price_at_time, 0, ',', '.') }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $item->adjustment_type?->badgeClass() ?? 'bg-secondary' }}">{{ $item->adjustmentLabel() }}</span>
                            @if ($item->waiver_reason)
                                <div class="text-muted small">{{ $item->waiver_reason }}</div>
                            @endif
                        </td>
                        <td>{{ $item->billing_status->label() }}</td>
                        @can('procedure.manage')
                            <td class="text-end text-nowrap">
                                @if ($writable)
                                    <button type="button" class="btn btn-sm btn-soft-primary" data-toggle-panel="#proc-edit-{{ $item->id }}">Ubah</button>
                                @endif
                                @if ($writable && $item->waiver_status?->value === 'pending' && ($canApproveDoctorFeeWaiver ?? false))
                                    <form action="{{ route('care.procedure.waiver.approve', [$visit, $item]) }}" method="POST" class="d-inline" onsubmit="return confirm('Setujui pembebasan jasa dokter?')">
                                        @csrf
                                        <button class="btn btn-sm btn-primary" type="submit">Setujui</button>
                                    </form>
                                @endif
                                @if ($writable && $item->isUnbilled())
                                    <form action="{{ route('care.procedure.destroy', [$visit, $item]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus tindakan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-soft-danger" type="submit">Hapus</button>
                                    </form>
                                @endif
                            </td>
                        @endcan
                    </tr>
                    @can('procedure.manage')
                        @if ($writable)
                            @php($reopenProcedureEdit = old('_form') === 'procedure-update-'.$item->id)
                            <tr id="proc-edit-{{ $item->id }}" @if (! $reopenProcedureEdit) hidden @endif>
                                <td colspan="7">
                                    <form action="{{ route('care.procedure.update', [$visit, $item]) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="_form" value="procedure-update-{{ $item->id }}">
                                        <input type="hidden" name="procedure_id" value="{{ $item->procedure_id }}">
                                        <input type="hidden" name="quantity" value="{{ $item->quantity }}">
                                        @include('clinical.care.partials.icd9-select', [
                                            'id' => 'procedure-icd9-'.$item->id,
                                            'selected' => $reopenProcedureEdit ? old('icd9_code', $item->icd9_code) : $item->icd9_code,
                                            'showError' => $reopenProcedureEdit,
                                        ])
                                        @if ($item->isUnbilled())
                                            @include('clinical.care.partials.procedure-adjustment-fields', [
                                                'prefix' => 'proc-edit-'.$item->id,
                                                'quoteUrl' => $procedureTariffQuoteUrl ?? '',
                                                'procedureId' => $item->procedure_id,
                                                'quantity' => $item->quantity,
                                                'canWaive' => $canWaiveDoctorFee ?? false,
                                                'canDiscount' => $canDiscountProcedure ?? false,
                                                'oldForm' => $reopenProcedureEdit,
                                                'record' => $item,
                                            ])
                                        @endif
                                        <x-button type="submit" icon="bx bx-save">Simpan</x-button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endcan
                @empty
                    <tr><td colspan="7" class="text-muted">Belum ada tindakan.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        @can('procedure.manage')
            @if ($writable)
                @php($reopenProcedure = old('_form') === 'procedure-create')
                <x-modal id="procedure-create-modal" title="Tambah tindakan" size="modal-lg" class="care-modal" :data-show-on-load="$reopenProcedure ? '' : null">
                    <form action="{{ route('care.procedure.store', $visit) }}" method="POST" data-procedure-form>
                        @csrf
                        <input type="hidden" name="_form" value="procedure-create">
                        <div class="care-typeahead mb-3" data-typeahead data-typeahead-items="{{ json_encode($procedures->map(fn ($procedure) => ['id' => $procedure->id, 'code' => $procedure->code, 'label' => $procedure->code.' — '.$procedure->name])->values()) }}">
                            <label class="form-label">Cari tindakan</label>
                            <input type="search" class="form-control" data-typeahead-q placeholder="Nama atau kode tindakan" autocomplete="off" value="{{ $reopenProcedure && old('procedure_id') ? ($procedures->firstWhere('id', old('procedure_id'))?->name) : '' }}">
                            <input type="hidden" name="procedure_id" data-typeahead-id value="{{ $reopenProcedure ? old('procedure_id') : '' }}" required>
                            <div class="care-typeahead__list" data-typeahead-results hidden></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                @include('clinical.care.partials.tooth-select', [
                                    'id' => 'procedure-tooth-create',
                                    'selected' => $reopenProcedure ? old('tooth_number') : '',
                                ])
                            </div>
                            <div class="col-md-2">
                                <x-input name="quantity" id="procedure-quantity-create" type="number" label="Qty" :value="$reopenProcedure ? old('quantity', 1) : 1" required />
                            </div>
                            <div class="col-md-6">
                                @include('clinical.care.partials.icd9-select', [
                                    'id' => 'procedure-icd9-create',
                                    'selected' => $reopenProcedure ? old('icd9_code') : null,
                                    'showError' => $reopenProcedure,
                                ])
                            </div>
                        </div>
                        @include('clinical.care.partials.procedure-adjustment-fields', [
                            'prefix' => 'proc-create',
                            'quoteUrl' => $procedureTariffQuoteUrl ?? '',
                            'procedureId' => $reopenProcedure ? old('procedure_id') : '',
                            'quantity' => $reopenProcedure ? old('quantity', 1) : 1,
                            'canWaive' => $canWaiveDoctorFee ?? false,
                            'canDiscount' => $canDiscountProcedure ?? false,
                            'oldForm' => $reopenProcedure,
                            'record' => null,
                        ])
                        <div class="text-end">
                            <x-button type="submit" icon="bx bx-save">Simpan</x-button>
                        </div>
                    </form>
                </x-modal>
            @endif
        @endcan
    </div>
</section>

<x-modal id="procedure-doctor-cut-modal" title="Tambah potongan custom" size="modal-sm" class="care-modal">
    <div class="mb-3">
        <label class="form-label" for="procedure-doctor-cut-custom">Persentase potong</label>
        <div class="input-group">
            <input type="number" min="1" max="100" step="1" id="procedure-doctor-cut-custom" class="form-control" data-tariff-cut-custom placeholder="Contoh: 20" inputmode="numeric">
            <span class="input-group-text">%</span>
        </div>
        <div class="form-text">Nilai 1–100. Tombol tersimpan di browser ini.</div>
        <div class="invalid-feedback d-block" data-tariff-cut-error hidden></div>
    </div>
    <div class="text-end">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" data-tariff-cut-save>Simpan</button>
    </div>
</x-modal>

<script>
(function () {
    function money(value) {
        var number = Math.round(Number(value || 0));
        return 'Rp ' + number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function moneyPlain(value) {
        return Math.round(Number(value || 0)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function doctorInputValue(box) {
        var doctor = box.querySelector('[data-tariff-doctor]');
        if (!doctor || doctor.disabled || doctor.value === '') return null;
        return doctor.value;
    }

    var DOCTOR_CUT_DEFAULTS = [25, 50, 100];
    var DOCTOR_CUT_STORAGE = 'klinikdent.doctorFeeCutPresets';
    var DOCTOR_CUT_COLORS = ['bg-primary', 'bg-success', 'bg-info text-dark', 'bg-warning text-dark', 'bg-danger', 'bg-dark'];

    function doctorCutColor(percent) {
        var pct = Math.round(Number(percent) || 0);
        return DOCTOR_CUT_COLORS[Math.abs(pct) % DOCTOR_CUT_COLORS.length];
    }

    function readCustomCuts() {
        try {
            var raw = localStorage.getItem(DOCTOR_CUT_STORAGE);
            var parsed = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(parsed)) return [];
            return parsed
                .map(function (n) { return Math.round(Number(n)); })
                .filter(function (n) { return n >= 1 && n <= 100 && DOCTOR_CUT_DEFAULTS.indexOf(n) === -1; })
                .filter(function (n, i, arr) { return arr.indexOf(n) === i; })
                .sort(function (a, b) { return a - b; });
        } catch (e) {
            return [];
        }
    }

    function writeCustomCuts(cuts) {
        try {
            localStorage.setItem(DOCTOR_CUT_STORAGE, JSON.stringify(cuts));
        } catch (e) {}
    }

    function applyDoctorCut(box, percent) {
        var doctor = box.querySelector('[data-tariff-doctor]');
        var master = box.querySelector('[data-tariff-doctor-master]');
        var waive = box.querySelector('[data-tariff-waive]');
        if (!doctor || doctor.disabled || (waive && waive.checked)) return;
        var masterFee = master && master.value !== '' ? Number(master.value) : Number(doctor.max || 0);
        if (!(masterFee >= 0)) return;
        var pct = Math.max(0, Math.min(100, Number(percent) || 0));
        var remaining = Math.round(masterFee * (100 - pct) / 100);
        if (remaining < 0) remaining = 0;
        if (remaining > masterFee) remaining = Math.round(masterFee);
        doctor.value = String(remaining);
        box.setAttribute('data-doctor-dirty', '1');
        loadQuote(box);
    }

    function renderDoctorCuts(box) {
        var list = box.querySelector('[data-tariff-cut-list]');
        if (!list) return;
        var custom = readCustomCuts();
        list.innerHTML = '';

        function appendCut(percent, isCustom) {
            var group = document.createElement('span');
            group.className = 'procedure-tariff__cut-group';

            var label = document.createElement('label');
            label.className = 'badge ' + doctorCutColor(percent) + ' procedure-tariff__cut-btn' + (isCustom ? ' is-custom' : '');
            label.setAttribute('data-tariff-cut', String(percent));
            label.setAttribute('role', 'button');
            label.setAttribute('tabindex', '0');
            label.title = 'Potong ' + percent + '% dari jasa dokter master';
            label.textContent = '-' + percent + '%';
            group.appendChild(label);

            if (isCustom) {
                var remove = document.createElement('label');
                remove.className = 'badge bg-danger procedure-tariff__cut-btn procedure-tariff__cut-remove';
                remove.setAttribute('data-tariff-cut-remove', String(percent));
                remove.setAttribute('role', 'button');
                remove.setAttribute('tabindex', '0');
                remove.title = 'Hapus label custom';
                remove.textContent = '×';
                group.appendChild(remove);
            }

            list.appendChild(group);
        }

        DOCTOR_CUT_DEFAULTS.forEach(function (percent) { appendCut(percent, false); });
        custom.forEach(function (percent) { appendCut(percent, true); });

        var reset = document.createElement('label');
        reset.className = 'badge bg-dark procedure-tariff__cut-btn';
        reset.setAttribute('data-tariff-cut-reset', '1');
        reset.setAttribute('role', 'button');
        reset.setAttribute('tabindex', '0');
        reset.title = 'Reset jasa dokter ke nilai penuh';
        reset.textContent = 'Reset';
        list.appendChild(reset);

        var add = document.createElement('label');
        add.className = 'badge bg-secondary procedure-tariff__cut-btn';
        add.setAttribute('data-tariff-cut-open-modal', '1');
        add.setAttribute('role', 'button');
        add.setAttribute('tabindex', '0');
        add.title = 'Tambah potongan custom';
        add.textContent = '+';
        list.appendChild(add);
    }

    function syncDoctorCutDisabled(box) {
        var doctor = box.querySelector('[data-tariff-doctor]');
        var disabled = !doctor || doctor.disabled;
        box.querySelectorAll('[data-tariff-cut], [data-tariff-cut-reset], [data-tariff-cut-open-modal], [data-tariff-cut-remove]').forEach(function (el) {
            el.classList.toggle('is-disabled', disabled);
            el.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            el.tabIndex = disabled ? -1 : 0;
        });
    }

    function refreshAllDoctorCuts() {
        document.querySelectorAll('[data-procedure-tariff]').forEach(function (box) {
            renderDoctorCuts(box);
            syncDoctorCutDisabled(box);
        });
    }

    function openDoctorCutModal() {
        var modalEl = document.getElementById('procedure-doctor-cut-modal');
        if (!modalEl || !window.bootstrap || !bootstrap.Modal) return;
        var input = modalEl.querySelector('[data-tariff-cut-custom]');
        var error = modalEl.querySelector('[data-tariff-cut-error]');
        if (input) input.value = '';
        if (error) {
            error.hidden = true;
            error.textContent = '';
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        setTimeout(function () {
            if (input) input.focus();
        }, 150);
    }

    function saveDoctorCutFromModal() {
        var modalEl = document.getElementById('procedure-doctor-cut-modal');
        if (!modalEl) return;
        var input = modalEl.querySelector('[data-tariff-cut-custom]');
        var error = modalEl.querySelector('[data-tariff-cut-error]');
        var pct = Math.round(Number(input && input.value));
        if (!(pct >= 1 && pct <= 100)) {
            if (error) {
                error.hidden = false;
                error.textContent = 'Isi persentase antara 1 sampai 100.';
            }
            return;
        }
        var next = readCustomCuts();
        if (DOCTOR_CUT_DEFAULTS.indexOf(pct) === -1 && next.indexOf(pct) === -1) {
            next.push(pct);
            writeCustomCuts(next);
        }
        refreshAllDoctorCuts();
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
    }

    function syncDoctorState(box, waived) {
        var doctor = box.querySelector('[data-tariff-doctor]');
        if (!doctor) return;
        doctor.disabled = !!waived;
        if (waived) {
            doctor.value = '0';
            box.removeAttribute('data-doctor-dirty');
        }
        syncDoctorCutDisabled(box);
    }

    function loadQuote(box, options) {
        options = options || {};
        var url = box.getAttribute('data-quote-url');
        if (!url) return;
        var form = box.closest('form');
        if (!form) return;
        var procedureId = form.querySelector('[name="procedure_id"]');
        var quantity = form.querySelector('[name="quantity"]');
        if (!procedureId || !procedureId.value) return;

        var params = new URLSearchParams();
        params.set('procedure_id', procedureId.value);
        params.set('quantity', quantity && quantity.value ? quantity.value : '1');
        var waive = box.querySelector('[data-tariff-waive]');
        if (waive && waive.checked) params.set('waive_doctor_fee', '1');
        var doctorFee = doctorInputValue(box);
        if (doctorFee !== null && !(waive && waive.checked)) params.set('doctor_fee', doctorFee);
        var discountType = box.querySelector('[data-tariff-discount-type]');
        var discountValue = box.querySelector('[data-tariff-discount-value]');
        if (discountType && discountType.value) params.set('discount_type', discountType.value);
        if (discountValue && discountValue.value) params.set('discount_value', discountValue.value);

        fetch(url + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.json(); }).then(function (data) {
            var normal = box.querySelector('[data-tariff-normal]');
            var doctor = box.querySelector('[data-tariff-doctor]');
            var master = box.querySelector('[data-tariff-doctor-master]');
            var bhp = box.querySelector('[data-tariff-bhp]');
            var clinic = box.querySelector('[data-tariff-clinic]');
            var dirty = box.getAttribute('data-doctor-dirty') === '1';
            var masterFee = data.doctor_fee_master != null ? data.doctor_fee_master : data.doctor_fee;

            if (master) master.value = String(masterFee);
            if (doctor) {
                doctor.min = '0';
                doctor.max = String(Math.round(Number(masterFee || 0)));
                if (options.resetDoctor || !dirty || (waive && waive.checked)) {
                    var chargedDoctor = data.doctor_fee_charged != null ? data.doctor_fee_charged : data.doctor_fee;
                    doctor.value = String(Math.round(Number(chargedDoctor || 0)));
                    if (options.resetDoctor) box.removeAttribute('data-doctor-dirty');
                } else {
                    var current = Number(doctor.value || 0);
                    var maxFee = Number(masterFee || 0);
                    if (current > maxFee) doctor.value = String(Math.round(maxFee));
                    if (current < 0) doctor.value = '0';
                }
            }
            var tariffMaster = data.tariff_price_master != null ? data.tariff_price_master : data.tariff_price;
            if (normal) normal.value = moneyPlain(tariffMaster);
            if (bhp) bhp.value = String(data.bhp_amount || 0);
            if (clinic) clinic.value = String(data.clinic_fee || 0);

            var total = box.querySelector('[data-tariff-total]');
            if (total) total.textContent = money(data.charged_line_total || data.charged_unit_price);
            var note = box.querySelector('[data-tariff-note]');
            if (note) {
                var qty = Number((quantity && quantity.value) || 1);
                var masterDoctor = Number(data.doctor_fee_master != null ? data.doctor_fee_master : 0);
                var chargedDoctorUnit = Number(
                    data.doctor_fee_charged != null ? data.doctor_fee_charged : (data.doctor_fee || 0)
                );
                var reductionUnit = Number(data.doctor_fee_reduction || Math.max(0, masterDoctor - chargedDoctorUnit));
                var reduction = reductionUnit * qty;
                var masterDoctorTotal = masterDoctor * qty;
                var chargedDoctorTotal = chargedDoctorUnit * qty;
                var tariffMasterTotal = Number(tariffMaster) * qty;
                var chargedTotal = Number(data.charged_line_total != null
                    ? data.charged_line_total
                    : (data.charged_unit_price || 0) * qty);
                var lines = [];
                if (data.requires_approval) {
                    lines.push('Pembebasan menunggu persetujuan. Tagihan pasien masih memakai tarif normal.');
                } else if (reduction > 0) {
                    lines.push(
                        'Jasa dokter semula ' + money(masterDoctorTotal)
                        + ' dikurangi ' + money(reduction)
                        + ' menjadi ' + money(chargedDoctorTotal) + '.'
                    );
                    lines.push(
                        'Tarif normal ' + money(tariffMasterTotal)
                        + ' − ' + money(reduction)
                        + ' = ' + money(chargedTotal) + '.'
                    );
                }
                if (!data.requires_approval && data.adjustment_type === 'doctor_fee_waiver') {
                    lines.push('Jasa dokter dibebaskan; BHP dan jasa klinik tetap dihitung.');
                } else if (!data.requires_approval && data.adjustment_type === 'procedure_discount') {
                    lines.push('Diskon tindakan sudah dipotong dari total tarif.');
                }
                note.innerHTML = lines.map(function (line) {
                    return '<div>' + line + '</div>';
                }).join('');
            }
        }).catch(function () {});
    }

    function syncReasonVisibility(box) {
        var reasonWrap = box.querySelector('[data-tariff-reason-wrap]');
        if (!reasonWrap) return;
        var discountWrap = box.querySelector('[data-tariff-discount-wrap]');
        var waive = box.querySelector('[data-tariff-waive]');
        var discountOpen = discountWrap && !discountWrap.hidden;
        var waived = waive && waive.checked;
        reasonWrap.hidden = !(discountOpen || waived);
        if (reasonWrap.hidden) {
            var reason = box.querySelector('[data-tariff-reason]');
            if (reason) reason.value = '';
        }
    }

    function setDiscountOpen(box, open) {
        var wrap = box.querySelector('[data-tariff-discount-wrap]');
        var toggle = box.querySelector('[data-tariff-discount-toggle]');
        if (!wrap || !toggle) return;
        wrap.hidden = !open;
        toggle.hidden = open;
        if (!open) {
            var discountType = box.querySelector('[data-tariff-discount-type]');
            var discountValue = box.querySelector('[data-tariff-discount-value]');
            if (discountType) discountType.value = '';
            if (discountValue) discountValue.value = '';
            loadQuote(box);
        }
        syncReasonVisibility(box);
    }

    function bindBox(box) {
        var form = box.closest('form');
        if (!form) return;

        renderDoctorCuts(box);
        syncDoctorCutDisabled(box);

        var cutRoot = box.querySelector('[data-tariff-doctor-cuts]');
        if (cutRoot) {
            function handleCutAction(event) {
                var target = event.target.closest('[data-tariff-cut-remove], [data-tariff-cut], [data-tariff-cut-reset], [data-tariff-cut-open-modal]');
                if (!target || target.classList.contains('is-disabled')) return;
                if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();

                if (target.hasAttribute('data-tariff-cut-remove')) {
                    var removePct = Math.round(Number(target.getAttribute('data-tariff-cut-remove')));
                    writeCustomCuts(readCustomCuts().filter(function (n) { return n !== removePct; }));
                    refreshAllDoctorCuts();
                    return;
                }
                if (target.hasAttribute('data-tariff-cut')) {
                    applyDoctorCut(box, target.getAttribute('data-tariff-cut'));
                    return;
                }
                if (target.hasAttribute('data-tariff-cut-reset')) {
                    applyDoctorCut(box, 0);
                    return;
                }
                if (target.hasAttribute('data-tariff-cut-open-modal')) {
                    openDoctorCutModal();
                }
            }
            cutRoot.addEventListener('click', handleCutAction);
            cutRoot.addEventListener('keydown', handleCutAction);
        }

        var discountToggle = box.querySelector('[data-tariff-discount-toggle]');
        var discountHide = box.querySelector('[data-tariff-discount-hide]');
        if (discountToggle) {
            discountToggle.addEventListener('click', function () {
                setDiscountOpen(box, true);
            });
        }
        if (discountHide) {
            discountHide.addEventListener('click', function () {
                setDiscountOpen(box, false);
            });
        }

        var doctor = box.querySelector('[data-tariff-doctor]');
        if (doctor && doctor.value !== '') {
            box.setAttribute('data-doctor-dirty', '1');
        }

        ['change', 'input'].forEach(function (eventName) {
            form.addEventListener(eventName, function (event) {
                if (!event.target) return;
                if (event.target.matches('[data-tariff-doctor]')) {
                    var master = box.querySelector('[data-tariff-doctor-master]');
                    var max = master && master.value !== '' ? Number(master.value) : null;
                    var value = Number(event.target.value || 0);
                    if (max !== null && value > max) {
                        event.target.value = String(Math.round(max));
                    }
                    if (value < 0) event.target.value = '0';
                    box.setAttribute('data-doctor-dirty', '1');
                    loadQuote(box);
                    return;
                }
                if (event.target.matches('[name="procedure_id"]')) {
                    box.removeAttribute('data-doctor-dirty');
                    syncDoctorState(box, false);
                    var waiveReset = box.querySelector('[data-tariff-waive]');
                    if (waiveReset) waiveReset.checked = false;
                    loadQuote(box, { resetDoctor: true });
                    return;
                }
                if (event.target.matches('[name="quantity"], [data-tariff-waive], [data-tariff-discount-type], [data-tariff-discount-value]')) {
                    if (event.target.matches('[data-tariff-waive]')) {
                        syncDoctorState(box, event.target.checked);
                        if (event.target.checked) setDiscountOpen(box, false);
                        else loadQuote(box, { resetDoctor: true });
                        syncReasonVisibility(box);
                        if (event.target.checked) loadQuote(box);
                        return;
                    }
                    loadQuote(box);
                }
            });
        });
        loadQuote(box, { resetDoctor: box.getAttribute('data-doctor-dirty') !== '1' });
    }

    document.querySelectorAll('[data-procedure-tariff]').forEach(bindBox);

    var cutModal = document.getElementById('procedure-doctor-cut-modal');
    if (cutModal) {
        var saveBtn = cutModal.querySelector('[data-tariff-cut-save]');
        var cutInput = cutModal.querySelector('[data-tariff-cut-custom]');
        if (saveBtn) saveBtn.addEventListener('click', saveDoctorCutFromModal);
        if (cutInput) {
            cutInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    saveDoctorCutFromModal();
                }
            });
        }
    }
})();
</script>
