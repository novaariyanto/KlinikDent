@php
    $row = $row ?? [];
    $role = ($row['role'] ?? 'active') === 'additive' ? 'additive' : 'active';
    $prefix = $sigPrefix.'-ing-'.$index;
    $fieldPrefix = $fieldPrefix ?? null;
    $base = $fieldPrefix
        ? $fieldPrefix.'[ingredients]['.$index.']'
        : 'ingredients['.$index.']';
    $name = trim((string) ($row['name'] ?? ''));
    $unit = trim((string) ($row['unit'] ?? ''));
    $per = $row['per_dose_quantity'] ?? '';
    $total = $row['quantity'] ?? 1;
    $detailParts = array_filter([
        $role === 'additive' ? 'Tambahan' : 'Bahan obat',
        filled($per) ? ('Per bks '.$per.($unit ? ' '.$unit : '')) : null,
        'Total '.$total.($unit ? ' '.$unit : ''),
    ]);
@endphp
<div class="rx-ingredient-item" data-ingredient-row data-ingredient-role="{{ $role }}">
    <div class="rx-ingredient-item__body">
        <div class="fw-semibold" data-ingredient-display-name>{{ $name !== '' ? $name : 'Bahan belum diisi' }}</div>
        <div class="rx-meta mb-0" data-ingredient-display-detail>{{ implode(' · ', $detailParts) }}</div>
    </div>
    <div class="rx-actions">
        <button type="button" class="btn btn-sm btn-soft-primary rx-icon-btn" data-edit-ingredient title="Ubah bahan" aria-label="Ubah bahan">
            <i class="bx bx-edit"></i>
        </button>
        <button type="button" class="btn btn-sm btn-soft-danger rx-icon-btn" data-remove-ingredient title="Hapus bahan" aria-label="Hapus bahan">
            <i class="bx bx-trash"></i>
        </button>
    </div>

    <input type="hidden" name="{{ $base }}[role]" value="{{ $role }}" data-ingredient-role-input>
    <input type="hidden" name="{{ $base }}[additive_kind]" value="{{ $row['additive_kind'] ?? '' }}" data-additive-kind>
    <input type="hidden" name="{{ $base }}[name]" value="{{ $row['name'] ?? '' }}" data-ingredient-name>
    <input type="hidden" name="{{ $base }}[medicine_id]" value="{{ $row['medicine_id'] ?? '' }}" data-ingredient-medicine>
    <input type="hidden" name="{{ $base }}[per_dose_quantity]" value="{{ $row['per_dose_quantity'] ?? '' }}" data-per-dose>
    <input type="hidden" name="{{ $base }}[quantity]" value="{{ $row['quantity'] ?? 1 }}" data-total-qty>
    <input type="hidden" name="{{ $base }}[unit]" value="{{ $row['unit'] ?? '' }}" data-ingredient-unit>
    <input type="hidden" name="{{ $base }}[strength_value]" value="{{ $row['strength_value'] ?? '' }}" data-strength-value data-compound-strength-field>
    <input type="hidden" name="{{ $base }}[strength_unit]" value="{{ $row['strength_unit'] ?? '' }}" data-strength-unit data-compound-strength-field>
</div>
