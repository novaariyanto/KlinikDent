<?php

namespace App\Http\Requests\Finance\SharingFee;

use App\Enums\SharingFeeCalcType;
use App\Enums\SharingFeeMaterialDeductionType;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\SharingFeeRule;
use App\Support\Finance\SharingFee\SharingFeeRuleResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSharingFeeRuleRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', SharingFeeRule::class) ?? false;
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
            'calc_type' => ['required', Rule::enum(SharingFeeCalcType::class)],
            'rate' => ['required', 'numeric', 'min:0'],
            'material_deduction_type' => ['required', Rule::enum(SharingFeeMaterialDeductionType::class)],
            'material_deduction_value' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'material_deduction_value' => $this->input('material_deduction_value', 0),
            'doctor_user_id' => $this->normalizeScope($this->input('doctor_user_id')),
            'branch_id' => $this->normalizeScope($this->input('branch_id')),
            'procedure_id' => $this->normalizeScope($this->input('procedure_id')),
            'effective_to' => $this->filled('effective_to') ? $this->input('effective_to') : null,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $branchId = $this->input('branch_id');

            if ($branchId && $this->user() && ! $this->user()->canAccessBranch((int) $branchId)) {
                $validator->errors()->add('branch_id', 'Anda tidak memiliki akses ke cabang ini.');
            }

            if ($this->input('calc_type') === SharingFeeCalcType::Percentage->value
                && (float) $this->input('rate') > 100) {
                $validator->errors()->add('rate', 'Persentase fee tidak boleh lebih dari 100.');
            }

            if ($this->input('material_deduction_type') === SharingFeeMaterialDeductionType::Percent->value
                && (float) $this->input('material_deduction_value') > 100) {
                $validator->errors()->add('material_deduction_value', 'Potongan persen tidak boleh lebih dari 100.');
            }
        });
    }

    protected function normalizeScope(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === SharingFeeRuleResolver::SCOPE_ALL) {
            return null;
        }

        return $value;
    }
}
