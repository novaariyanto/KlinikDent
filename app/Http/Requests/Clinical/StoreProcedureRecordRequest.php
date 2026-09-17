<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Visit;
use App\Support\Clinical\FdiTeeth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcedureRecordRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('procedure.manage')
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
            'procedure_id' => [
                'required',
                'integer',
                Rule::exists('procedures', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)),
            ],
            'tooth_number' => ['nullable', 'string', Rule::in([...FdiTeeth::adult(), ...FdiTeeth::primary()])],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! filled($this->input('tooth_number'))) {
            $this->merge(['tooth_number' => null]);
        }
    }
}
