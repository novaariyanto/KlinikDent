<?php

namespace App\Http\Requests\Medicine;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMedicineRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Medicine $medicine */
        $medicine = $this->route('medicine');

        return $this->user()?->can('update', $medicine) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Medicine $medicine */
        $medicine = $this->route('medicine');
        $tenantId = $this->actorTenantId() ?? $medicine->tenant_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('medicines', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($medicine->id),
            ],
            'unit' => ['required', 'string', Rule::in(array_keys(Medicine::UNITS))],
            'category' => ['required', 'string', Rule::in(array_keys(Medicine::CATEGORIES))],
            'base_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
