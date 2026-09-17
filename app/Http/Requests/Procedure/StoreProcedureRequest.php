<?php

namespace App\Http\Requests\Procedure;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Procedure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcedureRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Procedure::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('procedures', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(Procedure::CATEGORIES))],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'code' => strtoupper((string) $this->input('code')),
            'service_id' => $this->filled('service_id') ? $this->input('service_id') : null,
        ]);
    }
}
