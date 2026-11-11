@php
    $row = $row ?? [];
    $prefix = $prefix ?? ('tpl-'.$index);
    $medicineId = (string) ($row['medicine_id'] ?? '');
    $selectedMedicine = $medicineId !== '' ? $medicines->firstWhere('id', (int) $medicineId) : null;
    $medicineLabel = $row['medicine_label'] ?? ($selectedMedicine?->catalogLabel() ?? '');
    $unitValue = $row['unit'] ?? ($selectedMedicine?->unitLabel() ?? '');
@endphp
<div class="rx-card" data-template-row>
    <div class="rx-card__head">
        <strong class="small mb-0">Obat</strong>
        <button type="button" class="btn btn-sm btn-soft-danger rx-icon-btn" data-remove-template-row title="Hapus obat" aria-label="Hapus obat">
            <i class="bx bx-trash"></i>
        </button>
    </div>
    <input type="hidden" name="items[{{ $index }}][kind]" value="ready">
    <div class="care-typeahead mb-2" data-typeahead data-typeahead-source="rx-medicine-catalog">
        <label class="form-label" for="{{ $prefix }}-q">Cari obat</label>
        <input type="search" class="form-control" id="{{ $prefix }}-q" data-typeahead-q placeholder="Nama obat" autocomplete="off" value="{{ $medicineLabel }}">
        <input type="hidden" name="items[{{ $index }}][medicine_id]" id="{{ $prefix }}-medicine" data-typeahead-id data-template-medicine-id value="{{ $medicineId }}" required>
        <div class="care-typeahead__list" data-typeahead-results hidden></div>
    </div>
    <div class="row g-2">
        <div class="col-md-6 col-lg-3">
            <label class="form-label" for="{{ $prefix }}-dosage">Dosis</label>
            @include('clinical.care.partials.prescription-sig', ['group' => 'doses', 'target' => $prefix.'-dosage'])
            <x-input name="items[{{ $index }}][dosage]" :id="$prefix.'-dosage'" label="" :value="$row['dosage'] ?? ''" />
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label" for="{{ $prefix }}-frequency">Frekuensi</label>
            @include('clinical.care.partials.prescription-sig', ['group' => 'frequencies', 'target' => $prefix.'-frequency'])
            <x-input name="items[{{ $index }}][frequency]" :id="$prefix.'-frequency'" label="" :value="$row['frequency'] ?? ''" />
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label" for="{{ $prefix }}-duration">Aturan pakai</label>
            @include('clinical.care.partials.prescription-sig', ['group' => 'instructions', 'target' => $prefix.'-duration'])
            <x-input name="items[{{ $index }}][duration]" :id="$prefix.'-duration'" label="" :value="$row['duration'] ?? ''" />
        </div>
        <div class="col-6 col-md-3 col-lg-1">
            <label class="form-label" for="{{ $prefix }}-course">Durasi</label>
            @include('clinical.care.partials.prescription-sig', ['group' => 'courses', 'target' => $prefix.'-course'])
            <x-input name="items[{{ $index }}][course]" :id="$prefix.'-course'" label="" :value="$row['course'] ?? ''" />
        </div>
        <div class="col-3 col-md-2 col-lg-1">
            <x-input name="items[{{ $index }}][quantity]" :id="$prefix.'-qty'" type="number" min="1" label="Qty" :value="$row['quantity'] ?? 1" required />
        </div>
        <div class="col-3 col-md-2 col-lg-1">
            <x-input name="items[{{ $index }}][unit]" :id="$prefix.'-unit'" label="Satuan" :value="$unitValue" data-template-unit />
        </div>
    </div>
</div>
