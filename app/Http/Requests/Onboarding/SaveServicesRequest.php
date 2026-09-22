<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveServicesRequest extends FormRequest
{
    use AuthorizesOwnerOnboarding;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['template', 'empty'])],
        ];
    }
}
