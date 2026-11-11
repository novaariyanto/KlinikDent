@php
    $prefix = $prefix ?? 'proc';
    $record = $record ?? null;
    $oldForm = $oldForm ?? false;
    $canWaive = $canWaive ?? false;
    $canDiscount = $canDiscount ?? false;
    $waived = $oldForm ? (bool) old('waive_doctor_fee') : ($record?->adjustment_type?->value === 'doctor_fee_waiver');
    $discountType = $oldForm ? old('discount_type', 'nominal') : ($record?->discount_type?->value ?? 'nominal');
    $discountValue = $oldForm ? old('discount_value') : ($record?->discount_value ?? '');
    $reason = $oldForm ? old('waiver_reason') : ($record?->waiver_reason ?? '');
    $doctorFeeValue = $oldForm
        ? old('doctor_fee', $record?->doctor_fee_at_time)
        : ($record?->doctor_fee_at_time ?? '');
    $hasDiscount = filled($discountValue) && (string) $discountValue !== '0' && filled($discountType);
    $showDiscountForm = $oldForm
        ? (filled(old('discount_type')) || filled(old('discount_value')))
        : $hasDiscount;
    $showReason = $waived || $showDiscountForm;
@endphp

<div class="procedure-tariff mt-3" data-procedure-tariff data-quote-url="{{ $quoteUrl ?? '' }}">
    <input type="hidden" name="has_adjustment_form" value="1">

    <div class="procedure-tariff__grid">
        <div class="procedure-tariff__field">
            <label class="form-label" for="{{ $prefix }}-tariff-normal">Tarif normal</label>
            <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="text" id="{{ $prefix }}-tariff-normal" class="form-control" data-tariff-normal value="0" readonly>
            </div>
            <div class="form-text">Nilai master tetap (tidak berubah).</div>
        </div>
        <div class="procedure-tariff__field">
            <label class="form-label" for="{{ $prefix }}-doctor-fee">Jasa dokter</label>
            <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input
                    type="number"
                    min="0"
                    step="1"
                    name="doctor_fee"
                    id="{{ $prefix }}-doctor-fee"
                    class="form-control"
                    data-tariff-doctor
                    value="{{ $doctorFeeValue !== '' && $doctorFeeValue !== null ? (int) round((float) $doctorFeeValue) : '' }}"
                    @disabled($waived)
                    inputmode="numeric"
                >
            </div>
            <div class="procedure-tariff__cuts" data-tariff-doctor-cuts>
                <div class="procedure-tariff__cut-list" data-tariff-cut-list></div>
            </div>
            <div class="form-text">Potong dari jasa dokter master. Custom lewat tombol +.</div>
            @error('doctor_fee')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>

    {{-- BHP & jasa klinik disembunyikan; tetap dihitung di backend --}}
    <input type="hidden" data-tariff-bhp value="0">
    <input type="hidden" data-tariff-clinic value="0">
    <input type="hidden" data-tariff-doctor-master value="">

    <div class="procedure-tariff__actions">
        @if ($canWaive)
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="waive_doctor_fee" id="{{ $prefix }}-waive" value="1" data-tariff-waive @checked($waived)>
                <label class="form-check-label" for="{{ $prefix }}-waive">Bebaskan jasa dokter</label>
            </div>
        @endif

        @if ($canDiscount)
            <div data-tariff-discount-panel>
                <button type="button" class="btn btn-sm btn-soft-secondary" data-tariff-discount-toggle @if ($showDiscountForm) hidden @endif>
                    <i class="bx bx-purchase-tag-alt me-1"></i>Diskon
                </button>
                <div class="procedure-tariff__discount" data-tariff-discount-wrap @if (! $showDiscountForm) hidden @endif>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold">Diskon tindakan</span>
                        <button type="button" class="btn btn-sm btn-link text-muted p-0" data-tariff-discount-hide>Sembunyikan</button>
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label" for="{{ $prefix }}-discount-type">Jenis</label>
                            <select name="discount_type" id="{{ $prefix }}-discount-type" class="form-select" data-tariff-discount-type>
                                <option value="">Tanpa diskon</option>
                                <option value="nominal" @selected($discountType === 'nominal' && $discountValue !== '' && $discountValue !== null)>Nominal</option>
                                <option value="percent" @selected($discountType === 'percent')>Persentase</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="{{ $prefix }}-discount-value">Nilai</label>
                            <input type="number" min="0" step="0.01" name="discount_value" id="{{ $prefix }}-discount-value" class="form-control" data-tariff-discount-value value="{{ $discountValue }}">
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($canWaive || $canDiscount)
        <div class="mt-3" data-tariff-reason-wrap @if (! $showReason) hidden @endif>
            <label class="form-label" for="{{ $prefix }}-reason">Alasan pembebasan / diskon</label>
            <textarea name="waiver_reason" id="{{ $prefix }}-reason" class="form-control" rows="2" data-tariff-reason placeholder="Wajib jika bebaskan jasa dokter atau beri diskon">{{ $reason }}</textarea>
            @error('waiver_reason')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            @error('waive_doctor_fee')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            @error('discount_value')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="procedure-tariff__total alert alert-info mb-0">
        <div class="d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
            <span>Total tagihan pasien</span>
            <strong data-tariff-total>Rp 0</strong>
        </div>
        <div class="small mt-1" data-tariff-note></div>
    </div>
</div>
