<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Support\Clinical\CareExamOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('medical_record.update')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'chief_complaint' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'clinical_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'plan_instructions' => ['sometimes', 'array'],
            'plan_instructions.items' => ['nullable', 'array'],
            'plan_instructions.items.*' => ['string', Rule::in(array_keys(CareExamOptions::instructions()))],
            'plan_instructions.extra' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
