<?php

namespace App\Http\Requests\Medicine;

use App\Models\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMedicineStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $medicine = $this->route('medicine');

        return $this->user()?->can('stock.manage')
            && $medicine instanceof Medicine
            && $this->user()->can('view', $medicine);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'batch_number' => ['required', 'string', 'max:255'],
            'expired_date' => ['required', 'date'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $branchId = (int) $this->input('branch_id');

            if ($branchId > 0 && ! $this->user()?->canAccessBranch($branchId)) {
                $validator->errors()->add('branch_id', 'Cabang ini tidak bisa dipakai akun Anda.');
            }
        });
    }
}
