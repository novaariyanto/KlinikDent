<?php

namespace App\Http\Requests\Billing;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        if ($invoice instanceof Invoice) {
            return $this->user()?->can('create', \App\Models\Payment::class)
                && $this->user()?->can('view', $invoice);
        }

        return $this->user()?->can('create', \App\Models\Payment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'invoice_id' => [
                Rule::requiredIf(! $this->route('invoice')),
                Rule::exists('invoices', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', '!=', 'void')),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
