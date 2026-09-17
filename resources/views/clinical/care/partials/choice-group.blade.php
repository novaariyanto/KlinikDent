@props([
    'name',
    'label',
    'options' => [],
    'selected' => null,
    'reveal' => null,
    'revealWhen' => 'present',
])

<div class="exam-field" @if ($reveal) data-reveal-group="{{ $reveal }}" data-reveal-when="{{ $revealWhen }}" @endif>
    <div class="exam-field__label">{{ $label }}</div>
    <div class="exam-choices">
        @foreach ($options as $value => $optionLabel)
            <label class="exam-choice">
                <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked((string) old($name, $selected) === (string) $value)>
                <span>{{ $optionLabel }}</span>
            </label>
        @endforeach
    </div>
</div>
