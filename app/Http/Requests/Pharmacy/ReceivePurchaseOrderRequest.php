<?php

namespace App\Http\Requests\Pharmacy;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('purchaseOrder');

        return $order instanceof PurchaseOrder
            && ($this->user()?->can('update', $order) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.batch_number' => ['required', 'string', 'max:50'],
            'items.*.expired_date' => ['required', 'date'],
        ];
    }
}
