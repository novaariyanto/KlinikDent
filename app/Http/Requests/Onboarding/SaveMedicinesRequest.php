<?php

namespace App\Http\Requests\Onboarding;

use App\Models\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMedicinesRequest extends FormRequest
{
    use AuthorizesOwnerOnboarding;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['skip', 'template', 'manual'])],
            'medicines' => ['nullable', 'array'],
            'medicines.*.name' => ['nullable', 'string', 'max:255'],
            'medicines.*.kfa_code' => ['nullable', 'string', 'max:40'],
            'medicines.*.unit' => ['nullable', Rule::in(array_keys(Medicine::UNITS))],
            'medicines.*.category' => ['nullable', Rule::in(array_keys(Medicine::CATEGORIES))],
            'medicines.*.base_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('mode') !== 'manual') {
                return;
            }

            $items = array_filter((array) $this->input('medicines', []), fn ($row) => filled($row['name'] ?? null));

            if ($items === []) {
                $validator->errors()->add('medicines', 'Tambahkan minimal satu obat atau pilih opsi lain.');
            }
        });
    }
}
