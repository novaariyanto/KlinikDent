<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:tenants,subdomain'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'package_id' => [
                'nullable',
                'integer',
                Rule::exists('saas_packages', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama klinik',
            'subdomain' => 'subdomain',
            'owner_name' => 'nama owner',
            'owner_email' => 'email',
            'password' => 'password',
            'package_id' => 'paket',
        ];
    }

    protected function prepareForValidation(): void
    {
        $subdomain = strtolower((string) $this->input('subdomain', ''));

        if ($subdomain === '' && $this->filled('name')) {
            $subdomain = Str::slug((string) $this->input('name'));
        }

        $this->merge([
            'subdomain' => $subdomain,
            'package_id' => $this->filled('package_id') ? $this->input('package_id') : null,
        ]);
    }
}
