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

        $medicineId = $data['medicine_id'] ?? null;
        $medicine = $medicineId ? Medicine::query()->find($medicineId) : null;
        $unitPrice = array_key_exists('unit_price', $data) && $data['unit_price'] !== null && $data['unit_price'] !== ''
            ? $data['unit_price']
            : $medicine?->base_price;

        $item = $prescription->items()->create([
            'kind' => $data['kind'] ?? PrescriptionItemKind::Ready->value,
            'medication_type' => $data['medication_type'] ?? MedicationTypeCode::NonCompound->value,
            'name' => $data['name'] ?? null,
            'jenis_racikan' => $data['jenis_racikan'] ?? null,
            'medicine_id' => $medicineId,
            'dosage' => $data['dosage'] ?? null,
            'frequency' => $data['frequency'] ?? null,
            'duration' => $data['duration'] ?? null,
            'course' => $data['course'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'quantity_manual' => filter_var($data['quantity_manual'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'signa_p' => $data['signa_p'] ?? null,
            'signa_s' => $data['signa_s'] ?? null,
            'signa_sr' => $data['signa_sr'] ?? null,
            'signa_m' => $data['signa_m'] ?? null,
            'waktu' => $data['waktu'] ?? null,
            'jho' => $data['jho'] ?? null,
            'unit' => $data['unit'] ?? $medicine?->unit,
            'notes' => $data['notes'] ?? null,
            'unit_price' => $unitPrice,
        ]);

        $this->addIngredients($item, $ingredients);
        $this->syncCompoundPrice($item);

        return $item;
    }

    /**
     * @param  list<array<string, mixed>>  $ingredients
     */
    public function scaleIngredients(PrescriptionItem $item, int $previousPacks): void
    {
        if ($item->quantity_manual) {
            return;
        }

        $packs = max(1, (int) $item->quantity);
        $previousPacks = max(1, $previousPacks);

        foreach ($item->ingredients as $row) {
            if ($row->isAdditive()) {
                continue;
            }

            if ($row->dose_amount && (float) $row->strength_value > 0) {
                $row->update([
                    'quantity' => max(1, (int) ceil((float) $row->dose_amount * $packs / (float) $row->strength_value)),
                ]);

                continue;
            }

            if (! $row->per_dose_quantity) {
                continue;
            }

            $perDose = (int) $row->per_dose_quantity;
            $inSync = (int) $row->quantity === ($perDose * $previousPacks) || (int) $row->quantity === $perDose;
            if (! $inSync) {
                continue;
            }

            $row->update(['quantity' => $perDose * $packs]);
        }

        $this->syncCompoundPrice($item);
    }

    public function syncCompoundPrice(PrescriptionItem $item): void
    {
        if (! $item->isCompound()) {
            return;
        }

        $item->unsetRelation('ingredients');
        $item->load('ingredients.medicine');
        $item->update(['unit_price' => $item->compoundCharge()]);
    }

    /**
     * @param  list<array<string, mixed>>  $ingredients
     */
    public function replaceIngredients(PrescriptionItem $item, array $ingredients): void
    {
        $item->ingredients()->delete();
        $this->addIngredients($item, $ingredients);
        $this->syncCompoundPrice($item);
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
            $strength = filled($ingredient['strength_value'] ?? null) ? $ingredient['strength_value'] : $stockMedicine?->strength_value;
            $doseAmount = $role === 'additive' ? null : (filled($ingredient['dose_amount'] ?? null) ? (float) $ingredient['dose_amount'] : null);
            $quantity = (int) ($ingredient['quantity'] ?? 1);
            $perDose = filled($ingredient['per_dose_quantity'] ?? null)
                ? (int) $ingredient['per_dose_quantity']
                : null;

            if ($role !== 'additive' && $doseAmount !== null && $doseAmount > 0 && (float) $strength > 0) {
                $quantity = max(1, (int) ceil($doseAmount * max(1, (int) $item->quantity) / (float) $strength));
                $perDose = null;
            }

            $item->ingredients()->create([
                'medicine_id' => $medicineId,
                'name' => $ingredient['name'],
                'unit' => $ingredient['unit'] ?? null,
                'role' => $role,
                'additive_kind' => $role === 'additive' ? ($ingredient['additive_kind'] ?? null) : null,
                'quantity' => $quantity,
                'dose_amount' => $doseAmount,
                'per_dose_quantity' => $perDose,
                'strength_value' => $strength,
                'strength_unit' => $ingredient['strength_unit'] ?? $stockMedicine?->strength_unit,
            ]);
        }
    }
}
