<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class TestSatuSehatConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('integration.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ];
    }
}
