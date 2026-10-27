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
            'plan_instructions.extra' => ['nullable', 'string', 'max:5000', Rule::requiredIf(function () {
                $items = $this->input('plan_instructions.items', []);

                return is_array($items) && in_array('lainnya', $items, true);
            })],
            'plan_instructions.prognosis' => ['nullable', 'string', 'max:2000'],
            'plan_instructions.goal' => ['nullable', 'string', 'max:2000'],
            'plan_instructions.status' => ['nullable', 'string', Rule::in(array_keys(CareExamOptions::carePlanStatuses()))],
            'control_date' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $plan = $this->input('plan_instructions');

        if (! is_array($plan)) {
            return;
        }

        foreach (['extra', 'goal', 'prognosis'] as $key) {
            if (! array_key_exists($key, $plan) || ! is_string($plan[$key])) {
                continue;
            }

            $plan[$key] = trim($plan[$key]);

            if ($plan[$key] === '') {
                $plan[$key] = null;
            }
        }

        $this->merge(['plan_instructions' => $plan]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_instructions.extra.required' => 'Isi teks instruksi lainnya.',
        ];
    }
}
