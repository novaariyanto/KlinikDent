<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Models\CashMutation;
use App\Models\Expense;
use App\Support\Finance\FinanceReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceReportController extends Controller
{
    use ScopesFinanceBranch;

    public function revenue(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('report.view'), 403);

        return redirect()->route('reports.revenue', $request->query());
    }

    public function expenses(Request $request, FinanceReportService $reports): View
    {
        abort_unless($request->user()?->can('report.view'), 403);

        [$from, $to, $branchId] = $this->filters($request);
        $reports = $reports->forActor($request->user());
        $rows = $reports->expensesByCategory($from, $to, $branchId);

        $expenseQuery = Expense::query()
            ->with(['category', 'branch'])
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to);
        $this->constrainBranch($expenseQuery, $request->user(), 'branch_id', $branchId);

        return view('finance.reports.expenses', [
            'title' => 'Laporan Pengeluaran',
            'rows' => $rows,
            'total' => $reports->expenseSum($from, $to, $branchId),
            'expenses' => $expenseQuery
                ->latest('expense_date')
                ->limit(50)
                ->get(),
            ...$this->filterViewData($request, $from, $to, $branchId),
        ]);
    }

    public function cashflow(Request $request): View
    {
        abort_unless($request->user()?->can('report.view'), 403);

        [$from, $to, $branchId] = $this->filters($request);

        $mutations = CashMutation::query()
            ->with('account.branch')
            ->whereDate('mutated_at', '>=', $from)
            ->whereDate('mutated_at', '<=', $to);
        $this->constrainRelatedBranch($mutations, $request->user(), 'account', 'branch_id', $branchId);
        $mutations = $mutations
            ->latest('mutated_at')
            ->paginate(30)
            ->withQueryString();

        $in = '0.00';
        $out = '0.00';
        $totals = CashMutation::query()
            ->whereDate('mutated_at', '>=', $from)
            ->whereDate('mutated_at', '<=', $to);
        $this->constrainRelatedBranch($totals, $request->user(), 'account', 'branch_id', $branchId);
        foreach ($totals->get() as $mutation) {
            if ($mutation->type->value === 'in') {
                $in = bcadd($in, (string) $mutation->amount, 2);
            } else {
                $out = bcadd($out, (string) $mutation->amount, 2);
            }
        }

        return view('finance.reports.cashflow', [
            'mutations' => $mutations,
            'in' => $in,
            'out' => $out,
            'net' => bcsub($in, $out, 2),
            ...$this->filterViewData($request, $from, $to, $branchId),
        ]);
    }

    public function receivables(Request $request, FinanceReportService $reports): View
    {
        abort_unless($request->user()?->can('report.view'), 403);

        [$from, $to, $branchId] = $this->filters($request);
        $reports = $reports->forActor($request->user());

        return view('finance.reports.receivables', [
            'total' => $reports->receivableTotal($branchId),
            ...$this->filterViewData($request, $from, $to, $branchId),
        ]);
    }

    public function profitLoss(Request $request, FinanceReportService $reports): View
    {
        abort_unless($request->user()?->can('report.view'), 403);

        [$from, $to, $branchId] = $this->filters($request);
        $pl = $reports->forActor($request->user())->profitLoss($from, $to, $branchId);

        return view('finance.reports.profit-loss', [
            'pl' => $pl,
            ...$this->filterViewData($request, $from, $to, $branchId),
        ]);
    }
}
