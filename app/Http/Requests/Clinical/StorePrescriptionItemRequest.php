<?php

namespace App\Http\Requests\Clinical;

use App\Enums\PrescriptionItemKind;
use App\Enums\VisitStatus;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        return [
            'kind' => ['nullable', Rule::enum(PrescriptionItemKind::class)],
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $ingredients = collect($this->input('ingredients', []))
            ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))
            ->map(function (array $row) {
                $row['medicine_id'] = filled($row['medicine_id'] ?? null) ? $row['medicine_id'] : null;

                return $row;
            })
            ->values()
            ->all();

        $this->merge([
            'kind' => $this->input('kind') ?: \App\Enums\PrescriptionItemKind::Ready->value,
            'ingredients' => $ingredients,
            'medicine_id' => $this->input('kind') === \App\Enums\PrescriptionItemKind::Compound->value
                ? null
                : $this->input('medicine_id'),
        ]);
    }
}
