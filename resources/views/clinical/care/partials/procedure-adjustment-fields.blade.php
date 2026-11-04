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
@endphp

<div class="procedure-tariff mt-3" data-procedure-tariff data-quote-url="{{ $quoteUrl ?? '' }}">
    <input type="hidden" name="has_adjustment_form" value="1">
    <div class="row g-2">
        <div class="col-md-3">
            <label class="form-label">Tarif normal</label>
            <input type="text" class="form-control" data-tariff-normal value="Rp 0" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label">Jasa dokter</label>
            <input type="text" class="form-control" data-tariff-doctor value="Rp 0" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label">BHP</label>
            <input type="text" class="form-control" data-tariff-bhp value="Rp 0" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label">Jasa klinik</label>
            <input type="text" class="form-control" data-tariff-clinic value="Rp 0" readonly>
        </div>
    </div>

    @if ($canWaive)
        <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" name="waive_doctor_fee" id="{{ $prefix }}-waive" value="1" data-tariff-waive @checked($waived)>
            <label class="form-check-label" for="{{ $prefix }}-waive">Bebaskan Jasa Dokter</label>
        </div>
    @endif

    @if ($canDiscount)
        <div class="row g-2 mt-2" data-tariff-discount-wrap>
            <div class="col-md-4">
                <label class="form-label" for="{{ $prefix }}-discount-type">Diskon tindakan</label>
                <select name="discount_type" id="{{ $prefix }}-discount-type" class="form-select" data-tariff-discount-type>
                    <option value="">Tanpa diskon</option>
                    <option value="nominal" @selected($discountType === 'nominal' && $discountValue !== '' && $discountValue !== null)>Nominal</option>
                    <option value="percent" @selected($discountType === 'percent')>Persentase</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="{{ $prefix }}-discount-value">Nilai diskon</label>
                <input type="number" min="0" step="0.01" name="discount_value" id="{{ $prefix }}-discount-value" class="form-control" data-tariff-discount-value value="{{ $discountValue }}">
            </div>
        </div>
    @endif

    @if ($canWaive || $canDiscount)
        <div class="mt-2">
            <label class="form-label" for="{{ $prefix }}-reason">Alasan pembebasan / diskon</label>
            <textarea name="waiver_reason" id="{{ $prefix }}-reason" class="form-control" rows="2" data-tariff-reason>{{ $reason }}</textarea>
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

    <div class="alert alert-info mt-3 mb-0">
        Total tagihan pasien: <strong data-tariff-total>Rp 0</strong>
        <span class="d-block small" data-tariff-note></span>
    </div>
</div>
