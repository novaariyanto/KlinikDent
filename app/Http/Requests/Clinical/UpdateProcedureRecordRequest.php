<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\ProcedureRecord;
use App\Models\Visit;
use App\Support\Clinical\Icd9Catalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'icd9_code' => ['nullable', 'string', Rule::in(array_keys(Icd9Catalog::options()))],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! filled($this->input('icd9_code'))) {
            $this->merge(['icd9_code' => null]);
        }
    }
}
