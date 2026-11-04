<?php

namespace App\Http\Requests\Clinical;

use App\Enums\AssessmentChange;
use App\Enums\VisitStatus;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFollowUpReviewRequest extends FormRequest
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
        $change = Rule::in(array_keys(AssessmentChange::options()));

        return [
            'complaint_now' => ['nullable', 'string', 'max:2000'],
            'complaint_change' => ['nullable', 'string', 'max:2000'],
            'prior_response' => ['nullable', 'string', 'max:2000'],
            'adverse_effect' => ['nullable', 'string', 'max:2000'],
            'compliance' => ['nullable', 'string', 'max:1000'],
            'health_change' => ['nullable', 'string', 'max:2000'],
            'objective_note' => ['nullable', 'string', 'max:2000'],
            'treated_tooth_eval' => ['nullable', 'string', 'max:2000'],
            'perio_eval' => ['nullable', 'string', 'max:2000'],
            'new_findings' => ['nullable', 'string', 'max:2000'],
            'plan_decision' => ['nullable', Rule::in(['continue', 'change', 'additional', 'control', 'end', 'refer'])],
            'plan_note' => ['nullable', 'string', 'max:2000'],
            'control_date' => ['nullable', 'date'],
            'indicators' => ['nullable', 'array'],
            'indicators.complaint' => ['nullable', $change],
            'indicators.findings' => ['nullable', $change],
            'indicators.diagnosis' => ['nullable', $change],
            'indicators.plan' => ['nullable', $change],
            'indicators.procedure' => ['nullable', $change],
            'diagnosis_outcomes' => ['nullable', 'array'],
            'diagnosis_outcomes.*' => ['nullable', Rule::in(['unchanged', 'changed', 'added', 'resolved'])],
            'procedure_evals' => ['nullable', 'array'],
            'procedure_evals.*.result' => ['nullable', 'string', 'max:1000'],
            'procedure_evals.*.response' => ['nullable', 'string', 'max:1000'],
            'procedure_evals.*.success' => ['nullable', 'string', 'max:1000'],
            'procedure_evals.*.complication' => ['nullable', 'string', 'max:1000'],
            'procedure_evals.*.next' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
