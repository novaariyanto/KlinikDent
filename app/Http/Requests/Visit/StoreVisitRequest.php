<?php

namespace App\Http\Requests\Visit;

use App\Enums\Gender;
use App\Http\Requests\Concerns\NormalizesWilayahCodes;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Visit;
use App\Rules\WilayahCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitRequest extends FormRequest
{
    use NormalizesWilayahCodes;
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
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_postal_code' => ['nullable', 'string', 'max:10'],
            'province_code' => ['nullable', 'string', 'max:10', new WilayahCode('province')],
            'city_code' => ['nullable', 'string', 'max:10', new WilayahCode('city', 'province_code')],
            'district_code' => ['nullable', 'string', 'max:10', new WilayahCode('district', 'city_code')],
            'village_code' => ['nullable', 'string', 'max:20', new WilayahCode('village', 'district_code')],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'satusehat_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'patient_id', 'nik', 'doctor_id', 'room_id', 'gender', 'phone', 'address',
            'address_city', 'address_postal_code',
            'province_code', 'city_code', 'district_code', 'village_code', 'rt', 'rw',
            'satusehat_id',
        ];

        $merge = [
            'visit_date' => $this->input('visit_date') ?: now()->toDateString(),
            'branch_id' => $this->input('branch_id') ?: $this->user()?->branch_id,
        ];

        foreach ($nullable as $field) {
            $merge[$field] = $this->filled($field) ? $this->input($field) : null;
        }

        $this->merge($this->normalizeWilayahInto($merge));
    }
}
