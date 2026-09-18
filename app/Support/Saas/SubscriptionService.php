<?php

namespace App\Support\Saas;

use App\Enums\SaasInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\SaasInvoice;
use App\Models\SaasPackage;
use App\Models\SaasSubscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function start(Tenant $tenant, SaasPackage $package, bool $trial = false): SaasSubscription
    {
        return DB::transaction(function () use ($tenant, $package, $trial) {
            $now = now();
            $isTrial = $trial || $package->isFree();
            $endsAt = $isTrial
                ? $now->copy()->addDays(max(1, (int) $package->trial_days))
                : $now->copy()->addMonths($package->interval->months());

            $subscription = SaasSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'package_id' => $package->id,
                'status' => $isTrial ? SubscriptionStatus::Trial : SubscriptionStatus::Active,
                'starts_at' => $now,
                'ends_at' => $endsAt,
                'trial_ends_at' => $isTrial ? $endsAt : null,
            ]);

            $tenant->update([
                'plan_id' => $package->id,
                'status' => $isTrial ? TenantStatus::Trial : TenantStatus::Active,
            ]);

            if (! $isTrial) {
                $this->issueInvoice($subscription, $package->price);
            }

            return $subscription;
        });
    }

    public function issueInvoice(SaasSubscription $subscription, string|float $amount): SaasInvoice
    {
        $issued = now();

        return SaasInvoice::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'number' => $this->nextNumber(),
            'amount' => $amount,
            'status' => SaasInvoiceStatus::Unpaid,
            'issued_at' => $issued,
            'due_at' => $issued->copy()->addDays(7),
        ]);
    }

    public function markPaid(SaasInvoice $invoice): SaasInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => SaasInvoiceStatus::Paid,
                'paid_at' => now(),
            ]);

            $subscription = $invoice->subscription()->with('package')->first();
            if (! $subscription) {
                return $invoice->fresh();
            }

            $package = $subscription->package;
            $base = $subscription->ends_at?->isFuture() ? $subscription->ends_at : now();

            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'ends_at' => $base->copy()->addMonths($package->interval->months()),
                'cancelled_at' => null,
            ]);

            $subscription->tenant?->update([
                'plan_id' => $package->id,
                'status' => TenantStatus::Active,
            ]);

            return $invoice->fresh();
        });
    }

    public function expireOverdue(): int
    {
        $expired = 0;

        SaasSubscription::query()
            ->whereIn('status', [SubscriptionStatus::Trial->value, SubscriptionStatus::Active->value])
            ->where('ends_at', '<', now())
            ->with('tenant')
            ->each(function (SaasSubscription $subscription) use (&$expired) {
                $subscription->update(['status' => SubscriptionStatus::Expired]);
                $subscription->tenant?->update(['status' => TenantStatus::Suspended]);
                $expired++;
            });

        return $expired;
    }

    protected function nextNumber(): string
    {
        $prefix = 'SI-'.now()->format('Ym').'-';
        $last = SaasInvoice::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
