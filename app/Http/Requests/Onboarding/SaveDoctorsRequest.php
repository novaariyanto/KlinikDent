<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SaveDoctorsRequest extends FormRequest
{
    use AuthorizesOwnerOnboarding;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'doctors' => ['nullable', 'array'],
            'doctors.*.name' => ['nullable', 'string', 'max:255'],
            'doctors.*.title' => ['nullable', 'string', 'max:50'],
            'doctors.*.sip' => ['nullable', 'string', 'max:80'],
            'doctors.*.specialization' => ['nullable', 'string', 'max:120'],
            'doctors.*.phone' => ['nullable', 'string', 'max:30'],
            'doctors.*.email' => ['nullable', 'email', 'max:255', 'distinct', 'unique:users,email'],
            'doctors.*.password' => ['nullable', 'string', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $doctors = collect($this->input('doctors', []))->map(function ($row) {
            foreach (['name', 'title', 'sip', 'specialization', 'phone', 'email', 'password'] as $key) {
                if (array_key_exists($key, $row) && $row[$key] === '') {
                    $row[$key] = null;
                }
            }

            return $row;
        })->all();

        $this->merge(['doctors' => $doctors]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('doctors', []) as $index => $row) {
                if (! filled($row['name'] ?? null)) {
                    continue;
                }

                if (! filled($row['email'] ?? null)) {
                    $validator->errors()->add("doctors.$index.email", 'Email dokter wajib diisi.');
                }

                if (! filled($row['password'] ?? null)) {
                    $validator->errors()->add("doctors.$index.password", 'Password dokter wajib diisi.');
                }
            }
        });
    }
}
