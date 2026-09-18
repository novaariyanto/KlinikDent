<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Support\Finance\FinanceReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RevenueController extends Controller
{
    use ScopesFinanceBranch;

    public function daily(Request $request, FinanceReportService $reports): View
    {
        return $this->page($request, $reports, 'daily');
    }

    public function monthly(Request $request, FinanceReportService $reports): View
    {
        $from = $this->periodFrom($request, now()->startOfYear()->toDateString());
        $to = $this->periodTo($request, now()->endOfYear()->toDateString());

        return $this->page($request, $reports, 'monthly', $from, $to);
    }

    public function doctors(Request $request, FinanceReportService $reports): View
    {
        return $this->page($request, $reports, 'doctors');
    }

    protected function page(
        Request $request,
        FinanceReportService $reports,
        string $mode,
        ?string $from = null,
        ?string $to = null,
    ): View {
        abort_unless($request->user()?->can('revenue.view'), 403);

        $from ??= $this->periodFrom($request);
        $to ??= $this->periodTo($request);
        $branchId = $this->selectedBranchId($request->user(), $request->integer('branch_id') ?: null);
        $reports = $reports->forActor($request->user());

        $rows = match ($mode) {
            'monthly' => $reports->revenueByMonth($from, $to, $branchId),
            'doctors' => $reports->revenueByDoctor($from, $to, $branchId),
            default => $reports->revenueByDay($from, $to, $branchId),
        };

        $title = match ($mode) {
            'monthly' => 'Pendapatan Bulanan',
            'doctors' => 'Pendapatan per Dokter',
            default => 'Pendapatan Harian',
        };

        return view('finance.revenue', [
            'title' => $title,
            'mode' => $mode,
            'rows' => $rows,
            'total' => $reports->paymentSum($from, $to, $branchId),
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => $this->assignedBranches($request->user()),
        ]);
    }
}
