<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrescriptionMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');
        /** @var Prescription $prescription */
        $prescription = $this->route('prescription');

        return $this->user()?->can('prescription.update')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled
            && (int) $prescription->visit_id === (int) $visit->id
            && $prescription->isDraft();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $depots = config('pharmacy.depots', []);

        return [
            'pharmacy_depot' => ['nullable', 'string', 'max:100', Rule::in($depots)],
            'prescription_kind' => ['nullable', 'string', Rule::in(['biasa', 'cito'])],
            'iter' => ['nullable', 'integer', Rule::in([1, 2])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $iter = $this->input('iter');
        $this->merge([
            'pharmacy_depot' => filled($this->input('pharmacy_depot')) ? $this->input('pharmacy_depot') : null,
            'prescription_kind' => filled($this->input('prescription_kind')) ? $this->input('prescription_kind') : 'biasa',
            'iter' => filled($iter) ? (int) $iter : null,
        ]);
    }
}
