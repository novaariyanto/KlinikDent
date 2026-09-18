<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Support\Finance\FinanceReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceDashboardController extends Controller
{
    use ScopesFinanceBranch;

    public function index(Request $request, FinanceReportService $reports): View
    {
        abort_unless($request->user()?->can('finance.view'), 403);

        $from = $this->periodFrom($request);
        $to = $this->periodTo($request);
        $branchId = $this->selectedBranchId($request->user(), $request->integer('branch_id') ?: null);
        $metrics = $reports->forActor($request->user())->dashboard($branchId, $from, $to);

        return view('finance.dashboard', [
            'metrics' => $metrics,
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => $this->assignedBranches($request->user()),
        ]);
    }
}
