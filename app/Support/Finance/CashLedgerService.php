<?php

namespace App\Support\Finance;

use App\Enums\CashAccountType;
use App\Enums\CashMutationType;
use App\Models\CashAccount;
use App\Models\CashMutation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CashLedgerService
{
    public function defaultAccount(int $tenantId, int $branchId, CashAccountType $type): CashAccount
    {
        $existing = CashAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('type', $type)
            ->where('is_default', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return CashAccount::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'type' => $type->value,
                'is_default' => true,
            ],
            [
                'name' => $type === CashAccountType::Cash ? 'Kas Tunai' : 'Bank Utama',
                'balance' => '0.00',
            ],
        );
    }

    public function record(
        CashAccount $account,
        CashMutationType $type,
        string $amount,
        Model $source,
        ?string $notes = null,
        mixed $mutatedAt = null,
    ): CashMutation {
        $amount = $this->normalizeAmount($amount);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new FinanceException('Nominal mutasi harus lebih dari 0.');
        }

        return DB::transaction(function () use ($account, $type, $amount, $source, $notes, $mutatedAt) {
            $sourceType = $source->getMorphClass();
            $sourceId = $source->getKey();

            $existing = CashMutation::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            /** @var CashAccount $locked */
            $locked = CashAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            $newBalance = $type === CashMutationType::Out
                ? bcsub((string) $locked->balance, $amount, 2)
                : bcadd((string) $locked->balance, $amount, 2);

            if (bccomp($newBalance, '0', 2) < 0) {
                throw new FinanceException('Saldo akun tidak mencukupi.');
            }

            $mutation = CashMutation::query()->create([
                'tenant_id' => $locked->tenant_id,
                'cash_account_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'notes' => $notes,
                'mutated_at' => $mutatedAt ?? now(),
            ]);

            $locked->update(['balance' => $newBalance]);

            return $mutation;
        });
    }

    public function normalizeAmount(string $amount): string
    {
        $normalized = str_replace(',', '.', trim($amount));

        if (! is_numeric($normalized)) {
            throw new FinanceException('Nominal tidak valid.');
        }

        return number_format((float) $normalized, 2, '.', '');
    }
}
