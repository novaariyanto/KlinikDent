<?php

namespace App\Http\Requests\Clinical;

use App\Enums\MedicationTypeCode;
use App\Enums\VisitStatus;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePrescriptionItemRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');
        /** @var PrescriptionItem $item */
        $item = $this->route('prescriptionItem');
        $prescription = $item?->prescription;

        return $this->user()?->can('prescription.update')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled
            && $prescription
            && (int) $prescription->visit_id === (int) $visit->id
            && $prescription->isDraft();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PrescriptionItem|null $item */
        $item = $this->route('prescriptionItem');
        $compound = $item?->isCompound() ?? false;
        $interactive = $this->input('_form') === 'prescription-update';
        $tenantId = $this->actorTenantId();

        return [
            'dosage' => [Rule::requiredIf($interactive), 'nullable', 'string', 'max:160'],
            'frequency' => [Rule::requiredIf($interactive), 'nullable', 'string', 'max:160'],
            'duration' => [Rule::requiredIf($interactive), 'nullable', 'string', 'max:160'],
            'course' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'medication_type' => [Rule::excludeIf(! $compound), 'required', Rule::enum(MedicationTypeCode::class)],
            'composition_confirmed' => [Rule::excludeIf(! $compound || ! $interactive), 'accepted'],
            'ingredients' => [Rule::excludeIf(! $compound), 'required', 'array', 'min:1'],
            'ingredients.*.name' => ['required_with:ingredients', 'string', 'max:120'],
            'ingredients.*.medicine_id' => [
                'nullable',
                'integer',
                Rule::exists('medicines', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'ingredients.*.quantity' => ['required_with:ingredients', 'integer', 'min:1', 'max:10000'],
            'ingredients.*.per_dose_quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:30'],
            'ingredients.*.role' => ['nullable', Rule::in(['active', 'additive'])],
            'ingredients.*.additive_kind' => ['nullable', Rule::in(['sakarin', 'flavor', 'filler', 'other'])],
            'ingredients.*.strength_value' => ['nullable', 'numeric', 'min:0.0001', 'max:999999'],
            'ingredients.*.strength_unit' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dosage.required' => 'Dosis atau bentuk sediaan wajib diisi.',
            'frequency.required' => 'Frekuensi wajib diisi.',
            'duration.required' => 'Aturan pakai wajib diisi.',
            'composition_confirmed.accepted' => 'Periksa komposisi racikan sebelum menyimpan.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var PrescriptionItem|null $item */
            $item = $this->route('prescriptionItem');
            if (! $item?->isCompound()) {
                return;
            }

            $type = $this->input('medication_type');
            if ($type === MedicationTypeCode::NonCompound->value) {
                $validator->errors()->add('medication_type', 'Racikan harus bertipe EP atau SD.');
            }

            $active = collect($this->input('ingredients', []))
                ->filter(fn ($row) => is_array($row) && ($row['role'] ?? 'active') !== 'additive' && filled($row['name'] ?? null));

            if ($active->isEmpty()) {
                $validator->errors()->add('ingredients', 'Racikan membutuhkan minimal satu bahan obat. Bahan tambahan saja tidak cukup.');
            }

            if ($type === MedicationTypeCode::GiveSuchDoses->value) {
                foreach ($this->input('ingredients', []) as $index => $row) {
                    if (! is_array($row) || ($row['role'] ?? 'active') === 'additive') {
                        continue;
                    }
                    if (! filled($row['strength_value'] ?? null) || ! filled($row['strength_unit'] ?? null)) {
                        $validator->errors()->add('ingredients.'.$index.'.strength_value', 'Kekuatan bahan wajib diisi untuk racikan d.t.d (SD).');
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        /** @var PrescriptionItem|null $item */
        $item = $this->route('prescriptionItem');
        if (! $item?->isCompound()) {
            $this->merge([
                'course' => filled($this->input('course')) ? trim((string) $this->input('course')) : null,
            ]);

            return;
        }

        $ingredients = collect($this->input('ingredients', []))
            ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))
            ->map(function (array $row) {
                $kind = filled($row['additive_kind'] ?? null) ? (string) $row['additive_kind'] : null;
                $role = (($row['role'] ?? '') === 'additive' || $kind) ? 'additive' : 'active';
                $row['medicine_id'] = filled($row['medicine_id'] ?? null) ? $row['medicine_id'] : null;
                $row['strength_value'] = filled($row['strength_value'] ?? null) ? $row['strength_value'] : null;
                $row['strength_unit'] = filled($row['strength_unit'] ?? null) ? trim((string) $row['strength_unit']) : null;
                $row['per_dose_quantity'] = filled($row['per_dose_quantity'] ?? null) ? $row['per_dose_quantity'] : null;
                $row['role'] = $role;
                $row['additive_kind'] = $role === 'additive' ? $kind : null;

                return $row;
            })
            ->values()
            ->all();

        $this->merge([
            'ingredients' => $ingredients,
            'course' => filled($this->input('course')) ? trim((string) $this->input('course')) : null,
        ]);
    }
}
