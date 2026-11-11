<?php

namespace App\Support\Clinical;

use App\Enums\MedicationTypeCode;
use App\Enums\PrescriptionItemKind;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;

class PrescriptionItemWriter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Prescription $prescription, array $data): PrescriptionItem
    {
        $ingredients = is_array($data['ingredients'] ?? null) ? $data['ingredients'] : [];

        $item = $prescription->items()->create([
            'kind' => $data['kind'] ?? PrescriptionItemKind::Ready->value,
            'medication_type' => $data['medication_type'] ?? MedicationTypeCode::NonCompound->value,
            'medicine_id' => $data['medicine_id'] ?? null,
            'dosage' => $data['dosage'] ?? null,
            'frequency' => $data['frequency'] ?? null,
            'duration' => $data['duration'] ?? null,
            'course' => $data['course'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
        ]);

        $this->addIngredients($item, $ingredients);

        return $item;
    }

    /**
     * @param  list<array<string, mixed>>  $ingredients
     */
    public function replaceIngredients(PrescriptionItem $item, array $ingredients): void
    {
        $item->ingredients()->delete();
        $this->addIngredients($item, $ingredients);
    }

    /**
     * @param  list<array<string, mixed>>  $ingredients
     */
    public function addIngredients(PrescriptionItem $item, array $ingredients): void
    {
        foreach ($ingredients as $ingredient) {
            if (! is_array($ingredient) || blank($ingredient['name'] ?? null)) {
                continue;
            }

            $medicineId = filled($ingredient['medicine_id'] ?? null) ? (int) $ingredient['medicine_id'] : null;
            $stockMedicine = $medicineId ? Medicine::query()->find($medicineId) : null;
            $role = ($ingredient['role'] ?? 'active') === 'additive' ? 'additive' : 'active';

            $item->ingredients()->create([
                'medicine_id' => $medicineId,
                'name' => $ingredient['name'],
                'unit' => $ingredient['unit'] ?? null,
                'role' => $role,
                'additive_kind' => $role === 'additive' ? ($ingredient['additive_kind'] ?? null) : null,
                'quantity' => (int) ($ingredient['quantity'] ?? 1),
                'per_dose_quantity' => filled($ingredient['per_dose_quantity'] ?? null)
                    ? (int) $ingredient['per_dose_quantity']
                    : null,
                'strength_value' => $ingredient['strength_value'] ?? $stockMedicine?->strength_value,
                'strength_unit' => $ingredient['strength_unit'] ?? $stockMedicine?->strength_unit,
            ]);
        }
    }
}
