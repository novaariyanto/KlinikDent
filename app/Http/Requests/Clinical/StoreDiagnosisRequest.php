<?php

namespace App\Http\Requests\Clinical;

use App\Enums\DiagnosisKind;
use App\Enums\DiagnosisRank;
use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Support\Clinical\FdiTeeth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('diagnosis.manage')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tooth_number' => ['sometimes', 'nullable', 'string', Rule::in([...FdiTeeth::adult(), ...FdiTeeth::primary()])],
            'kind' => ['sometimes', 'nullable', Rule::enum(DiagnosisKind::class)],
            'rank' => ['sometimes', 'nullable', Rule::enum(DiagnosisRank::class)],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'clinical_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('tooth_number') && ! filled($this->input('tooth_number'))) {
            $this->merge(['tooth_number' => null]);
        }

        if ($this->isMethod('post')) {
            if (! filled($this->input('kind'))) {
                $this->merge(['kind' => DiagnosisKind::Clinical->value]);
            }

            if (! filled($this->input('rank'))) {
                $this->merge(['rank' => DiagnosisRank::Secondary->value]);
            }
        }

        if ($this->exists('code') && ! filled($this->input('code'))) {
            $this->merge(['code' => null]);
        }
    }
}
