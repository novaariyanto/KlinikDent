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
                    @php
                        $signaP = null;
                        $signaS = null;
                        if ($mode === 'signa') {
                            if (preg_match('/^(\d+)\s*[×xX]\s*(\d+)$/u', $option['label'], $m)) {
                                $signaP = $m[1];
                                $signaS = $m[2];
                            } elseif (preg_match('/tiap\s+(\d+)\s*jam/iu', $option['label'], $m)) {
                                $hours = (int) $m[1];
                                if ($hours > 0 && $hours <= 24) {
                                    $signaP = (string) max(1, (int) round(24 / $hours));
                                    $signaS = '1';
                                }
                            } elseif (preg_match('/^(PRN|SOS)$/iu', $option['label'])) {
                                $signaP = '1';
                                $signaS = '1';
                            }
                        }
                    @endphp
                    <button
                        type="button"
                        class="btn btn-sm btn-light border"
                        data-sig-fill="{{ $option['text'] }}"
                        @if ($name) data-sig-name="{{ $name }}" @endif
                        @if ($mode) data-sig-mode="{{ $mode }}" @endif
                        @if ($signaP !== null) data-sig-p="{{ $signaP }}" data-sig-s="{{ $signaS }}" @endif
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
            @php
                $signaP = null;
                $signaS = null;
                if ($mode === 'signa') {
                    if (preg_match('/^(\d+)\s*[×xX]\s*(\d+)$/u', $option['label'], $m)) {
                        $signaP = $m[1];
                        $signaS = $m[2];
                    } elseif (preg_match('/tiap\s+(\d+)\s*jam/iu', $option['label'], $m)) {
                        $hours = (int) $m[1];
                        if ($hours > 0 && $hours <= 24) {
                            $signaP = (string) max(1, (int) round(24 / $hours));
                            $signaS = '1';
                        }
                    } elseif (preg_match('/^(PRN|SOS)$/iu', $option['label'])) {
                        $signaP = '1';
                        $signaS = '1';
                    }
                }
            @endphp
            <button
                type="button"
                class="btn btn-sm btn-light border"
                data-sig-fill="{{ $option['text'] }}"
                @if ($signaP !== null) data-sig-p="{{ $signaP }}" data-sig-s="{{ $signaS }}" @endif
                title="{{ $option['hint'] }}"
                aria-label="{{ $option['hint'] }}"
            >{{ $option['label'] }}</button>
        @endforeach
    </div>
@endif
