<?php

namespace App\Http\Requests\Billing;

use App\Models\Invoice;
use App\Support\Billing\BillingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeclineMedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            && ($this->user()?->can('update', $invoice) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(array_keys(BillingService::DECLINE_REASONS))],
            'detail' => ['nullable', 'string', 'max:500', 'required_if:reason,lainnya'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'Isi jumlah yang tidak jadi diambil.',
            'quantity.min' => 'Jumlah yang tidak diambil minimal 1.',
            'reason.required' => 'Pilih alasan.',
            'detail.required_if' => 'Isi keterangan untuk alasan lainnya.',
        ];
    }
}
