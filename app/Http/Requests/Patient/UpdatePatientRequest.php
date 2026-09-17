<?php

namespace App\Http\Requests\Patient;

use App\Enums\Gender;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
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
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'default_payer_id' => [
                'nullable',
                'integer',
                Rule::exists('payers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nik' => $this->filled('nik') ? $this->input('nik') : null,
            'default_payer_id' => $this->filled('default_payer_id') ? $this->input('default_payer_id') : null,
        ]);
    }
}
