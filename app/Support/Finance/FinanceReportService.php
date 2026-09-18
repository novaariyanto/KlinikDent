<?php

namespace App\Support\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinanceReportService
{
    protected ?User $actor = null;

    public function forActor(?User $user): static
    {
        $instance = clone $this;
        $instance->actor = $user;

        return $instance;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    protected function constrain(Builder $query, ?int $branchId, string $column = 'branch_id'): Builder
    {
        if ($this->actor) {
            return $this->actor->applyBranchLimit($query, $column, $branchId);
        }

        return $branchId ? $query->where($column, $branchId) : $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?int $branchId, string $from, string $to): array
    {
        $today = now()->toDateString();

        return [
            'today_revenue' => $this->paymentSum($today, $today, $branchId),
            'period_revenue' => $this->paymentSum($from, $to, $branchId),
            'period_expenses' => $this->expenseSum($from, $to, $branchId),
            'cash_balance' => $this->cashBalance($branchId),
            'receivables' => $this->receivableTotal($branchId),
            'payables' => $this->payableTotal($branchId),
            'profit_loss' => $this->profitLoss($from, $to, $branchId),
        ];
    }

    public function paymentSum(string $from, string $to, ?int $branchId): string
    {
        $query = Payment::query()
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->whereHas('invoice', function ($invoice) use ($branchId) {
                $this->constrain($invoice, $branchId);
            });

        return $this->money((string) $query->sum('amount'));
    }

    public function expenseSum(string $from, string $to, ?int $branchId): string
    {
        $query = Expense::query()
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to);
        $this->constrain($query, $branchId);

        return $this->money((string) $query->sum('amount'));
    }

    public function cashBalance(?int $branchId): string
    {
        $query = CashAccount::query();
        $this->constrain($query, $branchId);

        return $this->money((string) $query->sum('balance'));
    }

    public function receivableTotal(?int $branchId): string
    {
        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]);
        $this->constrain($invoices, $branchId);
        $invoices = $invoices->get();

        return $invoices->reduce(
            fn (string $carry, Invoice $invoice) => bcadd($carry, $invoice->remainingAmount(), 2),
            '0.00'
        );
    }

    public function payableTotal(?int $branchId): string
    {
        $orders = $this->payableQuery($branchId)->with('items')->get();

        return $orders->reduce(
            fn (string $carry, PurchaseOrder $order) => bcadd($carry, $order->totalAmount(), 2),
            '0.00'
        );
    }

    /**
     * @return array{revenue: string, expenses: string, hpp: string, profit: string}
     */
    public function profitLoss(string $from, string $to, ?int $branchId): array
    {
        $revenue = $this->paymentSum($from, $to, $branchId);
        $expenses = $this->expenseSum($from, $to, $branchId);
        $hpp = $this->hppSum($from, $to, $branchId);
        $profit = bcsub(bcsub($revenue, $expenses, 2), $hpp, 2);

        return compact('revenue', 'expenses', 'hpp', 'profit');
    }

    public function hppSum(string $from, string $to, ?int $branchId): string
    {
        $rows = StockMovement::query()
            ->select('stock_movements.quantity', 'medicines.base_price')
            ->join('medicine_stocks', 'medicine_stocks.id', '=', 'stock_movements.medicine_stock_id')
            ->join('medicines', 'medicines.id', '=', 'medicine_stocks.medicine_id')
            ->where('stock_movements.type', StockMovementType::Out)
            ->whereDate('stock_movements.created_at', '>=', $from)
            ->whereDate('stock_movements.created_at', '<=', $to);
        $this->constrain($rows, $branchId, 'medicine_stocks.branch_id');
        $rows = $rows->get();

        return $rows->reduce(
            fn (string $carry, $row) => bcadd(
                $carry,
                bcmul((string) $row->quantity, (string) ($row->base_price ?? '0'), 2),
                2
            ),
            '0.00'
        );
    }

    /**
     * @return Collection<string, string>
     */
    public function revenueByDay(string $from, string $to, ?int $branchId): Collection
    {
        return $this->groupPayments($from, $to, $branchId, 'date');
    }

    /**
     * @return Collection<string, string>
     */
    public function revenueByMonth(string $from, string $to, ?int $branchId): Collection
    {
        return $this->groupPayments($from, $to, $branchId, 'month');
    }

    /**
     * @return Collection<int, array{doctor: string, amount: string}>
     */
    public function revenueByDoctor(string $from, string $to, ?int $branchId): Collection
    {
        $payments = Payment::query()
            ->with(['invoice.visit.doctor'])
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->whereHas('invoice', function ($invoice) use ($branchId) {
                $this->constrain($invoice, $branchId);
            })
            ->get();

        return $payments
            ->groupBy(fn (Payment $payment) => $payment->invoice?->visit?->doctor_id ?: 0)
            ->map(function (Collection $rows) {
                $doctor = $rows->first()?->invoice?->visit?->doctor?->name ?: 'Tanpa dokter';
                $amount = $rows->reduce(
                    fn (string $carry, Payment $payment) => bcadd($carry, (string) $payment->amount, 2),
                    '0.00'
                );

                return ['doctor' => $doctor, 'amount' => $amount];
            })
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * @return Collection<int, array{method: string, amount: string}>
     */
    public function revenueByMethod(string $from, string $to, ?int $branchId): Collection
    {
        $payments = Payment::query()
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->whereHas('invoice', function ($invoice) use ($branchId) {
                $this->constrain($invoice, $branchId);
            })
            ->get();

        return $payments
            ->groupBy(fn (Payment $payment) => $payment->method->value)
            ->map(function (Collection $rows) {
                $method = $rows->first()?->method;

                return [
                    'method' => $method?->label() ?? '-',
                    'amount' => $rows->reduce(
                        fn (string $carry, Payment $payment) => bcadd($carry, (string) $payment->amount, 2),
                        '0.00'
                    ),
                ];
            })
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * @return Collection<int, array{category: string, amount: string}>
     */
    public function expensesByCategory(string $from, string $to, ?int $branchId): Collection
    {
        $expenses = Expense::query()
            ->with('category')
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to);
        $this->constrain($expenses, $branchId);
        $expenses = $expenses->get();

        return $expenses
            ->groupBy('category_id')
            ->map(function (Collection $rows) {
                return [
                    'category' => $rows->first()?->category?->name ?: 'Tanpa kategori',
                    'amount' => $rows->reduce(
                        fn (string $carry, Expense $expense) => bcadd($carry, (string) $expense->amount, 2),
                        '0.00'
                    ),
                ];
            })
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<PurchaseOrder>
     */
    public function payableQuery(?int $branchId)
    {
        $query = PurchaseOrder::query()
            ->where('status', PurchaseOrderStatus::Received)
            ->whereNull('paid_at');
        $this->constrain($query, $branchId);

        return $query;
    }

    public function money(string $value): string
    {
        if ($value === '' || ! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @return Collection<string, string>
     */
    protected function groupPayments(string $from, string $to, ?int $branchId, string $grain): Collection
    {
        $payments = Payment::query()
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->whereHas('invoice', function ($invoice) use ($branchId) {
                $this->constrain($invoice, $branchId);
            })
            ->get();

        return $payments
            ->groupBy(function (Payment $payment) use ($grain) {
                $at = $payment->paid_at;

                return $grain === 'month'
                    ? ($at?->format('Y-m') ?? '-')
                    : ($at?->toDateString() ?? '-');
            })
            ->map(fn (Collection $rows) => $rows->reduce(
                fn (string $carry, Payment $payment) => bcadd($carry, (string) $payment->amount, 2),
                '0.00'
            ))
            ->sortKeys();
    }
}
