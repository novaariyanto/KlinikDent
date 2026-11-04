<?php

namespace App\Http\Requests\Clinical;

use App\Enums\ToothStatus;
use App\Enums\ToothSurface;
use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Support\Clinical\FdiTeeth;
use App\Support\Clinical\ToothConditions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateToothRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('odontogram.manage')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    protected function prepareForValidation(): void
    {
        $conditions = $this->input('conditions');

        if (is_string($conditions)) {
            $decoded = json_decode($conditions, true);
            $this->merge(['conditions' => is_array($decoded) ? $decoded : 'invalid']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tooth_number' => ['required', 'string', Rule::in([...FdiTeeth::adult(), ...FdiTeeth::primary()])],
            'status' => ['required', Rule::enum(ToothStatus::class)],
            'notes' => ['nullable', 'string', 'max:255'],
            'surfaces' => ['nullable', 'array'],
            'surfaces.*' => ['string', Rule::enum(ToothSurface::class)],
            'conditions' => ['sometimes', 'array', 'max:'.ToothConditions::MAX],
            'conditions.*.code' => ['required', 'string', Rule::enum(ToothStatus::class)],
            'conditions.*.surfaces' => ['nullable', 'array'],
            'conditions.*.surfaces.*' => ['string', Rule::enum(ToothSurface::class)],
            'conditions.*.detail' => ['nullable', 'array'],
            'conditions.*.detail.*' => ['nullable', 'string', 'max:50'],
            'conditions.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
