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
                        <div class="row">
                            <div class="col-md-6">
                                @include('clinical.care.partials.tooth-select', [
                                    'id' => 'procedure-tooth-create',
                                    'selected' => $reopenProcedure ? old('tooth_number') : '',
                                ])
                            </div>
                            <div class="col-md-6">
                                <x-input name="quantity" id="procedure-quantity-create" type="number" label="Qty" :value="$reopenProcedure ? old('quantity', 1) : 1" required />
                            </div>
                            <div class="col-md-12">
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

<script>
(function () {
    function money(value) {
        var number = Math.round(Number(value || 0));
        return 'Rp ' + number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function boxValue(box, selector) {
        var el = box.querySelector(selector);
        return el ? el.value : '';
    }

    function setText(box, selector, text) {
        var el = box.querySelector(selector);
        if (el) el.value !== undefined && el.tagName === 'INPUT' ? el.value = text : (el.textContent = text);
    }

    function loadQuote(box) {
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
        var discountType = box.querySelector('[data-tariff-discount-type]');
        var discountValue = box.querySelector('[data-tariff-discount-value]');
        if (discountType && discountType.value) params.set('discount_type', discountType.value);
        if (discountValue && discountValue.value) params.set('discount_value', discountValue.value);

        fetch(url + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.json(); }).then(function (data) {
            box.querySelector('[data-tariff-normal]').value = money(data.tariff_price);
            box.querySelector('[data-tariff-doctor]').value = money(data.doctor_fee);
            box.querySelector('[data-tariff-bhp]').value = money(data.bhp_amount);
            box.querySelector('[data-tariff-clinic]').value = money(data.clinic_fee);
            var total = box.querySelector('[data-tariff-total]');
            if (total) total.textContent = money(data.charged_line_total || data.charged_unit_price);
            var note = box.querySelector('[data-tariff-note]');
            if (note) {
                note.textContent = data.requires_approval
                    ? 'Pembebasan menunggu persetujuan. Tagihan pasien masih memakai tarif normal.'
                    : (data.adjustment_type === 'doctor_fee_waiver'
                        ? 'Jasa dokter dibebaskan. BHP dan jasa klinik tetap dihitung.'
                        : (data.adjustment_type === 'procedure_discount'
                            ? 'Diskon tindakan sudah dipotong dari total tarif.'
                            : ''));
            }
        }).catch(function () {});
    }

    function bindBox(box) {
        var form = box.closest('form');
        if (!form) return;
        ['change', 'input'].forEach(function (eventName) {
            form.addEventListener(eventName, function (event) {
                if (!event.target) return;
                if (event.target.matches('[name="procedure_id"], [name="quantity"], [data-tariff-waive], [data-tariff-discount-type], [data-tariff-discount-value]')) {
                    if (event.target.matches('[data-tariff-waive]') && event.target.checked) {
                        var discountType = box.querySelector('[data-tariff-discount-type]');
                        var discountValue = box.querySelector('[data-tariff-discount-value]');
                        if (discountType) discountType.value = '';
                        if (discountValue) discountValue.value = '';
                    }
                    loadQuote(box);
                }
            });
        });
        loadQuote(box);
    }

    document.querySelectorAll('[data-procedure-tariff]').forEach(bindBox);
})();
</script>
