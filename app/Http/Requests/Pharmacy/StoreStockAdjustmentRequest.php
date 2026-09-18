<?php

namespace App\Http\Requests\Pharmacy;

use App\Models\MedicineStock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MedicineStock::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'medicine_stock_id' => [
                'required',
                Rule::exists('medicine_stocks', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'delta' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'notes' => ['required', 'string', 'max:255'],
        ];
    }
}
