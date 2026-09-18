<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePrescriptionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');
        /** @var PrescriptionItem $item */
        $item = $this->route('prescriptionItem');
        $prescription = $item?->prescription;

        return $this->user()?->can('prescription.update')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled
            && $prescription
            && (int) $prescription->visit_id === (int) $visit->id
            && $prescription->isDraft();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dosage' => ['nullable', 'string', 'max:100'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'duration' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
