<?php

namespace App\Http\Requests\Billing;

use App\Models\CashierShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashierShift::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $branchId = $this->user()?->can('branch.manage') ? null : $this->user()?->branch_id;

        return [
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where(function ($query) use ($tenantId, $branchId) {
                    $query->where('tenant_id', $tenantId);
                    if ($branchId) {
                        $query->where('id', $branchId);
                    }
                }),
            ],
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ];
    }
}
