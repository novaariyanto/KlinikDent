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
@endphp
<div class="rx-sig" data-sig-for="{{ $target }}">
    @foreach ($options as $option)
        <button type="button" class="btn btn-sm btn-light border" data-sig-fill="{{ $option['text'] }}" title="{{ $option['hint'] }}" aria-label="{{ $option['hint'] }}">{{ $option['label'] }}</button>
    @endforeach
</div>
