<div class="rx-card" data-template-row data-template-locked>
    <div class="small mb-0">{{ $label }} · {{ $quantity }} bungkus</div>
    <input type="hidden" name="items[{{ $index }}][kind]" value="racikan">
    <input type="hidden" name="items[{{ $index }}][medication_type]" value="{{ $medicationType }}">
    <input type="hidden" name="items[{{ $index }}][dosage]" value="{{ $dosage }}">
    <input type="hidden" name="items[{{ $index }}][frequency]" value="{{ $frequency }}">
    <input type="hidden" name="items[{{ $index }}][duration]" value="{{ $duration }}">
    <input type="hidden" name="items[{{ $index }}][course]" value="{{ $course }}">
    <input type="hidden" name="items[{{ $index }}][quantity]" value="{{ $quantity }}">
    @foreach ($ingredients as $ingredientIndex => $ingredient)
        <input type="hidden" name="items[{{ $index }}][ingredients][{{ $ingredientIndex }}][name]" value="{{ $ingredient['name'] ?? '' }}">
        <input type="hidden" name="items[{{ $index }}][ingredients][{{ $ingredientIndex }}][medicine_id]" value="{{ $ingredient['medicine_id'] ?? '' }}">
        <input type="hidden" name="items[{{ $index }}][ingredients][{{ $ingredientIndex }}][unit]" value="{{ $ingredient['unit'] ?? '' }}">
        <input type="hidden" name="items[{{ $index }}][ingredients][{{ $ingredientIndex }}][role]" value="{{ $ingredient['role'] ?? 'active' }}">
        <input type="hidden" name="items[{{ $index }}][ingredients][{{ $ingredientIndex }}][additive_kind]" value="{{ $ingredient['additive_kind'] ?? '' }}">
        <input type="hidden" name="items[{{ $index }}][ingredients][{{ $ingredientIndex }}][quantity]" value="{{ $ingredient['quantity'] ?? 1 }}">
        <input type="hidden" name="items[{{ $index }}][ingredients][{{ $ingredientIndex }}][per_dose_quantity]" value="{{ $ingredient['per_dose_quantity'] ?? '' }}">
        <input type="hidden" name="items[{{ $index }}][ingredients][{{ $ingredientIndex }}][strength_value]" value="{{ $ingredient['strength_value'] ?? '' }}">
        <input type="hidden" name="items[{{ $index }}][ingredients][{{ $ingredientIndex }}][strength_unit]" value="{{ $ingredient['strength_unit'] ?? '' }}">
    @endforeach
</div>
