<?php

namespace App\Http\Requests\Auth;

use App\Models\Tenant;
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
        $this->merge([
            'subdomain' => $this->uniqueSubdomain((string) $this->input('name')),
            'package_id' => $this->filled('package_id') ? $this->input('package_id') : null,
        ]);
    }

    protected function uniqueSubdomain(string $name): string
    {
        $base = Str::slug($name);
        $base = trim(substr($base !== '' ? $base : 'klinik', 0, 50), '-');
        $base = $base !== '' ? $base : 'klinik';

        $candidate = $base;
        $suffix = 2;

        while (Tenant::query()->where('subdomain', $candidate)->exists()) {
            $tail = '-'.$suffix;
            $candidate = trim(substr($base, 0, 63 - strlen($tail)).$tail, '-');
            $suffix++;

            if ($suffix > 999) {
                return trim(substr($base, 0, 54), '-').'-'.Str::lower(Str::random(6));
            }
        }

        return $candidate;
    }
}
