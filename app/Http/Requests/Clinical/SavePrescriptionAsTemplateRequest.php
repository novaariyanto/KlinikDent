<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePrescriptionAsTemplateRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');
        /** @var Prescription|null $prescription */
        $prescription = $this->route('prescription');

        return $this->user()?->can('prescription.create')
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
        $tenantId = $this->actorTenantId();
        $userId = $this->user()?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('prescription_templates', 'name')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)->where('created_by', $userId)
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Nama template ini sudah ada pada akun Anda.',
            'name.required' => 'Nama template wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
