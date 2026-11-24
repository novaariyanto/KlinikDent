@php
    $options = match ($group) {
        'doses' => \App\Support\Clinical\PrescriptionSigCatalog::doses(),
        'frequencies' => \App\Support\Clinical\PrescriptionSigCatalog::frequencies(),
        'instructions' => \App\Support\Clinical\PrescriptionSigCatalog::instructions(),
        'courses' => \App\Support\Clinical\PrescriptionSigCatalog::courses(),
        'forms' => \App\Support\Clinical\PrescriptionSigCatalog::compoundForms(),
        'units' => \App\Support\Clinical\PrescriptionSigCatalog::units(),
        'amounts' => \App\Support\Clinical\PrescriptionSigCatalog::ingredientAmounts(),
        default => [],
    };
    $target = $target ?? null;
    $name = $name ?? null;
    $mode = $mode ?? null;
    $compact = $compact ?? false;
@endphp
@if ($compact)
    <div
        class="rx-sig-tip"
        @if ($name) data-sig-name="{{ $name }}" @endif
        @if ($mode) data-sig-mode="{{ $mode }}" @endif
    >
        <button
            type="button"
            class="btn btn-sm btn-light border rx-sig-tip__toggle"
            data-rx-sig-popover
            title="Bantuan input"
            aria-label="Bantuan input"
        >
            <i class="bx bx-help-circle"></i>
        </button>
        <div class="rx-sig-tip__template" hidden>
            <div class="rx-sig rx-sig--tip">
                @foreach ($options as $option)
                    <button
                        type="button"
                        class="btn btn-sm btn-light border"
                        data-sig-fill="{{ $option['text'] }}"
                        data-sig-effect="{{ $option['effect'] ?? 'text' }}"
                        @if ($name) data-sig-name="{{ $name }}" @endif
                        @if ($mode) data-sig-mode="{{ $mode }}" @endif
                        @if (isset($option['times'])) data-sig-times="{{ $option['times'] }}" @endif
                        @if (isset($option['dose'])) data-sig-dose="{{ $option['dose'] }}" @endif
                        @if (isset($option['days'])) data-sig-days="{{ $option['days'] }}" @endif
                        title="{{ $option['hint'] }}"
                        aria-label="{{ $option['hint'] }}"
                    >{{ $option['label'] }}</button>
                @endforeach
            </div>
        </div>
    </div>
@else
    <div
        class="rx-sig"
        @if ($target) data-sig-for="{{ $target }}" @endif
        @if ($name) data-sig-name="{{ $name }}" @endif
        @if ($mode) data-sig-mode="{{ $mode }}" @endif
    >
        @foreach ($options as $option)
            <button
                type="button"
                class="btn btn-sm btn-light border"
                data-sig-fill="{{ $option['text'] }}"
                data-sig-effect="{{ $option['effect'] ?? 'text' }}"
                @if (isset($option['times'])) data-sig-times="{{ $option['times'] }}" @endif
                @if (isset($option['dose'])) data-sig-dose="{{ $option['dose'] }}" @endif
                @if (isset($option['days'])) data-sig-days="{{ $option['days'] }}" @endif
                title="{{ $option['hint'] }}"
                aria-label="{{ $option['hint'] }}"
            >{{ $option['label'] }}</button>
        @endforeach
    </div>
@endif
