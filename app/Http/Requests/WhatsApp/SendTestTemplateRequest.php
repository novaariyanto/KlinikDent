<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class SendTestTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('whatsapp.templates.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:30'],
            'to_name' => ['nullable', 'string', 'max:100'],
            'body_params' => ['nullable', 'array'],
            'body_params.*' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
