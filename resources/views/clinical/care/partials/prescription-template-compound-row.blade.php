@php
    $index = $index ?? 0;
    $prefix = $prefix ?? ('tpl-compound-'.$index);
    $row = $row ?? [];
    $ingredientRows = array_values($row['ingredients'] ?? ($ingredientRows ?? [
        ['role' => 'active', 'quantity' => 1],
        ['role' => 'active', 'quantity' => 1],
    ]));
@endphp
<div class="rx-card" data-template-row data-template-compound>
    <div class="rx-card__head">
        <strong class="small mb-0">Racikan</strong>
        <button type="button" class="btn btn-sm btn-soft-danger rx-icon-btn" data-remove-template-row title="Hapus racikan" aria-label="Hapus racikan">
            <i class="bx bx-trash"></i>
        </button>
    </div>
    <input type="hidden" name="items[{{ $index }}][kind]" value="racikan">
    @include('clinical.care.partials.prescription-compound-fields', [
        'sigPrefix' => $prefix,
        'fieldPrefix' => 'items['.$index.']',
        'ingredientRows' => $ingredientRows,
        'medicines' => $medicines,
        'medicationType' => $row['medication_type'] ?? ($medicationType ?? 'EP'),
        'dosage' => $row['dosage'] ?? ($dosage ?? ''),
        'frequency' => $row['frequency'] ?? ($frequency ?? ''),
        'duration' => $row['duration'] ?? ($duration ?? ''),
        'course' => $row['course'] ?? ($course ?? ''),
        'quantity' => $row['quantity'] ?? ($quantity ?? 1),
        'requireConfirm' => false,
    ])
</div>
