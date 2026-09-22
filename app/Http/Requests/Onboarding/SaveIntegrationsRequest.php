<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveIntegrationsRequest extends FormRequest
{
    use AuthorizesOwnerOnboarding;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $configureSatuSehat = $this->input('satusehat_action') === 'configure';
        $configureBpjs = $this->input('bpjs_action') === 'configure';

        return [
            'satusehat_action' => ['nullable', Rule::in(['skip', 'configure'])],
            'bpjs_action' => ['nullable', Rule::in(['skip', 'configure'])],
            'satusehat.client_id' => [Rule::requiredIf($configureSatuSehat), 'nullable', 'string', 'max:255'],
            'satusehat.client_secret' => ['nullable', 'string', 'max:255'],
            'satusehat.organization_id' => ['nullable', 'string', 'max:255'],
            'satusehat.base_url' => ['nullable', 'url', 'max:255'],
            'satusehat.token_url' => ['nullable', 'url', 'max:255'],
            'bpjs.cons_id' => [Rule::requiredIf($configureBpjs), 'nullable', 'string', 'max:255'],
            'bpjs.secret_key' => ['nullable', 'string', 'max:255'],
            'bpjs.user_key' => ['nullable', 'string', 'max:255'],
            'bpjs.base_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'satusehat_action' => $this->input('satusehat_action') ?: 'skip',
            'bpjs_action' => $this->input('bpjs_action') ?: 'skip',
        ]);
    }
}
