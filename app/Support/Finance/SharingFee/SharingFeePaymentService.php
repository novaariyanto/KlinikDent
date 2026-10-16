<?php

namespace App\Support\Finance\SharingFee;

use App\Enums\CashMutationType;
use App\Enums\PaymentMethod;
use App\Enums\SharingFeeSettlementStatus;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\SharingFeePayment;
use App\Models\SharingFeeSettlement;
use App\Models\User;
use App\Support\Finance\CashLedgerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SharingFeePaymentService
{
    public function __construct(
        protected CashLedgerService $ledger,
        protected SharingFeeCalculator $calculator,
        protected SharingFeeSettlementService $settlements,
    ) {}

    public function pay(
        SharingFeeSettlement $settlement,
        User $actor,
        string $amount,
        PaymentMethod $method,
        int $cashAccountId,
        string $paidOn,
        ?string $reference = null,
        ?string $notes = null,
    ): SharingFeePayment {
        $amount = $this->calculator->normalize($amount);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new SharingFeeException('Nominal pembayaran harus lebih dari 0.');
        }

        return DB::transaction(function () use ($settlement, $actor, $amount, $method, $cashAccountId, $paidOn, $reference, $notes) {
            /** @var SharingFeeSettlement $locked */
            $locked = SharingFeeSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();

            if ($locked->isDraft()) {
                throw new SharingFeeException('Settlement harus disetujui sebelum dibayar.');
            }

            $outstanding = $locked->outstandingAmount();

            if (bccomp($amount, $outstanding, 2) > 0) {
                throw new SharingFeeException('Nominal pembayaran melebihi sisa tagihan sharing fee.');
            }

            /** @var CashAccount $account */
            $account = CashAccount::query()->whereKey($cashAccountId)->lockForUpdate()->firstOrFail();

            if ((int) $account->tenant_id !== (int) $locked->tenant_id) {
                throw new SharingFeeException('Akun kas tidak valid untuk tenant ini.');
            }

            $branchId = (int) ($locked->branch_id ?: $account->branch_id);

            if ((int) $account->branch_id !== $branchId) {
                throw new SharingFeeException('Akun kas tidak sesuai cabang settlement.');
            }

            $category = $this->ensureCategory((int) $locked->tenant_id);

            $expense = Expense::query()->create([
                'tenant_id' => $locked->tenant_id,
                'branch_id' => $branchId,
                'category_id' => $category->id,
                'supplier_id' => null,
                'cash_account_id' => $account->id,
                'amount' => $amount,
                'description' => 'Pembayaran sharing fee dokter #'.$locked->id
                    .($reference ? ' ref '.$reference : ''),
                'expense_date' => $paidOn,
                'created_by' => $actor->id,
            ]);

            $payment = SharingFeePayment::query()->create([
                'tenant_id' => $locked->tenant_id,
                'settlement_id' => $locked->id,
                'expense_id' => $expense->id,
                'cash_account_id' => $account->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'paid_on' => $paidOn,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            $this->ledger->record(
                $account,
                CashMutationType::Out,
                $amount,
                $payment,
                'Sharing fee: settlement #'.$locked->id,
                Carbon::parse($paidOn)->setTimeFrom(now()),
            );

            $this->settlements->recalculateTotals($locked);
            $locked->refresh();

            if (bccomp($locked->outstandingAmount(), '0', 2) <= 0) {
                $locked->update([
                    'status' => SharingFeeSettlementStatus::Paid,
                    'paid_by' => $actor->id,
                    'paid_at' => now(),
                ]);
            }

            activity_audit('paid', $locked, [], 'Pembayaran sharing fee dicatat.', 'sharing_fee', [
                'payment_id' => $payment->id,
                'amount' => $amount,
                'expense_id' => $expense->id,
            ], $actor);

            return $payment->fresh(['expense', 'cashAccount']);
        });
    }

    protected function ensureCategory(int $tenantId): ExpenseCategory
    {
        return ExpenseCategory::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => 'Sharing Fee Dokter',
            ],
        );
    }
}
