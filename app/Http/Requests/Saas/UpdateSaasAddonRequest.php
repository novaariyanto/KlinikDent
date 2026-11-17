<?php

namespace App\Http\Requests\Saas;

use App\Enums\AddonBillingInterval;
use App\Http\Controllers\Saas\AddonController;
use App\Models\SaasAddon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaasAddonRequest extends FormRequest
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
        /** @var SaasAddon $addon */
        $addon = $this->route('saasAddon');

        return [
            'code' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('saas_addons', 'code')->ignore($addon->id)],
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
        /** @var SaasAddon $addon */
        $addon = $this->route('saasAddon');

        $this->merge([
            'code' => $this->filled('code')
                ? AddonController::slugCode((string) $this->input('code'))
                : $addon->code,
            'benefits' => AddonController::normalizeBenefits($this->input('benefits')),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : $addon->is_active,
            'trial_days' => $this->exists('trial_days')
                ? ($this->filled('trial_days') ? $this->integer('trial_days') : 0)
                : $addon->trial_days,
        ]);
    }
}
