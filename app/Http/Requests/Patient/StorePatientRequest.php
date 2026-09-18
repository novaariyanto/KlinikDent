<?php

namespace App\Http\Requests\Patient;

use App\Enums\Gender;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Patient::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'nik' => [
                'nullable',
                'digits:16',
                Rule::unique('patients', 'nik')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
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
            'branch_id' => [
                Rule::requiredIf(fn () => ! $this->user()?->branch_id),
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'bpjs_number' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nik' => $this->filled('nik') ? $this->input('nik') : null,
            'default_payer_id' => $this->filled('default_payer_id') ? $this->input('default_payer_id') : null,
            'branch_id' => $this->input('branch_id') ?: $this->user()?->branch_id,
        ]);
    }
}
