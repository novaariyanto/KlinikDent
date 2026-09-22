<?php

namespace App\Http\Requests\Saas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->isPlatformAdmin() && $this->user()?->can('payment_gateway.manage'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'merchant_id' => ['nullable', 'string', 'max:100'],
            'client_key' => ['nullable', 'required_if:enabled,1', 'string', 'max:255'],
            'server_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_key.required_if' => 'Client Key wajib diisi jika Midtrans diaktifkan.',
        ];
    }
}
