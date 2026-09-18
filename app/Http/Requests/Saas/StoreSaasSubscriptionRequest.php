<?php

namespace App\Http\Requests\Saas;

use App\Models\SaasSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaasSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SaasSubscription::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'package_id' => ['required', 'integer', Rule::exists('saas_packages', 'id')->where('is_active', true)],
            'trial' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'trial' => $this->boolean('trial'),
        ]);
    }
}
