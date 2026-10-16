<?php

namespace App\Http\Requests\Finance\SharingFee;

use App\Enums\SharingFeePeriodType;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\SharingFeeSettlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateSharingFeeSettlementRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        return $this->user()?->can('create', SharingFeeSettlement::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();

        return [
            'period_type' => ['required', Rule::enum(SharingFeePeriodType::class)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'doctor_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $branchId = (int) $this->input('branch_id');

            if ($branchId && $this->user() && ! $this->user()->canAccessBranch($branchId)) {
                $validator->errors()->add('branch_id', 'Anda tidak memiliki akses ke cabang ini.');
            }
        });
    }
}
