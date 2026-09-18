<?php

namespace App\Http\Requests\Pharmacy;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseOrder::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $ids = $this->user()?->restrictedBranchIds();

        return [
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where(function ($query) use ($tenantId, $ids) {
                    $query->where('tenant_id', $tenantId);

                    if ($ids !== null) {
                        $query->whereIn('id', $ids);
                    }
                }),
            ],
            'order_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => [
                'required',
                Rule::exists('medicines', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)),
                'distinct',
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($row) => filled(data_get($row, 'medicine_id')))
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Minimal satu item obat.',
            'items.*.medicine_id.distinct' => 'Obat pada PO tidak boleh duplikat.',
        ];
    }
}
