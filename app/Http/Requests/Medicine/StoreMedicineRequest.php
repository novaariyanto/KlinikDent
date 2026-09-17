<?php

namespace App\Http\Requests\Medicine;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicineRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Medicine::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('medicines', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
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
