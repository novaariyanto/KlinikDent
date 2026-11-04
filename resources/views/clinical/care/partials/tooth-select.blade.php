@php
    $toothSelectId = $id ?? 'tooth_number';
    $toothSelectSelected = (string) ($selected ?? old('tooth_number', ''));
    $odoTeeth = ($teeth ?? collect())
        ->filter(fn ($tooth) => \App\Support\Clinical\ToothConditions::fromRecord($tooth) !== [])
        ->sortBy('tooth_number');
@endphp

<div class="mb-3">
    <label for="{{ $toothSelectId }}" class="form-label">Gigi <span class="text-muted fw-normal">(opsional)</span></label>
    <select name="tooth_number" id="{{ $toothSelectId }}" class="form-select" data-odo-tooth-select>
        <option value=""></option>
        @foreach ($odoTeeth as $tooth)
            @php
                $label = $tooth->tooth_number.' — '.\App\Support\Clinical\ToothConditions::summary(\App\Support\Clinical\ToothConditions::fromRecord($tooth));
            @endphp
            <option value="{{ $tooth->tooth_number }}" @selected($toothSelectSelected === (string) $tooth->tooth_number)>{{ $label }}</option>
        @endforeach
        @if ($toothSelectSelected !== '' && ! $odoTeeth->has($toothSelectSelected))
            <option value="{{ $toothSelectSelected }}" selected>{{ $toothSelectSelected }}</option>
        @endif
    </select>
</div>
