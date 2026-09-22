<?php

namespace App\Http\Requests\WhatsApp;

use App\Support\WhatsApp\WhatsAppCatalog;
use App\Support\WhatsApp\WhatsAppParameterParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTemplateRequest extends FormRequest
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
            'whatsapp_template_name' => ['required', 'string', 'max:100'],
            'language_code' => ['required', Rule::in(array_keys(WhatsAppCatalog::languages()))],
            'preview_body' => ['required', 'string', 'max:5000'],
            'is_enabled' => ['sometimes', 'boolean'],
            'parameters' => ['nullable', 'array'],
            'parameters.*.position' => ['required', 'integer', 'min:1'],
            'parameters.*.label' => ['required', 'string', 'max:100'],
            'parameters.*.source' => ['required', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $preview = (string) $this->input('preview_body', '');
            $mapping = WhatsAppParameterParser::mergeMapping($preview, $this->input('parameters', []));
            $missing = WhatsAppParameterParser::missing($mapping);

            if ($missing !== []) {
                $validator->errors()->add('parameters', 'Semua parameter wajib diisi: '.implode(', ', $missing));
            }

            if ($this->boolean('is_enabled')) {
                $template = $this->route('whatsappTemplate');
                $status = is_object($template) ? $template->remote_status : null;

                if (filled($status) && $status !== 'approved') {
                    $validator->errors()->add('is_enabled', 'Template baru bisa diaktifkan setelah status Meta approved.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_enabled' => $this->boolean('is_enabled'),
        ]);
    }
}
