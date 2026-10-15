<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSatuSehatSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('integration.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'fake' => ['sometimes', 'boolean'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'environment' => ['required', 'in:sandbox,production'],
            'organization_id' => ['nullable', 'string', 'max:64'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
        ];
    }
}
