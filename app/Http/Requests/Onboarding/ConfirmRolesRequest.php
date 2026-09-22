<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmRolesRequest extends FormRequest
{
    use AuthorizesOwnerOnboarding;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', Rule::in(RoleName::tenantValues())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $roles = array_values(array_unique(array_map('strval', (array) $this->input('roles', []))));

        if (! in_array(RoleName::Owner->value, $roles, true)) {
            $roles[] = RoleName::Owner->value;
        }

        $this->merge(['roles' => $roles]);
    }
}
