<?php

namespace App\Http\Requests\Medicine;

use App\Models\Medicine;
use App\Models\MedicineStock;
use Illuminate\Foundation\Http\FormRequest;

class AdjustMedicineStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $medicine = $this->route('medicine');
        $stock = $this->route('medicineStock');

        return $medicine instanceof Medicine
            && $stock instanceof MedicineStock
            && (int) $stock->medicine_id === (int) $medicine->id
            && ($this->user()?->can('update', $stock) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delta' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'notes' => ['required', 'string', 'max:255'],
        ];
    }
}
