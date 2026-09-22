<?php

namespace App\Http\Requests\WhatsApp;

use App\Support\WhatsApp\WhatsAppBalanceService;
use App\Support\WhatsApp\WhatsAppPhone;
use Illuminate\Foundation\Http\FormRequest;

class StoreTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('whatsapp.settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1000'],
            'payment_channel' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'regex:/^08[0-9]{8,13}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'Nominal topup minimal Rp 1.000.',
            'phone.regex' => 'Nomor HP harus format 08xxxxxxxxxx.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $rawPhone = trim((string) $this->input('phone'));
        $phone = WhatsAppPhone::toLocal($rawPhone);

        if ($rawPhone !== '' && $phone === null) {
            $phone = preg_replace('/\D+/', '', $rawPhone) ?: $rawPhone;
        }

        $this->merge([
            'amount' => $this->filled('amount') ? (int) $this->input('amount') : null,
            'payment_channel' => $this->filled('payment_channel') ? $this->string('payment_channel')->toString() ?: null : null,
            'phone' => $phone,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $channel = (string) $this->input('payment_channel');

            if (WhatsAppBalanceService::channelNeedsPhone($channel) && blank($this->input('phone'))) {
                $validator->errors()->add('phone', 'Nomor HP wajib untuk channel e-wallet / QRIS.');
            }
        });
    }
}
