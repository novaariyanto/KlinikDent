<?php

namespace App\Http\Resources;

use App\Models\Branch;
use App\Models\User;
use App\Support\Mobile\ActiveBranchResolver;
use App\Support\Onboarding\OnboardingCatalog;
use App\Support\Onboarding\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileUserResource extends JsonResource
{
    public function __construct($resource, protected ?Branch $activeBranch = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $tenant = $user->tenant;
        $active = $this->activeBranch ?? $request->attributes->get('active_branch');
        $step = $tenant
            ? app(OnboardingService::class)->currentStepKey($tenant)
            : null;

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'role' => $user->primaryRole()?->value,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'tenant' => $tenant ? [
                'id' => (int) $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status->value,
                'needs_onboarding' => $tenant->needsOnboarding(),
                'onboarding_step' => $step,
                'onboarding_step_number' => $step ? OnboardingCatalog::stepNumber($step) : null,
                'onboarding_progress' => $tenant->onboardingProgressPercent(),
            ] : null,
            'branches' => app(ActiveBranchResolver::class)
                ->accessibleBranches($user)
                ->map(fn (Branch $branch) => $this->branchPayload($branch))
                ->values()
                ->all(),
            'active_branch' => $active instanceof Branch ? $this->branchPayload($active) : null,
        ];
    }

    /**
     * @return array{id: int, name: string, is_active: bool}
     */
    protected function branchPayload(Branch $branch): array
    {
        return [
            'id' => (int) $branch->id,
            'name' => $branch->name,
            'is_active' => (bool) $branch->is_active,
        ];
    }
}