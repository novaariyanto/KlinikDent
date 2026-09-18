<?php

namespace Database\Seeders;

use App\Enums\CashAccountType;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\BillingService;
use App\Support\Finance\CashLedgerService;
use App\Support\Finance\FinanceException;
use App\Support\Finance\FinanceService;
use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $billing = app(BillingService::class);
        $finance = app(FinanceService::class);
        $ledger = app(CashLedgerService::class);

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($billing, $finance, $ledger) {
            $this->seedForTenant($tenant, $billing, $finance, $ledger);
        });
    }

    protected function seedForTenant(
        Tenant $tenant,
        BillingService $billing,
        FinanceService $finance,
        CashLedgerService $ledger,
    ): void {
        $actor = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->role(RoleName::Finance->value)
            ->first()
            ?? User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->role(RoleName::Owner->value)
                ->first()
            ?? User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->first();

        $branch = Branch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->first();

        if (! $actor || ! $branch) {
            return;
        }

        foreach (['Operasional', 'Utilitas', 'Gaji'] as $name) {
            ExpenseCategory::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
            );
        }

        $invoice = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('paid_amount', '0.00')
            ->orderBy('id')
            ->first();

        if ($invoice && bccomp((string) $invoice->total_amount, '0', 2) > 0) {
            $pay = bccomp((string) $invoice->total_amount, '100000', 2) > 0
                ? '100000.00'
                : (string) $invoice->total_amount;

            try {
                $billing->pay($invoice, $actor, $pay, PaymentMethod::Cash, 'Seed pembayaran keuangan');
            } catch (\Throwable) {
                //
            }
        }

        $category = ExpenseCategory::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Operasional')
            ->first();
        $account = $ledger->defaultAccount((int) $tenant->id, (int) $branch->id, CashAccountType::Cash);

        if ($category && bccomp((string) $account->fresh()->balance, '25000', 2) >= 0) {
            $exists = Expense::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('description', 'Biaya operasional seed')
                ->exists();

            if (! $exists) {
                try {
                    $finance->recordExpense([
                        'branch_id' => $branch->id,
                        'category_id' => $category->id,
                        'cash_account_id' => $account->id,
                        'amount' => '25000',
                        'description' => 'Biaya operasional seed',
                        'expense_date' => now()->toDateString(),
                    ], $actor);
                } catch (FinanceException) {
                    //
                }
            }
        }

        $supplier = Supplier::query()->where('tenant_id', $tenant->id)->orderBy('id')->first();
        $medicine = Medicine::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->first();

        if (! $supplier || ! $medicine) {
            return;
        }

        $po = PurchaseOrder::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'PO-'.now()->format('Y').'-PAY01'],
            [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Received,
                'order_date' => now()->subDays(3)->toDateString(),
                'received_at' => now()->subDay(),
                'created_by' => $actor->id,
            ]
        );

        if ($po->items()->doesntExist()) {
            $po->items()->create([
                'medicine_id' => $medicine->id,
                'quantity' => 10,
                'unit_price' => '1500.00',
            ]);
        }
    }
}
