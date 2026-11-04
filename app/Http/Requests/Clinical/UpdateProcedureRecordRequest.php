<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\ProcedureRecord;
use App\Models\Visit;
use App\Support\Clinical\Icd9Catalog;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProcedureRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');
        /** @var ProcedureRecord|null $record */
        $record = $this->route('procedure_record');

        return $this->user()?->can('procedure.manage')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled
            && $record
            && (int) $record->visit_id === (int) $visit->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'icd9_code' => [
                'nullable',
                'string',
                'max:16',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! Icd9Catalog::has((string) $value)) {
                        $fail('Kode ICD-9-CM tidak valid.');
                    }
                },
            ],
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

        $this->merge([
            'waive_doctor_fee' => $this->boolean('waive_doctor_fee'),
            'discount_type' => filled($this->input('discount_type')) ? $this->input('discount_type') : null,
            'discount_value' => filled($this->input('discount_value')) ? $this->input('discount_value') : null,
            'waiver_reason' => filled($this->input('waiver_reason')) ? $this->input('waiver_reason') : null,
        ]);
    }
}
