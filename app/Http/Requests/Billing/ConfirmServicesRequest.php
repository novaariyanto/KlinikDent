<?php

namespace App\Http\Requests\Billing;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmServicesRequest extends FormRequest
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
            'procedures' => ['nullable', 'array'],
            'procedures.*' => ['integer'],
            'medicines' => ['nullable', 'array'],
            'medicines.*' => ['integer'],
        ];
    }
}
