<?php

namespace Database\Factories;

use App\Enums\OnboardingStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'subdomain' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => TenantStatus::Active,
            'plan_id' => null,
            'onboarding_status' => OnboardingStatus::Completed,
            'onboarding_template' => null,
            'onboarding_current_step' => 10,
            'onboarding_selected_roles' => null,
            'onboarding_completed_at' => now(),
        ];
    }

    public function pendingOnboarding(): static
    {
        return $this->state(fn () => [
            'onboarding_status' => OnboardingStatus::Pending,
            'onboarding_template' => null,
            'onboarding_current_step' => 1,
            'onboarding_selected_roles' => null,
            'onboarding_completed_at' => null,
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Trial]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }
}
