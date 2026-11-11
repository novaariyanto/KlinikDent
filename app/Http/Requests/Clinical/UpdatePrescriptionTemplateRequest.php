<?php

namespace App\Http\Requests\Clinical;

use App\Models\PrescriptionTemplate;
use Illuminate\Validation\Rule;

class UpdatePrescriptionTemplateRequest extends StorePrescriptionTemplateRequest
{
    public function authorize(): bool
    {
        /** @var PrescriptionTemplate|null $template */
        $template = $this->route('prescriptionTemplate');

        return parent::authorize() && ($template?->canBeManagedBy($this->user()) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $tenantId = $this->actorTenantId();
        $userId = $this->user()?->id;
        /** @var PrescriptionTemplate $template */
        $template = $this->route('prescriptionTemplate');

        $rules['name'] = [
            'required',
            'string',
            'max:120',
            Rule::unique('prescription_templates', 'name')
                ->ignore($template->id)
                ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('created_by', $template->created_by ?: $userId)),
        ];

        return $rules;
    }
}
