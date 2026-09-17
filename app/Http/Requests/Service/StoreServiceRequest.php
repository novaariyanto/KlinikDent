<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Service::class) ?? false;
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
                Rule::unique('services', 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'category' => ['required', 'string', Rule::in(array_keys(Service::CATEGORIES))],
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
