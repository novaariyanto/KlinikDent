<?php

namespace App\Support\Saas;

use App\Enums\OnboardingStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\SaasPackage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClinicProvisioningService
{
    public function __construct(protected SubscriptionService $subscriptions) {}

    /**
     * @return array{tenant: Tenant, owner: User}
     */
    public function provision(
        string $name,
        string $subdomain,
        string $ownerName,
        string $ownerEmail,
        string $ownerPassword,
        TenantStatus $status = TenantStatus::Trial,
        ?int $packageId = null,
        bool $startTrial = true,
    ): array {
        return DB::transaction(function () use (
            $name,
            $subdomain,
            $ownerName,
            $ownerEmail,
            $ownerPassword,
            $status,
            $packageId,
            $startTrial,
        ) {
            $package = $packageId
                ? SaasPackage::query()->where('is_active', true)->find($packageId)
                : null;

            $tenant = Tenant::query()->create([
                'name' => $name,
                'subdomain' => $subdomain,
                'status' => $status,
                'plan_id' => $package?->id,
                'onboarding_status' => OnboardingStatus::Pending,
                'onboarding_current_step' => 1,
                'onboarding_completed_at' => null,
            ]);

            if ($package) {
                $this->subscriptions->start(
                    $tenant,
                    $package,
                    $startTrial || $status === TenantStatus::Trial || $package->isFree()
                );
            }

            $owner = User::query()->create([
                'name' => $ownerName,
                'email' => $ownerEmail,
                'password' => $ownerPassword,
                'status' => UserStatus::Active,
                'tenant_id' => $tenant->id,
            ]);
            $owner->syncRoles([RoleName::Owner->value]);

            return [
                'tenant' => $tenant->fresh() ?? $tenant,
                'owner' => $owner,
            ];
        });
    }

    public function defaultPublicPackage(): ?SaasPackage
    {
        return SaasPackage::query()->where('is_active', true)->where('slug', 'trial')->first()
            ?? SaasPackage::query()->where('is_active', true)->where('price', '<=', 0)->orderBy('id')->first();
    }

    /**
     * @return Collection<int, SaasPackage>
     */
    public function publicPackages()
    {
        return SaasPackage::query()->where('is_active', true)->orderBy('price')->orderBy('name')->get();
    }
}
