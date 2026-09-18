<?php

namespace App\Support\Finance;

use App\Enums\CashMutationType;
use App\Enums\PurchaseOrderStatus;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function __construct(protected CashLedgerService $ledger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordExpense(array $data, User $user): Expense
    {
        $amount = $this->ledger->normalizeAmount((string) $data['amount']);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new FinanceException('Nominal pengeluaran harus lebih dari 0.');
        }

        return DB::transaction(function () use ($data, $user, $amount) {
            /** @var CashAccount $account */
            $account = CashAccount::query()->whereKey($data['cash_account_id'])->lockForUpdate()->firstOrFail();

            if ((int) $account->branch_id !== (int) $data['branch_id']) {
                throw new FinanceException('Akun kas tidak sesuai cabang pengeluaran.');
            }

            $expense = Expense::query()->create([
                'tenant_id' => $user->tenant_id,
                'branch_id' => $data['branch_id'],
                'category_id' => $data['category_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'cash_account_id' => $account->id,
                'amount' => $amount,
                'description' => $data['description'],
                'expense_date' => $data['expense_date'],
                'created_by' => $user->id,
            ]);

            $this->ledger->record(
                $account,
                CashMutationType::Out,
                $amount,
                $expense,
                'Pengeluaran: '.$expense->description,
                $expense->expense_date?->copy()->setTimeFrom(now()),
            );

            activity_audit('created', $expense, [], 'Pengeluaran '.$amount.' dicatat.', 'finance', [
                'amount' => $amount,
            ], $user);

            return $expense->fresh(['category', 'cashAccount', 'branch']);
        });
    }

    public function payPurchaseOrder(PurchaseOrder $order, User $user, CashAccount $account): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $user, $account) {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->with('items')->firstOrFail();

            if ($locked->status !== PurchaseOrderStatus::Received || $locked->paid_at !== null) {
                throw new FinanceException('Hanya PO yang sudah diterima dan belum dibayar yang dapat dilunasi.');
            }

            /** @var CashAccount $lockedAccount */
            $lockedAccount = CashAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedAccount->branch_id !== (int) $locked->branch_id) {
                throw new FinanceException('Akun kas tidak sesuai cabang purchase order.');
            }

            $amount = $locked->totalAmount();

            if (bccomp($amount, '0', 2) <= 0) {
                throw new FinanceException('Nilai purchase order tidak valid.');
            }

            $this->ledger->record(
                $lockedAccount,
                CashMutationType::Out,
                $amount,
                $locked,
                'Pelunasan PO '.$locked->number,
            );

            $before = activity_snapshot($locked);

            $locked->update([
                'paid_at' => now(),
                'paid_from_account_id' => $lockedAccount->id,
            ]);

            activity_audit('paid', $locked, $before, 'PO '.$locked->number.' dilunasi.', 'finance', [
                'amount' => $amount,
            ], $user);

            return $locked->fresh(['items', 'supplier', 'paidFromAccount']);
        });
    }
}
