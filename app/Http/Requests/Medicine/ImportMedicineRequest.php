<?php

namespace App\Http\Requests\Medicine;

use App\Models\Medicine;
use Illuminate\Foundation\Http\FormRequest;

class ImportMedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Medicine::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimes:xlsx,xls',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file XLSX obat.',
            'file.mimes' => 'File harus berformat XLSX.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
