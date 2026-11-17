<?php

namespace App\Support\Saas;

use App\Enums\SaasInvoiceStatus;
use App\Models\SaasAddonInvoice;
use App\Models\SaasAddonSubscription;
use Illuminate\Support\Facades\DB;

class AddonBillingService
{
    public function __construct(protected AddonService $addons) {}

    public function issueInvoice(SaasAddonSubscription $subscription, string|float $amount): SaasAddonInvoice
    {
        $issued = now();

        return SaasAddonInvoice::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'addon_subscription_id' => $subscription->id,
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
    public function markPaid(SaasAddonInvoice $invoice, array $payment = []): SaasAddonInvoice
    {
        return DB::transaction(function () use ($invoice, $payment) {
            $invoice = $invoice->fresh(['addonSubscription.addon']) ?? $invoice;

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

            $subscription = $invoice->addonSubscription;

            if ($subscription) {
                $this->addons->activate($subscription, null, false);
            }

            return $invoice->fresh(['addonSubscription.addon']) ?? $invoice;
        });
    }

    protected function nextNumber(): string
    {
        $prefix = 'ADDINV-'.now()->format('Ym').'-';
        $latest = SaasAddonInvoice::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
