<?php

namespace App\Http\Requests\Finance\SharingFee;

use Illuminate\Foundation\Http\FormRequest;

class AdjustSharingFeeSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $settlement = $this->route('sharingFeeSettlement');

        return $settlement
            ? ($this->user()?->can('adjust', $settlement) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:255'],
            'settlement_item_id' => ['nullable', 'integer', 'exists:sharing_fee_settlement_items,id'],
        ];
    }
}
