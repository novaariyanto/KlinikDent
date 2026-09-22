<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class SaveProfileRequest extends FormRequest
{
    use AuthorizesOwnerOnboarding;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'clinic_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'rm_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9-]*$/'],
            'address' => ['required', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:32'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'clinic_name' => 'nama klinik',
            'address' => 'alamat',
            'rm_prefix' => 'kode klinik',
        ];
    }

    protected function prepareForValidation(): void
    {
        $prefix = strtoupper(preg_replace('/\s+/', '', (string) $this->input('rm_prefix', '')) ?? '');

        $this->merge([
            'rm_prefix' => $prefix !== '' ? $prefix : null,
        ]);
    }
}
