<?php

namespace App\Http\Requests\Setting;

use App\Support\ClinicTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateThemeSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'theme_preset' => ['required', Rule::in(array_keys(ClinicTheme::options()))],
            'theme_primary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_navy' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_accent' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'theme_preset' => 'template tema',
            'theme_primary' => 'warna utama',
            'theme_navy' => 'navy',
            'theme_accent' => 'aksen',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('theme_preset') !== ClinicTheme::PRESET_CUSTOM) {
                return;
            }

            foreach (['theme_primary', 'theme_navy', 'theme_accent'] as $field) {
                if (! filled($this->input($field))) {
                    $validator->errors()->add($field, 'Warna wajib diisi untuk tema sendiri.');
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach (ClinicTheme::contrastErrors(
                (string) $this->input('theme_primary'),
                (string) $this->input('theme_navy'),
                (string) $this->input('theme_accent'),
            ) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'theme_primary' => $this->filled('theme_primary') ? strtoupper((string) $this->input('theme_primary')) : null,
            'theme_navy' => $this->filled('theme_navy') ? strtoupper((string) $this->input('theme_navy')) : null,
            'theme_accent' => $this->filled('theme_accent') ? strtoupper((string) $this->input('theme_accent')) : null,
        ]);
    }
}
