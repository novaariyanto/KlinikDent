<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\OnboardingTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectTemplateRequest extends FormRequest
{
    use AuthorizesOwnerOnboarding;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template' => ['required', Rule::enum(OnboardingTemplate::class)],
        ];
    }
}
