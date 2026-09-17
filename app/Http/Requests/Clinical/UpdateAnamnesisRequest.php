<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAnamnesisRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('anamnesis.manage')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'anamnesis' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
