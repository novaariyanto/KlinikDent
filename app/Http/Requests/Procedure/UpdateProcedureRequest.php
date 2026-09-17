<?php

namespace App\Http\Requests\Procedure;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Procedure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcedureRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Procedure $procedure */
        $procedure = $this->route('procedure');

        return $this->user()?->can('update', $procedure) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Procedure $procedure */
        $procedure = $this->route('procedure');
        $tenantId = $this->actorTenantId() ?? $procedure->tenant_id;

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
                Rule::unique('procedures', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($procedure->id),
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
