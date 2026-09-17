<?php

namespace App\Http\Requests\User;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'role' => ['required', 'string', Rule::in($this->allowedRoles())],
            'tenant_id' => [
                Rule::requiredIf(fn () => $this->user()?->isPlatformAdmin() && $this->input('role') !== RoleName::SuperAdminSaas->value),
                'nullable',
                'integer',
                'exists:tenants,id',
            ],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('role') === RoleName::SuperAdminSaas->value && ! $this->user()?->isPlatformAdmin()) {
                $validator->errors()->add('role', 'You cannot assign the Super Admin SaaS role.');
            }

            $tenantId = $this->resolvedTenantId();
            $branchId = $this->input('branch_id');

            if ($branchId && $tenantId) {
                $branch = Branch::withoutGlobalScopes()->find($branchId);

                if (! $branch || (int) $branch->tenant_id !== (int) $tenantId) {
                    $validator->errors()->add('branch_id', 'Cabang tidak termasuk dalam tenant yang dipilih.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()?->isPlatformAdmin()) {
            $this->merge([
                'tenant_id' => $this->user()?->tenant_id,
            ]);
        }

        if ($this->input('role') === RoleName::SuperAdminSaas->value) {
            $this->merge([
                'tenant_id' => null,
                'branch_id' => null,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    protected function allowedRoles(): array
    {
        return $this->user()?->isPlatformAdmin()
            ? RoleName::values()
            : RoleName::tenantValues();
    }

    public function resolvedTenantId(): ?int
    {
        if ($this->input('role') === RoleName::SuperAdminSaas->value) {
            return null;
        }

        if ($this->user()?->isPlatformAdmin()) {
            return $this->input('tenant_id') ? (int) $this->input('tenant_id') : null;
        }

        return $this->user()?->tenant_id ? (int) $this->user()->tenant_id : null;
    }
}
