<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRemoteTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'language' => ['required', Rule::in(['id', 'en', 'en_US'])],
            'header_type' => ['required', Rule::in(['none', 'text'])],
            'header_text' => ['nullable', 'required_if:header_type,text', 'string', 'max:60'],
            'body_text' => ['required', 'string', 'max:5000'],
            'footer_text' => ['nullable', 'string', 'max:60'],
        ];
    }
}
