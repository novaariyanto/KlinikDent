<?php

namespace App\Http\Requests\Visit;

use App\Enums\Gender;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Visit::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();
        $creatingPatient = ! $this->filled('patient_id');

        return [
            'patient_id' => [
                'nullable',
                'integer',
                Rule::exists('patients', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'doctor_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'room_id' => [
                'nullable',
                'integer',
                Rule::exists('rooms', 'id'),
            ],
            'payer_id' => [
                'required',
                'integer',
                Rule::exists('payers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'visit_date' => ['nullable', 'date'],
            'name' => [$creatingPatient ? 'required' : 'nullable', 'string', 'max:255'],
            'nik' => ['nullable', 'digits:16'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'patient_id' => $this->filled('patient_id') ? $this->input('patient_id') : null,
            'doctor_id' => $this->filled('doctor_id') ? $this->input('doctor_id') : null,
            'room_id' => $this->filled('room_id') ? $this->input('room_id') : null,
            'visit_date' => $this->input('visit_date') ?: now()->toDateString(),
            'branch_id' => $this->input('branch_id') ?: $this->user()?->branch_id,
        ]);
    }
}
