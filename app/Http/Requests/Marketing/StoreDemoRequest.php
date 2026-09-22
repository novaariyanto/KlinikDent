<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class StoreDemoRequest extends FormRequest
{
    protected $errorBag = 'demo';

    public function authorize(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        return url()->to(route('home').'#demo');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'clinic' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
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
            'clinic' => 'nama klinik',
            'email' => 'email',
            'phone' => 'nomor WhatsApp',
            'message' => 'pesan',
        ];
    }
}
