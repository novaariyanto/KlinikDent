<?php

namespace App\Http\Requests\Saas;

use App\Enums\AddonBillingInterval;
use App\Http\Controllers\Saas\AddonController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaasAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('addon.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:saas_addons,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'benefits' => ['nullable', 'array'],
            'benefits.*' => ['string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'interval' => ['required', Rule::enum(AddonBillingInterval::class)],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'terms' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => AddonController::slugCode((string) $this->input('code', '')),
            'benefits' => AddonController::normalizeBenefits($this->input('benefits')),
            'is_active' => $this->boolean('is_active', true),
            'trial_days' => $this->filled('trial_days') ? $this->integer('trial_days') : 0,
        ]);
    }
}
