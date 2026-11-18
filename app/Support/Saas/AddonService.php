<?php

namespace App\Support\Saas;

use App\Enums\AddonBillingInterval;
use App\Enums\AddonCode;
use App\Enums\AddonStatus;
use App\Models\SaasAddon;
use App\Models\SaasAddonSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppSetting;
use Illuminate\Support\Facades\DB;

class AddonService
{
    /**
     * Pastikan katalog Add-on WhatsApp ada (hanya buat jika belum ada).
     */
    public function ensureWhatsAppCatalog(): SaasAddon
    {
        return SaasAddon::query()->firstOrCreate(
            ['code' => AddonCode::WhatsApp->value],
            [
                'name' => 'WhatsApp Integration',
                'description' => 'Pengingat jadwal, promosi, dan otomasi WhatsApp untuk klinik.',
                'benefits' => [
                    'Pengingat kunjungan otomatis',
                    'Promosi ke pasien consent',
                    'Template & riwayat pesan',
                ],
                'price' => 0,
                'interval' => AddonBillingInterval::Monthly,
                'trial_days' => 0,
                'terms' => 'Dengan mengaktifkan, klinik menyetujui ketentuan penggunaan WhatsApp Integration.',
                'is_active' => true,
            ]
        );
    }

    public function ensureSubscription(Tenant $tenant, SaasAddon $addon): SaasAddonSubscription
    {
        return SaasAddonSubscription::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'addon_id' => $addon->id,
            ],
            [
                'status' => AddonStatus::Inactive,
            ]
        );
    }

    public function activate(SaasAddonSubscription $subscription, ?User $actor = null, bool $trial = false): SaasAddonSubscription
    {
        return DB::transaction(function () use ($subscription, $actor, $trial) {
            $subscription = $subscription->fresh(['addon']) ?? $subscription;
            $addon = $subscription->addon;
            $now = now();

            $trialDays = max(0, (int) ($addon?->trial_days ?? 0));
            $useTrial = $trial && $trialDays > 0;

            $endsAt = null;
            $trialEndsAt = null;

            if ($useTrial) {
                $endsAt = $now->copy()->addDays($trialDays);
                $trialEndsAt = $endsAt;
            } elseif ($addon?->interval instanceof AddonBillingInterval) {
                $months = $addon->interval->months();
                if ($months !== null) {
                    $endsAt = $now->copy()->addMonths($months);
                }
            }

            $subscription->update([
                'status' => AddonStatus::Active,
                'starts_at' => $subscription->starts_at ?? $now,
                'ends_at' => $endsAt,
                'trial_ends_at' => $trialEndsAt,
                'activated_at' => $now,
                'suspended_at' => null,
                'activated_by' => $actor?->id,
            ]);

            return $subscription->fresh(['addon']) ?? $subscription;
        });
    }

    /**
     * Aktivasi tanpa expiry (migrasi / superadmin).
     */
    public function activateUnlimited(SaasAddonSubscription $subscription, ?User $actor = null): SaasAddonSubscription
    {
        return DB::transaction(function () use ($subscription, $actor) {
            $now = now();

            $subscription->update([
                'status' => AddonStatus::Active,
                'starts_at' => $subscription->starts_at ?? $now,
                'ends_at' => null,
                'trial_ends_at' => null,
                'activated_at' => $now,
                'suspended_at' => null,
                'activated_by' => $actor?->id,
            ]);

            return $subscription->fresh(['addon']) ?? $subscription;
        });
    }

    public function suspend(SaasAddonSubscription $subscription): SaasAddonSubscription
    {
        $subscription->update([
            'status' => AddonStatus::Suspended,
            'suspended_at' => now(),
        ]);

        return $subscription->fresh() ?? $subscription;
    }

    public function revoke(SaasAddonSubscription $subscription): SaasAddonSubscription
    {
        $subscription->update([
            'status' => AddonStatus::Inactive,
            'suspended_at' => null,
        ]);

        return $subscription->fresh() ?? $subscription;
    }

    public function markExpired(SaasAddonSubscription $subscription): SaasAddonSubscription
    {
        $subscription->update([
            'status' => AddonStatus::Expired,
        ]);

        return $subscription->fresh() ?? $subscription;
    }

    public function expireOverdue(): int
    {
        $count = 0;

        SaasAddonSubscription::query()
            ->where('status', AddonStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->each(function (SaasAddonSubscription $subscription) use (&$count) {
                $this->markExpired($subscription);
                $count++;
            });

        return $count;
    }

    public function migrateFromWhatsAppSettings(): int
    {
        $addon = $this->ensureWhatsAppCatalog();

        $tenantIds = WhatsAppSetting::query()
            ->withoutGlobalScopes()
            ->distinct()
            ->pluck('tenant_id')
            ->filter()
            ->unique()
            ->values();

        $count = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);

            if (! $tenant) {
                continue;
            }

            $subscription = $this->ensureSubscription($tenant, $addon);

            if ($subscription->status !== AddonStatus::Active) {
                $this->activateUnlimited($subscription);
                $count++;
            }
        }

        return $count;
    }

    public function extend(SaasAddonSubscription $subscription, ?\DateTimeInterface $endsAt): SaasAddonSubscription
    {
        $subscription->update([
            'ends_at' => $endsAt,
        ]);

        return $subscription->fresh() ?? $subscription;
    }
}
