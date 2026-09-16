@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'required' => false,
    'placeholder' => 'Select...',
])

@php
    $id = $attributes->get('id', $name);
    $current = old($name, $selected);
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

    <select
        name="{{ $name }}"
        id="{{ $id }}"
        {{ $attributes->class(['form-select', 'is-invalid' => $invalid])->merge(['required' => $required ?: null]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            @if (is_array($optionLabel))
                <option value="{{ $optionLabel['value'] }}" @selected((string) $current === (string) $optionLabel['value'])>
                    {{ $optionLabel['label'] }}
                </option>
            @else
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>
                    {{ $optionLabel }}
                </option>
            @endif
        @endforeach
    </select>

    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
