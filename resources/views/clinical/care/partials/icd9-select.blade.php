@props([
    'id',
    'selected' => null,
    'showError' => false,
])

@php
    $selectedCode = filled($selected) ? (string) $selected : null;
    $selectedLabel = $selectedCode ? \App\Support\Clinical\Icd9Catalog::label($selectedCode) : null;
@endphp

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">ICD-9-CM</label>
    <select
        name="icd9_code"
        id="{{ $id }}"
        class="form-select @if ($showError && $errors->has('icd9_code')) is-invalid @endif"
        data-icd9-select
        data-icd9-url="{{ route('care.procedure.icd9.search', $visit) }}"
    >
        <option value=""></option>
        @if ($selectedCode)
            <option value="{{ $selectedCode }}" selected>{{ $selectedLabel }}</option>
        @endif
    </select>
    @if ($showError)
        @error('icd9_code')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    @endif
</div>
