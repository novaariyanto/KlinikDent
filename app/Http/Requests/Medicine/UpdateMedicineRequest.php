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
            'kfa_code' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('medicines', 'kfa_code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($medicine->id),
            ],
            'strength_value' => ['nullable', 'numeric', 'min:0.0001', 'max:999999'],
            'strength_unit' => ['nullable', 'string', 'max:30', 'required_with:strength_value'],
            'category' => ['required', 'string', Rule::in(array_keys(Medicine::CATEGORIES))],
            'base_price' => ['required', 'numeric', 'min:0'],
            'margin_type' => ['required', Rule::in(array_keys(Medicine::MARGIN_TYPES))],
            'margin_value' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'margin_type' => $this->input('margin_type') ?: 'fixed',
            'margin_value' => $this->input('margin_value', 0),
            'kfa_code' => filled($this->input('kfa_code')) ? preg_replace('/\s+/', '', (string) $this->input('kfa_code')) : null,
            'strength_value' => filled($this->input('strength_value')) ? $this->input('strength_value') : null,
            'strength_unit' => filled($this->input('strength_unit')) ? trim((string) $this->input('strength_unit')) : null,
        ]);
    }
}
