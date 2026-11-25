@php
    $row = $row ?? [];
    $componentDraft = $componentDraft ?? false;
    $parentKey = $parentKey ?? '__key__';
    $role = ($row['role'] ?? 'active') === 'additive' ? 'additive' : 'active';
    $strength = trim(trim((string) ($row['strength_value'] ?? '')).' '.trim((string) ($row['strength_unit'] ?? '')));
    $dose = $row['dose_amount'] ?? '';
    $doseText = $dose !== '' && $dose !== null
        ? rtrim(rtrim(number_format((float) $dose, 2, '.', ''), '0'), '.')
        : '—';
    $qty = $row['quantity'] ?? 1;
    $unit = (string) ($row['unit'] ?? '');
    $price = $row['price'] ?? null;
    $subtotal = $row['subtotal'] ?? null;
@endphp
<tr class="rx-component-row{{ $componentDraft ? ' is-editing' : '' }}"
    data-rx-component-row
    data-rx-ingredient
    data-rx-component-of="{{ $parentKey }}"
    data-ingredient-role="{{ $role }}">
    <td class="text-center text-muted">·</td>
    <td style="min-width:12rem">
        <span class="rx-when-view fw-semibold" data-ing-name-view>{{ $row['name'] ?? 'Bahan belum diisi' }}</span>
        <div class="rx-when-edit">
            <select class="form-select form-select-sm" data-ing-medicine-id data-rx-medicine-select data-rx-ing-medicine-select autocomplete="off" aria-label="Obat komponen">
                <option value="">Pilih bahan obat…</option>
                @foreach ($medicines as $medicine)
                    <option
                        value="{{ $medicine->id }}"
                        data-name="{{ $medicine->name }}"
                        data-unit="{{ $medicine->unitLabel() }}"
                        data-strength-value="{{ $medicine->strength_value }}"
                        data-strength-unit="{{ $medicine->strength_unit }}"
                        @selected((int) ($row['medicine_id'] ?? 0) === (int) $medicine->id)
                    >{{ $medicine->catalogLabel() }}</option>
                @endforeach
            </select>
            <input type="text" class="form-control form-control-sm mt-1" data-ing-name value="{{ $row['name'] ?? '' }}" placeholder="Nama bahan" maxlength="120" autocomplete="off" @if ($role !== 'additive') hidden @endif>
        </div>
    </td>
    <td class="text-nowrap" @if ($role === 'additive') data-hidden-additive @endif>
        <span data-ing-strength-view>{{ $strength !== '' ? $strength : '—' }}</span>
    </td>
    <td style="min-width:6rem" @if ($role === 'additive') data-hidden-additive @endif>
        <span class="rx-when-view" data-ing-dose-view>{{ $doseText }}</span>
        <span class="rx-when-edit d-flex gap-1">
            <input class="form-control form-control-sm" type="number" data-ing-dose step="0.0001" min="0" value="{{ $dose }}" placeholder="mg" autocomplete="off">
            <button type="button" class="btn btn-sm btn-light border rx-icon-btn" data-rx-ing-calc title="Kalkulator dosis" aria-label="Kalkulator dosis">
                <i class="bx bx-calculator"></i>
            </button>
        </span>
    </td>
    <td class="text-center" @if ($role === 'additive') data-hidden-additive @endif><b data-ing-stock>—</b></td>
    <td class="text-nowrap"><span class="rx-when-view" data-ing-unit-view>{{ $unit !== '' ? $unit : '—' }}</span><span class="rx-when-edit text-muted">{{ $unit !== '' ? $unit : '—' }}</span></td>
    <td class="text-muted text-center">—</td>
    <td class="text-muted text-center">—</td>
    <td class="text-muted text-center">—</td>
    <td style="min-width:3.5rem">
        <span class="rx-when-view" data-ing-qty-view>{{ $qty }}</span>
        <input class="rx-when-edit form-control form-control-sm" type="number" data-ing-qty min="1" value="{{ $qty }}" @if ($role !== 'additive') readonly @endif title="Qty otomatis = dosis × jumlah racikan ÷ kekuatan">
        <span class="text-muted small" data-ing-qty-raw></span>
    </td>
    <td class="text-muted text-center">—</td>
    <td class="rx-col-money text-end text-nowrap" data-rx-comp-price data-raw="{{ $price !== null ? (float) $price : '' }}">{{ $price !== null ? number_format((float) $price, 2, ',', '.') : '—' }}</td>
    <td class="rx-col-money text-end text-nowrap" data-rx-comp-subtotal data-raw="{{ $subtotal !== null ? (float) $subtotal : '' }}">{{ $subtotal !== null ? number_format((float) $subtotal, 2, ',', '.') : '—' }}</td>
    <td class="text-center text-nowrap rx-when-edit">
        <span class="rx-actions">
            <button type="button" class="btn btn-sm btn-soft-danger rx-icon-btn" data-rx-remove-ingredient title="Hapus komponen" aria-label="Hapus komponen">
                <i class="bx bx-trash"></i>
            </button>
        </span>
        <input type="hidden" data-ing-role value="{{ $role }}">
        <input type="hidden" data-ing-additive value="{{ $row['additive_kind'] ?? '' }}">
        <input type="hidden" data-ing-unit value="{{ $unit }}">
        <input type="hidden" data-ing-per value="{{ $row['per_dose_quantity'] ?? '' }}">
        <input type="hidden" data-ing-strength value="{{ $row['strength_value'] ?? '' }}">
        <input type="hidden" data-ing-strength-unit value="{{ $row['strength_unit'] ?? '' }}">
    </td>
</tr>
