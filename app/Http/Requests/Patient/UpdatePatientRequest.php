<?php

namespace App\Http\Requests\Patient;

use App\Enums\Citizenship;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Http\Requests\Concerns\NormalizesWilayahCodes;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    use NormalizesWilayahCodes;
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return $this->user()?->can('update', $patient) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');
        $tenantId = $this->actorTenantId() ?? $patient->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'nik' => [
                'nullable',
                'digits:16',
                Rule::unique('patients', 'nik')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($patient->id),
            ],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'citizenship' => ['nullable', Rule::enum(Citizenship::class)],
            'family_card_number' => ['nullable', 'digits:16'],
            'phone' => ['nullable', 'string', 'max:30'],
            'marketing_consent' => ['sometimes', 'boolean'],
            'address' => ['nullable', 'string', 'max:1000'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_postal_code' => ['nullable', 'string', 'max:10'],
            'province_code' => ['nullable', 'string', 'max:10'],
            'city_code' => ['nullable', 'string', 'max:10'],
            'district_code' => ['nullable', 'string', 'max:10'],
            'village_code' => ['nullable', 'string', 'max:20'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'default_payer_id' => [
                'nullable',
                'integer',
                Rule::exists('payers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'bpjs_number' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'nik', 'default_payer_id', 'birth_place', 'marital_status', 'family_card_number',
            'address', 'address_city', 'address_postal_code',
            'province_code', 'city_code', 'district_code', 'village_code', 'rt', 'rw',
        ];

        $merge = [
            'marketing_consent' => $this->boolean('marketing_consent'),
            'citizenship' => $this->input('citizenship') ?: Citizenship::Wni->value,
        ];

        foreach ($nullable as $field) {
            $merge[$field] = $this->filled($field) ? $this->input($field) : null;
        }

        $this->merge($this->normalizeWilayahInto($merge));
    }
}
