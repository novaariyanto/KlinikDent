<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Service $service */
        $service = $this->route('service');

        return $this->user()?->can('update', $service) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Service $service */
        $service = $this->route('service');
        $tenantId = $this->actorTenantId() ?? $service->tenant_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($service->id),
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
