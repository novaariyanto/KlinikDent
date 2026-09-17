<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Support\Clinical\CareExamOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemicHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        $user = $this->user();

        return ($user?->can('anamnesis.manage') || $user?->can('medical_record.update'))
            && $user?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conditions' => ['nullable', 'array'],
            'conditions.*' => ['string', Rule::in(array_keys(CareExamOptions::systemic()))],
            'blood_pressure_sys' => ['nullable', 'string', 'max:10'],
            'blood_pressure_dia' => ['nullable', 'string', 'max:10'],
            'allergy_detail' => ['nullable', 'string', 'max:255'],
            'medication_detail' => ['nullable', 'string', 'max:255'],
            'other_detail' => ['nullable', 'string', 'max:255'],
            'none' => ['nullable', 'boolean'],
        ];
    }
}
