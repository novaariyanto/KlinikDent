<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveStaffRequest extends FormRequest
{
    use AuthorizesOwnerOnboarding;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'staff' => ['nullable', 'array'],
            'staff.*.name' => ['nullable', 'string', 'max:255'],
            'staff.*.email' => ['nullable', 'email', 'max:255', 'distinct', 'unique:users,email'],
            'staff.*.password' => ['nullable', 'string', Password::defaults()],
            'staff.*.roles' => ['nullable', 'array'],
            'staff.*.roles.*' => ['string', Rule::in(RoleName::tenantValues())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $staff = collect($this->input('staff', []))->map(function ($row) {
            foreach (['name', 'email', 'password'] as $key) {
                if (array_key_exists($key, $row) && $row[$key] === '') {
                    $row[$key] = null;
                }
            }

            return $row;
        })->all();

        $this->merge(['staff' => $staff]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('staff', []) as $index => $row) {
                if (! filled($row['name'] ?? null)) {
                    continue;
                }

                if (! filled($row['email'] ?? null)) {
                    $validator->errors()->add("staff.$index.email", 'Email staf wajib diisi.');
                }

                if (! filled($row['password'] ?? null)) {
                    $validator->errors()->add("staff.$index.password", 'Password staf wajib diisi.');
                }

                $roles = array_values(array_filter((array) ($row['roles'] ?? [])));

                if ($roles === []) {
                    $validator->errors()->add("staff.$index.roles", 'Pilih minimal satu role.');
                }

                if (in_array(RoleName::SuperAdminSaas->value, $roles, true)) {
                    $validator->errors()->add("staff.$index.roles", 'Role Super Admin SaaS tidak diizinkan.');
                }
            }
        });
    }
}
