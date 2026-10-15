<?php

namespace App\Http\Requests\Branch;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');

        return $this->user()?->can('update', $branch) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => [
                Rule::requiredIf(fn () => (bool) $this->user()?->isPlatformAdmin()),
                'nullable',
                'integer',
                'exists:tenants,id',
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'altitude' => ['nullable', 'numeric', 'between:-500,9000'],
            'is_active' => ['required', 'boolean'],
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.*' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);

        foreach (['address', 'city', 'postal_code', 'phone', 'email', 'website', 'longitude', 'latitude', 'altitude'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if (! $this->user()?->isPlatformAdmin()) {
            $this->merge([
                'tenant_id' => $this->user()?->tenant_id,
            ]);
        }
    }
}
