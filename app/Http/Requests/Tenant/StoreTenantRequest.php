<?php

namespace App\Http\Requests\Tenant;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tenant::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:tenants,subdomain'],
            'status' => ['required', Rule::enum(TenantStatus::class)],
            'package_id' => ['nullable', 'integer', 'exists:saas_packages,id'],
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
