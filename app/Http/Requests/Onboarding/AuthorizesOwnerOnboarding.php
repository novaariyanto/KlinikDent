<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;

trait AuthorizesOwnerOnboarding
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->hasRole(RoleName::Owner) && $user->tenant_id && $user->tenant?->needsOnboarding());
    }
}
