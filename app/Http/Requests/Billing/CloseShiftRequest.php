<?php

namespace App\Http\Requests\Billing;

use App\Models\CashierShift;
use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shift = $this->route('cashierShift') ?? $this->route('shift');

        if ($shift instanceof CashierShift) {
            return $this->user()?->can('update', $shift) ?? false;
        }

        return $this->user()?->can('create', CashierShift::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'closing_balance' => ['required', 'numeric', 'min:0'],
            'close_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
