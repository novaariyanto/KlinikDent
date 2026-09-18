<?php

namespace App\Http\Requests\Setting;

use App\Support\ClinicSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('setting.manage') && current_tenant_id());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'clinic_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:32'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'pic_sip' => ['nullable', 'string', 'max:100'],
            'print_city' => ['nullable', 'string', 'max:100'],
            'print_footer' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', Rule::in(array_keys(ClinicSettings::timezones()))],
            'rm_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'clinic_name' => 'nama klinik',
            'legal_name' => 'nama badan usaha',
            'tagline' => 'slogan',
            'address' => 'alamat',
            'city' => 'kota',
            'province' => 'provinsi',
            'postal_code' => 'kode pos',
            'phone' => 'telepon',
            'email' => 'email',
            'website' => 'situs web',
            'npwp' => 'NPWP',
            'license_number' => 'nomor izin klinik',
            'pic_name' => 'penanggung jawab',
            'pic_sip' => 'SIP penanggung jawab',
            'print_city' => 'kota dokumen',
            'print_footer' => 'catatan kaki dokumen',
            'timezone' => 'zona waktu',
            'rm_prefix' => 'prefix nomor RM',
            'logo' => 'logo',
        ];
    }

    protected function prepareForValidation(): void
    {
        $prefix = strtoupper(preg_replace('/\s+/', '', (string) $this->input('rm_prefix', '')) ?? '');

        $this->merge([
            'rm_prefix' => $prefix !== '' ? $prefix : null,
            'remove_logo' => $this->boolean('remove_logo'),
        ]);
    }
}
