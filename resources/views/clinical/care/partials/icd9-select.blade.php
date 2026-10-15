@props([
    'id',
    'selected' => null,
    'showError' => false,
])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">ICD-9-CM</label>
    <select
        name="icd9_code"
        id="{{ $id }}"
        class="form-select @if ($showError && $errors->has('icd9_code')) is-invalid @endif"
        data-icd9-select
    >
        <option value=""></option>
        @foreach (\App\Support\Clinical\Icd9Catalog::options() as $code => $label)
            <option value="{{ $code }}" @selected((string) $selected === (string) $code)>{{ $label }}</option>
        @endforeach
    </select>
    @if ($showError)
        @error('icd9_code')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    @endif
</div>
