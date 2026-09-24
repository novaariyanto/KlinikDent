<?php

namespace App\Observers;

use App\Enums\CashAccountType;
use App\Enums\CashMutationType;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\Finance\CashLedgerService;

class PaymentObserver
{
    public function __construct(protected CashLedgerService $ledger)
    {
    }

    public function created(Payment $payment): void
    {
        $payment->loadMissing('invoice');
        $invoice = $payment->invoice;

        if (! $invoice) {
            return;
        }

        $accountType = $payment->method === PaymentMethod::Cash
            ? CashAccountType::Cash
            : CashAccountType::Bank;

        $account = $this->ledger->defaultAccount(
            (int) $invoice->tenant_id,
            (int) $invoice->branch_id,
            $accountType,
        );

        $this->ledger->record(
            $account,
            $payment->isRefund() ? CashMutationType::Out : CashMutationType::In,
            (string) $payment->amount,
            $payment,
            ($payment->isRefund() ? 'Pengembalian ' : 'Pembayaran ').$invoice->number.' ('.$payment->method->label().')',
            $payment->paid_at,
        );
    }
}
