<?php

namespace App\Http\Requests\Finance\SharingFee;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ResolvesActorTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaySharingFeeSettlementRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        $settlement = $this->route('sharingFeeSettlement');

        return $settlement
            ? ($this->user()?->can('pay', $settlement) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();
        $settlement = $this->route('sharingFeeSettlement');
        $branchId = $settlement?->branch_id;

        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'cash_account_id' => [
                'required',
                'integer',
                Rule::exists('cash_accounts', 'id')->where(function ($query) use ($tenantId, $branchId) {
                    $query->where('tenant_id', $tenantId);
                    if ($branchId) {
                        $query->where('branch_id', $branchId);
                    }
                }),
            ],
            'paid_on' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
