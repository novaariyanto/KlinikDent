<?php

namespace App\Http\Requests\Tenant;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->user()?->can('update', $tenant) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return [
            'name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('tenants', 'subdomain')->ignore($tenant->id),
            ],
            'status' => ['required', Rule::enum(TenantStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('subdomain')) {
            $this->merge([
                'subdomain' => strtolower((string) $this->input('subdomain')),
            ]);
        }
    }
}
