<?php

namespace App\Http\Requests\Clinical;

use App\Enums\ToothStatus;
use App\Enums\ToothSurface;
use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Support\Clinical\FdiTeeth;
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
        ];
    }
}
