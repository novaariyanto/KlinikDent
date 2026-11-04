<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Visit;
use App\Support\Clinical\FdiTeeth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcedureRecordRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('procedure.manage')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'procedure_id' => [
                'required',
                'integer',
                Rule::exists('procedures', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)),
            ],
            'tooth_number' => ['nullable', 'string', Rule::in([...FdiTeeth::adult(), ...FdiTeeth::primary()])],
            'icd9_code' => [
                'nullable',
                'string',
                'max:16',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! \App\Support\Clinical\Icd9Catalog::has((string) $value)) {
                        $fail('Kode ICD-9-CM tidak valid.');
                    }
                },
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'waive_doctor_fee' => ['nullable', 'boolean'],
            'discount_type' => ['nullable', 'in:nominal,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'waiver_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! filled($this->input('icd9_code'))) {
            $this->merge(['icd9_code' => null]);
        }

        if (! filled($this->input('tooth_number'))) {
            $this->merge(['tooth_number' => null]);
        }

        $this->merge([
            'waive_doctor_fee' => $this->boolean('waive_doctor_fee'),
            'discount_type' => filled($this->input('discount_type')) ? $this->input('discount_type') : null,
            'discount_value' => filled($this->input('discount_value')) ? $this->input('discount_value') : null,
            'waiver_reason' => filled($this->input('waiver_reason')) ? $this->input('waiver_reason') : null,
        ]);
    }
}
