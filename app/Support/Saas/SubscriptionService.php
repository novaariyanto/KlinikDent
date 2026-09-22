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

    /**
     * @param  array{channel?: string, transaction_id?: ?string, status?: ?string, payload?: mixed}  $payment
     */
    public function markPaid(SaasInvoice $invoice, array $payment = []): SaasInvoice
    {
        return DB::transaction(function () use ($invoice, $payment) {
            $invoice = $invoice->fresh() ?? $invoice;

            if ($invoice->status === SaasInvoiceStatus::Paid) {
                return $invoice;
            }

            $invoice->update(array_filter([
                'status' => SaasInvoiceStatus::Paid,
                'paid_at' => now(),
                'payment_channel' => $payment['channel'] ?? $invoice->payment_channel ?? 'manual',
                'midtrans_transaction_id' => $payment['transaction_id'] ?? $invoice->midtrans_transaction_id,
                'midtrans_status' => $payment['status'] ?? $invoice->midtrans_status,
                'payment_payload' => $payment['payload'] ?? $invoice->payment_payload,
            ], fn ($value) => $value !== null));

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

    /**
     * Owner memilih atau memperpanjang paket klinik sendiri.
     *
     * @return array{subscription: SaasSubscription, invoice: ?SaasInvoice, message: string}
     */
    public function subscribeForOwner(Tenant $tenant, SaasPackage $package): array
    {
        return DB::transaction(function () use ($tenant, $package) {
            if ($package->isFree()) {
                $this->cancelLiveSubscriptions($tenant);
                $subscription = $this->start($tenant, $package, true);

                return [
                    'subscription' => $subscription,
                    'invoice' => null,
                    'message' => 'Paket gratis diaktifkan.',
                ];
            }

            $current = $tenant->currentSubscription();
            $sameLivePackage = $current
                && (int) $current->package_id === (int) $package->id
                && $current->status->isLive();

            if ($sameLivePackage) {
                $unpaid = $current->invoices()
                    ->where('status', SaasInvoiceStatus::Unpaid)
                    ->latest('id')
                    ->first();

                if ($unpaid) {
                    return [
                        'subscription' => $current,
                        'invoice' => $unpaid,
                        'message' => 'Tagihan paket ini sudah ada. Silakan bayar.',
                    ];
                }

                $invoice = $this->issueInvoice($current, $package->price);

                return [
                    'subscription' => $current,
                    'invoice' => $invoice,
                    'message' => 'Tagihan perpanjangan dibuat. Silakan bayar.',
                ];
            }

            $keepSuspended = $tenant->status === TenantStatus::Suspended;
            $this->cancelLiveSubscriptions($tenant);
            $this->voidUnpaidInvoices($tenant);

            $now = now();
            $subscription = SaasSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'package_id' => $package->id,
                'status' => $keepSuspended ? SubscriptionStatus::Expired : SubscriptionStatus::Active,
                'starts_at' => $now,
                'ends_at' => $now->copy()->addMonths($package->interval->months()),
                'trial_ends_at' => null,
            ]);

            $tenant->update([
                'plan_id' => $package->id,
            ]);

            $invoice = $this->issueInvoice($subscription, $package->price);

            return [
                'subscription' => $subscription,
                'invoice' => $invoice,
                'message' => 'Paket dipilih. Lanjutkan pembayaran tagihan.',
            ];
        });
    }

    public function payForTenant(Tenant $tenant, SaasInvoice $invoice): SaasInvoice
    {
        abort_unless((int) $invoice->tenant_id === (int) $tenant->id, 403);

        if ($invoice->status !== SaasInvoiceStatus::Unpaid) {
            abort(422, 'Invoice ini tidak dapat dibayar.');
        }

        return $this->markPaid($invoice);
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

    protected function cancelLiveSubscriptions(Tenant $tenant): void
    {
        SaasSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [SubscriptionStatus::Trial, SubscriptionStatus::Active])
            ->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
    }

    protected function voidUnpaidInvoices(Tenant $tenant): void
    {
        SaasInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', SaasInvoiceStatus::Unpaid)
            ->update(['status' => SaasInvoiceStatus::Void]);
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
