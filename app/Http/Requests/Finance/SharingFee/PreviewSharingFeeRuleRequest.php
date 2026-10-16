<?php

namespace App\Http\Requests\Finance\SharingFee;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\SharingFeeRule;
use App\Support\Finance\SharingFee\SharingFeeRuleResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewSharingFeeRuleRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SharingFeeRule::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'doctor_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'procedure_id' => [
                'nullable',
                'integer',
                Rule::exists('procedures', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'on_date' => ['required', 'date'],
            'gross_base' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'doctor_user_id' => $this->normalizeScope($this->input('doctor_user_id')),
            'branch_id' => $this->normalizeScope($this->input('branch_id')),
            'procedure_id' => $this->normalizeScope($this->input('procedure_id')),
            'on_date' => $this->input('on_date', now()->toDateString()),
            'quantity' => $this->input('quantity', 1),
        ]);
    }

    protected function normalizeScope(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === SharingFeeRuleResolver::SCOPE_ALL) {
            return null;
        }

        return $value;
    }
}
