<?php

namespace App\Http\Requests\Doctor;

use App\Models\Doctor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Doctor $doctor */
        $doctor = $this->route('doctor');

        return $this->user()?->can('update', $doctor) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sip' => ['nullable', 'string', 'max:80'],
            'str' => ['nullable', 'string', 'max:80'],
            'nik' => [
                'nullable',
                'digits:16',
                Rule::unique('doctors', 'nik')->where(fn ($query) => $query->where('tenant_id', $doctor->tenant_id))->ignore($doctor->id),
            ],
            'specialization' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'nik' => $this->filled('nik') ? $this->input('nik') : null,
        ]);
    }
}
