<?php

namespace App\Http\Requests\Saas;

use App\Enums\BillingInterval;
use App\Models\SaasPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateSaasPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SaasPackage $package */
        $package = $this->route('saasPackage');

        return $this->user()?->can('update', $package) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SaasPackage $package */
        $package = $this->route('saasPackage');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('saas_packages', 'slug')->ignore($package->id)],
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
        /** @var SaasPackage $package */
        $package = $this->route('saasPackage');

        $this->merge([
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : $package->slug,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : $package->is_active,
            'max_branches' => $this->exists('max_branches')
                ? ($this->filled('max_branches') ? $this->integer('max_branches') : null)
                : $package->max_branches,
            'max_users' => $this->exists('max_users')
                ? ($this->filled('max_users') ? $this->integer('max_users') : null)
                : $package->max_users,
        ]);
    }
}
