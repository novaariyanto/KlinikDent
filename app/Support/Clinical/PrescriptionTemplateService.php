<?php

namespace App\Support\Clinical;

use App\Enums\MedicationTypeCode;
use App\Enums\PrescriptionItemKind;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionTemplate;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrescriptionTemplateService
{
    public function __construct(protected PrescriptionItemWriter $items) {}

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function create(User $user, string $name, array $items): PrescriptionTemplate
    {
        return DB::transaction(function () use ($user, $name, $items) {
            $template = PrescriptionTemplate::query()->create([
                'tenant_id' => $user->tenant_id,
                'created_by' => $user->id,
                'name' => $name,
            ]);

            $this->writeItems($template, $items);

            return $template->load('items.ingredients', 'items.medicine');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function update(PrescriptionTemplate $template, string $name, array $items): PrescriptionTemplate
    {
        return DB::transaction(function () use ($template, $name, $items) {
            $template->update(['name' => $name]);
            $template->items()->delete();
            $this->writeItems($template, $items);

            return $template->load('items.ingredients', 'items.medicine');
        });
    }

    public function createFromPrescription(User $user, Prescription $prescription, string $name): PrescriptionTemplate
    {
        $prescription->loadMissing('items.ingredients');

        $items = $prescription->items->map(function ($item) {
            return [
                'kind' => $item->kind?->value ?? PrescriptionItemKind::Ready->value,
                'medication_type' => $item->medication_type?->value,
                'medicine_id' => $item->medicine_id,
                'dosage' => $item->dosage,
                'frequency' => $item->frequency,
                'duration' => $item->duration,
                'course' => $item->course,
                'quantity' => $item->quantity,
                'ingredients' => $item->ingredients->map(fn ($ingredient) => [
                    'medicine_id' => $ingredient->medicine_id,
                    'name' => $ingredient->name,
                    'unit' => $ingredient->unit,
                    'role' => $ingredient->role ?: 'active',
                    'additive_kind' => $ingredient->additive_kind,
                    'quantity' => $ingredient->quantity,
                    'per_dose_quantity' => $ingredient->per_dose_quantity,
                    'strength_value' => $ingredient->strength_value,
                    'strength_unit' => $ingredient->strength_unit,
                ])->all(),
            ];
        })->all();

        if ($items === []) {
            throw ValidationException::withMessages([
                'name' => 'Resep draft belum memiliki item.',
            ]);
        }

        return $this->create($user, $name, $items);
    }

    /**
     * @return array{prescription: Prescription, skipped: list<string>}
     */
    public function apply(Visit $visit, User $user, PrescriptionTemplate $template): array
    {
        $template->loadMissing('items.medicine', 'items.ingredients');

        $result = DB::transaction(function () use ($visit, $user, $template) {
            $prescription = app(ClinicalCareService::class)->draftPrescription($visit, $user);
            $applied = 0;
            $skipped = [];

            foreach ($template->items as $item) {
                if (! $item->isCompound()) {
                    $medicine = $item->medicine;
                    if (! $medicine || ! $medicine->is_active) {
                        $skipped[] = $item->label();

                        continue;
                    }
                }

                $this->items->create($prescription, [
                    'kind' => $item->kind?->value ?? PrescriptionItemKind::Ready->value,
                    'medication_type' => $item->medication_type?->value
                        ?? ($item->isCompound() ? MedicationTypeCode::EqualParts->value : MedicationTypeCode::NonCompound->value),
                    'medicine_id' => $item->isCompound() ? null : $item->medicine_id,
                    'dosage' => $item->dosage,
                    'frequency' => $item->frequency,
                    'duration' => $item->duration,
                    'course' => $item->course,
                    'quantity' => $item->quantity,
                    'ingredients' => $item->ingredients->map(fn ($ingredient) => [
                        'medicine_id' => $ingredient->medicine_id,
                        'name' => $ingredient->name,
                        'unit' => $ingredient->unit,
                        'role' => $ingredient->role,
                        'additive_kind' => $ingredient->additive_kind,
                        'quantity' => $ingredient->quantity,
                        'per_dose_quantity' => $ingredient->per_dose_quantity,
                        'strength_value' => $ingredient->strength_value,
                        'strength_unit' => $ingredient->strength_unit,
                    ])->all(),
                ]);
                $applied++;
            }

            if ($applied === 0) {
                throw ValidationException::withMessages([
                    'template' => 'Tidak ada item yang dapat diterapkan.'
                        .($skipped !== [] ? ' Obat tidak aktif: '.implode(', ', $skipped).'.' : ''),
                ]);
            }

            return ['prescription' => $prescription, 'skipped' => $skipped];
        });

        activity_log('created', $template, ['visit_id' => $visit->id], 'Template '.$template->name.' diterapkan.', 'prescriptions');

        return $result;
    }

    public function delete(PrescriptionTemplate $template): void
    {
        activity_log('deleted', $template, ['name' => $template->name], 'Template resep '.$template->name.' dihapus.', 'prescriptions');
        $template->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function writeItems(PrescriptionTemplate $template, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $kind = ($item['kind'] ?? PrescriptionItemKind::Ready->value) === PrescriptionItemKind::Compound->value
                ? PrescriptionItemKind::Compound
                : PrescriptionItemKind::Ready;
            $medicineId = $kind === PrescriptionItemKind::Compound
                ? null
                : (filled($item['medicine_id'] ?? null) ? (int) $item['medicine_id'] : null);

            if ($kind === PrescriptionItemKind::Ready && $medicineId) {
                Medicine::query()->whereKey($medicineId)->firstOrFail();
            }

            $row = $template->items()->create([
                'sort' => $index,
                'kind' => $kind,
                'medication_type' => $item['medication_type']
                    ?? ($kind === PrescriptionItemKind::Compound ? MedicationTypeCode::EqualParts->value : MedicationTypeCode::NonCompound->value),
                'medicine_id' => $medicineId,
                'dosage' => $item['dosage'] ?? null,
                'frequency' => $item['frequency'] ?? null,
                'duration' => $item['duration'] ?? null,
                'course' => $item['course'] ?? null,
                'unit' => $item['unit'] ?? null,
                'quantity' => (int) ($item['quantity'] ?? 1),
            ]);

            foreach ($item['ingredients'] ?? [] as $ingredient) {
                if (! is_array($ingredient) || blank($ingredient['name'] ?? null)) {
                    continue;
                }

                $row->ingredients()->create([
                    'medicine_id' => filled($ingredient['medicine_id'] ?? null) ? $ingredient['medicine_id'] : null,
                    'name' => $ingredient['name'],
                    'unit' => $ingredient['unit'] ?? null,
                    'role' => ($ingredient['role'] ?? 'active') === 'additive' ? 'additive' : 'active',
                    'additive_kind' => $ingredient['additive_kind'] ?? null,
                    'quantity' => (int) ($ingredient['quantity'] ?? 1),
                    'per_dose_quantity' => filled($ingredient['per_dose_quantity'] ?? null) ? (int) $ingredient['per_dose_quantity'] : null,
                    'strength_value' => $ingredient['strength_value'] ?? null,
                    'strength_unit' => $ingredient['strength_unit'] ?? null,
                ]);
            }
        }
    }
}
