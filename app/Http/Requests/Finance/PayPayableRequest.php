<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ResolvesActorTenant;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayPayableRequest extends FormRequest
{
    use ResolvesActorTenant;

    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('purchaseOrder');

        if (! $user || ! $order instanceof PurchaseOrder) {
            return false;
        }

        return $user->can('payable.view')
            && $user->can('finance.manage')
            && $user->belongsToTenantId($order->tenant_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->actorTenantId();
        $order = $this->route('purchaseOrder');
        $branchId = $order instanceof PurchaseOrder ? $order->branch_id : null;

        return [
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
        ];
    }
}
