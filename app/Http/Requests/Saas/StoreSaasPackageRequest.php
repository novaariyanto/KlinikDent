<?php

namespace App\Http\Requests\Saas;

use App\Enums\BillingInterval;
use App\Models\SaasPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreSaasPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SaasPackage::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash', 'unique:saas_packages,slug'],
            'price' => ['required', 'numeric', 'min:0'],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_branches' => ['nullable', 'integer', 'min:1'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : Str::slug((string) $this->input('name')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
