<?php

namespace App\Http\Requests\Clinical;

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
            'tooth_number' => ['nullable', 'string', Rule::in([...FdiTeeth::adult(), ...FdiTeeth::primary()])],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! filled($this->input('tooth_number'))) {
            $this->merge(['tooth_number' => null]);
        }
    }
}
