<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrescriptionItemRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('prescription.create')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'medicine_id' => [
                'required',
                'integer',
                Rule::exists('medicines', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)),
            ],
            'dosage' => ['nullable', 'string', 'max:100'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'duration' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
