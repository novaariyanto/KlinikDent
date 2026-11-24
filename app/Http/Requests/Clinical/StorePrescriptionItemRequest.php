<?php

namespace App\Http\Requests\Clinical;

use App\Enums\MedicationTypeCode;
use App\Enums\PrescriptionItemKind;
use App\Enums\VisitStatus;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Visit;
use App\Support\Clinical\PrescriptionQuantity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePrescriptionItemRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('prescription.create')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        $kind = $this->input('kind', PrescriptionItemKind::Ready->value);
        $compound = $kind === PrescriptionItemKind::Compound->value;
        $interactive = in_array($this->input('_form'), ['prescription-create', 'compound-create'], true)
            && $this->input('_form') !== 'rx-editor-create';

        return [
            'kind' => ['nullable', Rule::enum(PrescriptionItemKind::class)],
            'medication_type' => [
                Rule::requiredIf($compound),
                'nullable',
                Rule::enum(MedicationTypeCode::class),
            ],
            'medicine_id' => [
                Rule::requiredIf(! $compound),
                'nullable',
                'integer',
                Rule::exists('medicines', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)),
            ],
            'dosage' => [Rule::requiredIf($interactive), 'nullable', 'string', 'max:160'],
            'frequency' => [Rule::requiredIf($interactive), 'nullable', 'string', 'max:160'],
            'duration' => [Rule::requiredIf($interactive), 'nullable', 'string', 'max:160'],
            'course' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'quantity_manual' => ['nullable', 'boolean'],
            'signa_p' => ['nullable', 'string', 'max:20'],
            'signa_s' => ['nullable', 'string', 'max:20'],
            'signa_sr' => ['nullable', 'string', 'max:20'],
            'signa_m' => ['nullable', 'string', 'max:20'],
            'waktu' => ['nullable', 'string', 'max:100'],
            'jho' => ['nullable', 'integer', 'min:1', 'max:365'],
            'unit' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
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
            'ingredients.*.quantity_manual' => ['nullable', 'boolean'],
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
            $kind = $this->input('kind', PrescriptionItemKind::Ready->value);
            $type = $this->input('medication_type');

            if ($kind === PrescriptionItemKind::Ready->value && filled($type) && $type !== MedicationTypeCode::NonCompound->value) {
                $validator->errors()->add('medication_type', 'Obat jadi hanya boleh bertipe NC.');
            }

            if ($kind === PrescriptionItemKind::Compound->value && filled($type) && $type === MedicationTypeCode::NonCompound->value) {
                $validator->errors()->add('medication_type', 'Racikan harus bertipe EP atau SD.');
            }

            if ($message = PrescriptionQuantity::mismatchMessage($this->all())) {
                $validator->errors()->add('quantity', $message);
            }

            if ($kind === PrescriptionItemKind::Compound->value) {
                $active = collect($this->input('ingredients', []))
                    ->filter(fn ($row) => is_array($row) && ($row['role'] ?? 'active') !== 'additive' && filled($row['name'] ?? null));

                if ($active->isEmpty()) {
                    $validator->errors()->add('ingredients', 'Racikan membutuhkan minimal satu bahan obat. Bahan tambahan saja tidak cukup.');
                }

                if ($message = PrescriptionQuantity::ingredientMessage($this->all(), $this->input('ingredients', []))) {
                    $validator->errors()->add('ingredients', $message);
                }

                if ($this->input('medication_type') === MedicationTypeCode::GiveSuchDoses->value) {
                    foreach ($this->input('ingredients', []) as $index => $row) {
                        if (! is_array($row) || ($row['role'] ?? 'active') === 'additive') {
                            continue;
                        }
                        if (! filled($row['strength_value'] ?? null) || ! filled($row['strength_unit'] ?? null)) {
                            $validator->errors()->add('ingredients.'.$index.'.strength_value', 'Kekuatan bahan wajib diisi untuk racikan d.t.d (SD).');
                        }
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $kind = $this->input('kind') ?: PrescriptionItemKind::Ready->value;
        $compound = $kind === PrescriptionItemKind::Compound->value;

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

        $medicationType = $this->input('medication_type');
        if (! $compound) {
            $medicationType = MedicationTypeCode::NonCompound->value;
        } elseif (! filled($medicationType)) {
            $medicationType = MedicationTypeCode::EqualParts->value;
        }

        $nullable = ['signa_p', 'signa_s', 'signa_sr', 'signa_m', 'waktu', 'unit', 'notes', 'course'];
        $merge = [
            'kind' => $kind,
            'medication_type' => $medicationType,
            'ingredients' => $ingredients,
            'medicine_id' => $compound ? null : $this->input('medicine_id'),
            'jho' => filled($this->input('jho')) ? (int) $this->input('jho') : null,
            'unit_price' => filled($this->input('unit_price')) ? $this->input('unit_price') : null,
            'quantity_manual' => $this->boolean('quantity_manual'),
        ];
        foreach ($nullable as $field) {
            $merge[$field] = filled($this->input($field)) ? trim((string) $this->input($field)) : null;
        }
        $merge = array_merge($merge, $this->liquidUnitOverride());
        if ($merge['jho'] && (! filled($this->input('duration')) || trim((string) $this->input('duration')) === '-')) {
            $merge['duration'] = $merge['jho'].' hari';
        }
        if ($this->input('_form') === 'rx-editor-create') {
            $merge = array_merge($merge, PrescriptionQuantity::editorQuantity(array_merge($this->all(), $merge)));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, string>
     */
    protected function liquidUnitOverride(): array
    {
        $result = PrescriptionQuantity::assess($this->all());
        $manual = $this->boolean('quantity_manual');
        $quantity = filled($this->input('quantity')) ? (int) $this->input('quantity') : null;

        if ($result['liquid'] && $result['mode'] === 'calculated' && ! $manual && $quantity === $result['quantity']) {
            return ['unit' => 'ml'];
        }

        return [];
    }
}
