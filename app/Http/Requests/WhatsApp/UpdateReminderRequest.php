<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('whatsapp.automation.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reminder_enabled' => ['sometimes', 'boolean'],
            'h2' => ['sometimes', 'boolean'],
            'h1' => ['sometimes', 'boolean'],
            'today' => ['sometimes', 'boolean'],
            'send_time' => ['required', 'date_format:H:i'],
            'confirmation' => ['sometimes', 'boolean'],
            'rescheduled' => ['sometimes', 'boolean'],
            'cancelled' => ['sometimes', 'boolean'],
            'missed' => ['sometimes', 'boolean'],
            'control' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $booleans = ['reminder_enabled', 'h2', 'h1', 'today', 'confirmation', 'rescheduled', 'cancelled', 'missed', 'control'];
        $merged = [];

        foreach ($booleans as $field) {
            $merged[$field] = $this->boolean($field);
        }

        $this->merge($merged);
    }
}
