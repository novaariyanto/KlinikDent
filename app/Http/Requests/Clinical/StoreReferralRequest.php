<?php

namespace App\Http\Requests\Clinical;

use App\Enums\VisitStatus;
use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

class StoreReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('referral.manage')
            && $this->user()?->can('view', $visit)
            && $visit->status !== VisitStatus::Cancelled;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'referred_to' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
