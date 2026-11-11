@php
    $sigPrefix = $sigPrefix ?? 'compound';
    $fieldPrefix = $fieldPrefix ?? null;
    $requireConfirm = $requireConfirm ?? true;
    $field = function (string $name) use ($fieldPrefix): string {
        return $fieldPrefix ? $fieldPrefix.'['.$name.']' : $name;
    };
    $ingredientRows = array_values($ingredientRows ?? []);
@endphp
<div class="rx-form" data-compound-form>
    <p class="form-text mb-2">AC, PC, PRN, HS, SOS, cth, csd = bahasa resep (bukan kode SATUSEHAT). Klik pilihan atau ketik manual.</p>
    <div class="row g-2">
        <div class="col-md-4 col-lg-3">
            <label class="form-label" for="{{ $sigPrefix }}-type">Tipe racikan</label>
            <select class="form-select mb-3" name="{{ $field('medication_type') }}" id="{{ $sigPrefix }}-type" required data-compound-type>
                @foreach (\App\Enums\MedicationTypeCode::compoundOptions() as $type)
                    <option value="{{ $type->value }}" @selected(($medicationType ?? 'EP') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <p class="form-text mt-n2 mb-0">EP = bagi sama rata. SD = d.t.d.</p>
        </div>
        <div class="col-md-4 col-lg-3">
            <label class="form-label" for="{{ $sigPrefix }}-dosage">Bentuk</label>
            @include('clinical.care.partials.prescription-sig', ['group' => 'forms', 'target' => $sigPrefix.'-dosage'])
            <x-input :name="$field('dosage')" :id="$sigPrefix.'-dosage'" label="" :value="$dosage ?? ''" required />
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="{{ $sigPrefix }}-quantity">Jumlah bungkus</label>
            <x-input :name="$field('quantity')" :id="$sigPrefix.'-quantity'" type="number" min="1" label="" :value="$quantity ?? 1" data-pack-qty required />
        </div>
        <div class="col-6 col-md-4 col-lg-4">
            <label class="form-label" for="{{ $sigPrefix }}-frequency">Frekuensi</label>
            @include('clinical.care.partials.prescription-sig', ['group' => 'frequencies', 'target' => $sigPrefix.'-frequency'])
            <x-input :name="$field('frequency')" :id="$sigPrefix.'-frequency'" label="" :value="$frequency ?? ''" required />
        </div>
        <div class="col-md-4 col-lg-4">
            <label class="form-label" for="{{ $sigPrefix }}-duration">Aturan pakai</label>
            @include('clinical.care.partials.prescription-sig', ['group' => 'instructions', 'target' => $sigPrefix.'-duration'])
            <x-input :name="$field('duration')" :id="$sigPrefix.'-duration'" label="" :value="$duration ?? ''" required />
        </div>
        <div class="col-md-4 col-lg-4">
            <label class="form-label" for="{{ $sigPrefix }}-course">Durasi</label>
            @include('clinical.care.partials.prescription-sig', ['group' => 'courses', 'target' => $sigPrefix.'-course'])
            <x-input :name="$field('course')" :id="$sigPrefix.'-course'" label="" :value="$course ?? ''" />
        </div>
    </div>

    <div class="rx-ingredient-panel mt-2">
        <div class="rx-toolbar justify-content-between align-items-center mb-2">
            <strong class="small mb-0">Bahan obat</strong>
            <div class="rx-toolbar">
                <button type="button" class="btn btn-sm btn-primary" data-add-ingredient title="Tambah bahan" aria-label="Tambah bahan">
                    <i class="bx bx-plus me-1"></i>Tambah
                </button>
                @foreach (\App\Support\Clinical\PrescriptionSigCatalog::additives() as $additive)
                    <button
                        type="button"
                        class="btn btn-sm btn-light border"
                        data-add-additive="{{ $additive['kind'] }}"
                        data-additive-name="{{ $additive['name'] }}"
                        data-additive-unit="{{ $additive['unit'] }}"
                        title="{{ $additive['hint'] }}"
                        aria-label="{{ $additive['hint'] }}"
                    >+ {{ $additive['label'] }}</button>
                @endforeach
            </div>
        </div>

        <div data-ingredient-list class="rx-ingredient-list">
            @forelse ($ingredientRows as $index => $row)
                @include('clinical.care.partials.prescription-ingredient-row', [
                    'row' => $row,
                    'index' => $index,
                    'sigPrefix' => $sigPrefix,
                    'medicines' => $medicines,
                    'fieldPrefix' => $fieldPrefix,
                ])
            @empty
            @endforelse
        </div>
        <p class="text-muted small mb-0 mt-2" data-ingredient-empty @if (count($ingredientRows) > 0) hidden @endif>
            Belum ada bahan. Klik <strong>Tambah</strong> untuk mengisi komposisi.
        </p>

        <template data-ingredient-template>
            @include('clinical.care.partials.prescription-ingredient-row', [
                'row' => ['role' => 'active', 'name' => '', 'quantity' => 1],
                'index' => '__i__',
                'sigPrefix' => $sigPrefix,
                'medicines' => $medicines,
                'fieldPrefix' => $fieldPrefix,
            ])
        </template>
    </div>

    <p class="form-text mb-2 mt-2">Sakarin, perasa, dan pengisi opsional; tidak ditambahkan otomatis.</p>
    <div class="rx-preview" data-compound-preview>
        <strong class="small d-block mb-1">Periksa komposisi</strong>
        <div class="small" data-compound-preview-body>Isi bahan untuk melihat total.</div>
    </div>
    @if ($requireConfirm)
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="{{ $field('composition_confirmed') }}" id="{{ $sigPrefix }}-confirm" value="1" required>
            <label class="form-check-label" for="{{ $sigPrefix }}-confirm">Komposisi sudah diperiksa. Koreksi total tidak ditimpa otomatis.</label>
        </div>
    @endif
</div>
