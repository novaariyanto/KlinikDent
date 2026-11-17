@php
    $formKey = $formKey ?? 'create';
    $addon = $addon ?? null;
    $field = fn (string $name, mixed $default = null) => old($name, $addon?->{$name} ?? $default);
    $invalid = fn (string $name) => $errors->has($name);
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Kode</label>
        <input class="form-control {{ $invalid('code') ? 'is-invalid' : '' }}" name="code" value="{{ $field('code') }}" required {{ $formKey === 'edit' ? 'readonly' : '' }}>
        @if ($invalid('code'))
            <div class="invalid-feedback d-block">{{ $errors->first('code') }}</div>
        @endif
    </div>
    <div class="col-md-8">
        <label class="form-label">Nama</label>
        <input class="form-control {{ $invalid('name') ? 'is-invalid' : '' }}" name="name" value="{{ $field('name') }}" required>
        @if ($invalid('name'))
            <div class="invalid-feedback d-block">{{ $errors->first('name') }}</div>
        @endif
    </div>
    <div class="col-12">
        <label class="form-label">Deskripsi</label>
        <textarea class="form-control {{ $invalid('description') ? 'is-invalid' : '' }}" name="description" rows="3">{{ $field('description') }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label">Manfaat (satu baris per item)</label>
        @php
            $benefitsOld = old('benefits', $addon?->benefits);
            $benefitsText = is_array($benefitsOld) ? implode("\n", $benefitsOld) : (string) ($benefitsOld ?? '');
        @endphp
        <textarea class="form-control" name="benefits" rows="4">{{ $benefitsText }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label">Harga</label>
        <input class="form-control {{ $invalid('price') ? 'is-invalid' : '' }}" type="number" min="0" step="0.01" name="price" value="{{ $field('price', 0) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Interval</label>
        <select class="form-select" name="interval" required>
            @foreach ($intervalOptions as $value => $label)
                <option value="{{ $value }}" @selected((string) $field('interval', 'monthly') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Trial (hari)</label>
        <input class="form-control" type="number" min="0" name="trial_days" value="{{ $field('trial_days', 0) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Ketentuan</label>
        <textarea class="form-control" name="terms" rows="3">{{ $field('terms') }}</textarea>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $formKey }}-active" @checked(old('is_active', $addon?->is_active ?? true))>
            <label class="form-check-label" for="{{ $formKey }}-active">Aktif di marketplace</label>
        </div>
    </div>
</div>
