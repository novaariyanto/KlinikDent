<?php

namespace App\Http\Requests\Doctor;

use App\Enums\RoleName;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreDoctorRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Doctor::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $existing = $this->input('source') === 'existing';
        $tenantId = $this->actorTenantId();

        return [
            'source' => ['required', 'in:new,existing'],
            'user_id' => [
                Rule::requiredIf($existing),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($tenantId) {
                    if ($tenantId) {
                        $query->where('tenant_id', $tenantId);
                    }
                }),
            ],
            'name' => [Rule::requiredIf(! $existing), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf(! $existing), 'nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => [Rule::requiredIf(! $existing), 'nullable', 'confirmed', Password::defaults()],
            'role' => [Rule::requiredIf(! $existing), 'nullable', Rule::in(RoleName::medicalStaffValues())],
            'branch_ids' => [Rule::requiredIf(! $existing), 'nullable', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'sip' => ['nullable', 'string', 'max:80'],
            'str' => ['nullable', 'string', 'max:80'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('source') !== 'existing' || ! $this->filled('user_id')) {
                return;
            }

            $user = User::withoutGlobalScopes()->with('doctorProfile')->find((int) $this->input('user_id'));

            if (! $user) {
                return;
            }

            if (! $user->isMedicalStaff()) {
                $validator->errors()->add('user_id', 'Pengguna harus berperan sebagai tenaga medis.');
            }

            if ($user->doctorProfile) {
                $validator->errors()->add('user_id', 'Pengguna ini sudah punya profil tenaga medis.');
            }
        });

        $validator->after(function (Validator $validator) {
            $tenantId = $this->actorTenantId();
            $branchIds = array_values(array_unique(array_map('intval', array_filter((array) $this->input('branch_ids', [])))));

            if ($this->input('source') === 'existing' || $branchIds === [] || ! $tenantId) {
                return;
            }

            $valid = \App\Models\Branch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $branchIds)
                ->count();

            if ($valid !== count($branchIds)) {
                $validator->errors()->add('branch_ids', 'Cabang tidak termasuk dalam klinik ini.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source' => $this->input('source', 'new'),
            'is_active' => $this->boolean('is_active'),
            'user_id' => $this->filled('user_id') ? $this->input('user_id') : null,
            'branch_ids' => array_values(array_filter((array) $this->input('branch_ids', []))),
        ]);
    }
}
