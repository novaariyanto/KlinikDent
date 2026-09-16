@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id', $name);
    $currentValue = $type === 'password' ? '' : old($name, $value);
    $invalid = $errors->has($name);
@endphp

<div class="mb-3">
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $id }}"
        value="{{ $currentValue }}"
        {{ $attributes->class(['form-control', 'is-invalid' => $invalid])->merge(['required' => $required ?: null]) }}
    >

    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
