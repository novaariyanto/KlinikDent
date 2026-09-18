<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class SendTestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->isPlatformAdmin() && $this->user()?->can('settings.update'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'test_email' => ['required', 'email', 'max:255'],
        ];
    }
}
