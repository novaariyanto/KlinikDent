<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class StoreStarterKitRequest extends FormRequest
{
    protected $errorBag = 'starterKit';

    public function authorize(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        return url()->previous().'#starter-kit';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'nomor WhatsApp',
        ];
    }
}
