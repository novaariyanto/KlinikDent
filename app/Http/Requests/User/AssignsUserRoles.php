<?php

namespace App\Http\Requests\User;

use App\Enums\RoleName;
use App\Models\Branch;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait AssignsUserRoles
{
    /**
     * @return array<string, mixed>
     */
    protected function roleRules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in($this->allowedRoles())],
            'tenant_id' => [
                Rule::requiredIf(fn () => $this->user()?->isPlatformAdmin() && ! $this->includesPlatformRole()),
                'nullable',
                'integer',
                'exists:tenants,id',
            ],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ];
    }

    /**
     * @return list<string>
     */
    public function selectedRoles(): array
    {
        return array_values(array_unique(array_map('strval', array_filter((array) $this->input('roles', [])))));
    }

    public function includesPlatformRole(): bool
    {
        return in_array(RoleName::SuperAdminSaas->value, $this->selectedRoles(), true);
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
        if ($this->includesPlatformRole()) {
            return null;
        }

        if ($this->user()?->isPlatformAdmin()) {
            return $this->input('tenant_id') ? (int) $this->input('tenant_id') : null;
        }

        return $this->user()?->tenant_id ? (int) $this->user()->tenant_id : null;
    }

    protected function prepareAssignedRoles(): void
    {
        if (! $this->user()?->isPlatformAdmin()) {
            $this->merge([
                'tenant_id' => $this->user()?->tenant_id,
            ]);
        }

        if ($this->includesPlatformRole()) {
            $this->merge([
                'tenant_id' => null,
                'branch_id' => null,
                'branch_ids' => [],
            ]);

            return;
        }

        $ids = array_values(array_filter((array) $this->input('branch_ids', [])));

        if ($ids === [] && $this->filled('branch_id')) {
            $ids = [$this->input('branch_id')];
        }

        $this->merge([
            'branch_ids' => $ids,
            'branch_id' => $ids[0] ?? $this->input('branch_id'),
        ]);
    }

    protected function validateAssignedRoles(Validator $validator): void
    {
        $roles = $this->selectedRoles();
        $saas = RoleName::SuperAdminSaas->value;

        if (in_array($saas, $roles, true)) {
            if (! $this->user()?->isPlatformAdmin()) {
                $validator->errors()->add('roles', 'Anda tidak dapat menugaskan role Super Admin SaaS.');
            } elseif (count($roles) > 1) {
                $validator->errors()->add('roles', 'Super Admin SaaS tidak dapat digabung dengan role lain.');
            }
        }

        $tenantId = $this->resolvedTenantId();
        $branchIds = array_values(array_unique(array_map('intval', array_filter((array) $this->input('branch_ids', [])))));

        if ($this->input('branch_id')) {
            $branchIds[] = (int) $this->input('branch_id');
            $branchIds = array_values(array_unique($branchIds));
        }

        if ($branchIds === [] || ! $tenantId) {
            return;
        }

        $valid = Branch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $branchIds)
            ->count();

        if ($valid !== count($branchIds)) {
            $validator->errors()->add('branch_ids', 'Cabang tidak termasuk dalam tenant yang dipilih.');
        }
    }
}
