<?php

namespace App\Http\Requests\Clinical;

use App\Enums\MedicationTypeCode;
use App\Enums\PrescriptionItemKind;
use App\Enums\VisitStatus;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Visit;
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
        $dtd = $compound && $this->input('medication_type') === MedicationTypeCode::GiveSuchDoses->value;

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
            'dosage' => ['nullable', 'string', 'max:100'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'duration' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'ingredients' => [Rule::excludeIf(! $compound), 'required', 'array', 'min:1'],
            'ingredients.*.name' => ['required_with:ingredients', 'string', 'max:120'],
            'ingredients.*.medicine_id' => [
                'nullable',
                'integer',
                Rule::exists('medicines', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'ingredients.*.quantity' => ['required_with:ingredients', 'integer', 'min:1', 'max:1000'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:30'],
            'ingredients.*.strength_value' => [
                Rule::requiredIf($dtd),
                'nullable',
                'numeric',
                'min:0.0001',
                'max:999999',
            ],
            'ingredients.*.strength_unit' => [
                Rule::requiredIf($dtd),
                'nullable',
                'string',
                'max:30',
            ],
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
        });
    }

    protected function prepareForValidation(): void
    {
        $kind = $this->input('kind') ?: PrescriptionItemKind::Ready->value;
        $compound = $kind === PrescriptionItemKind::Compound->value;

        $ingredients = collect($this->input('ingredients', []))
            ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))
            ->map(function (array $row) {
                $row['medicine_id'] = filled($row['medicine_id'] ?? null) ? $row['medicine_id'] : null;
                $row['strength_value'] = filled($row['strength_value'] ?? null) ? $row['strength_value'] : null;
                $row['strength_unit'] = filled($row['strength_unit'] ?? null) ? trim((string) $row['strength_unit']) : null;

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

        $this->merge([
            'kind' => $kind,
            'medication_type' => $medicationType,
            'ingredients' => $ingredients,
            'medicine_id' => $compound ? null : $this->input('medicine_id'),
        ]);
    }
}
