<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class CheckTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('whatsapp.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string', 'max:80'],
        ];
    }
}
