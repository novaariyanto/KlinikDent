<?php

namespace App\Http\Resources;

use App\Enums\OnboardingTemplate;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\ClinicSettings;
use App\Support\Onboarding\OnboardingCatalog;
use App\Support\Onboarding\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $tenant = $user->tenant;
        $onboarding = app(OnboardingService::class);
        $step = $onboarding->currentStepKey($tenant);
        $template = $tenant->onboarding_template ?? OnboardingTemplate::Small;
        $settings = ClinicSettings::current((int) $tenant->id);
        $branch = $tenant->branches()->orderBy('id')->first();
        $summary = $onboarding->summary($tenant);

        return [
            'needs_onboarding' => $tenant->needsOnboarding(),
            'step' => $step,
            'step_number' => OnboardingCatalog::stepNumber($step),
            'total_steps' => OnboardingCatalog::TOTAL_STEPS,
            'progress' => $tenant->onboardingProgressPercent(),
            'optional' => OnboardingCatalog::isOptional($step),
            'templates' => collect(OnboardingTemplate::cases())->map(fn (OnboardingTemplate $item) => [
                'value' => $item->value,
                'label' => $item->label(),
                'description' => $item->description(),
            ])->values()->all(),
            'selected_roles' => $this->roles($tenant->onboarding_selected_roles ?? []),
            'recommended_roles' => $this->roles(OnboardingCatalog::recommendedRoles($template)),
            'other_roles' => $this->roles(OnboardingCatalog::otherRoles($template)),
            'profile' => [
                'clinic_name' => $settings['clinic_name'] ?? '',
                'tagline' => $settings['tagline'] ?? '',
                'rm_prefix' => $settings['rm_prefix'] ?? '',
                'address' => $settings['address'] ?? '',
                'phone' => $settings['phone'] ?? '',
                'email' => $settings['email'] ?? '',
                'npwp' => $settings['npwp'] ?? '',
                'license_number' => $settings['license_number'] ?? '',
            ],
            'logo_url' => ClinicSettings::logoUrl('dark', (int) $tenant->id),
            'branch' => $branch ? [
                'id' => (int) $branch->id,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'is_active' => (bool) $branch->is_active,
            ] : null,
            'summary' => $summary,
            'medicine_template_count' => OnboardingCatalog::medicineTemplateCount(),
            'satusehat_configured' => (bool) ($summary['satusehat'] ?? false),
            'bpjs_configured' => (bool) ($summary['bpjs'] ?? false),
        ];
    }

    /**
     * @param  list<string>  $roles
     * @return list<array{value: string, label: string}>
     */
    protected function roles(array $roles): array
    {
        return collect($roles)
            ->map(function (string $role) {
                $enum = RoleName::tryFrom($role);

                return $enum ? [
                    'value' => $enum->value,
                    'label' => $enum->label(),
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}