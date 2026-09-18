<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Http\Requests\Finance\PayPayableRequest;
use App\Models\CashAccount;
use App\Models\PurchaseOrder;
use App\Support\Finance\FinanceException;
use App\Support\Finance\FinanceReportService;
use App\Support\Finance\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayableController extends Controller
{
    use ScopesFinanceBranch;

    public function __construct(
        protected FinanceService $finance,
        protected FinanceReportService $reports,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('payable.view'), 403);

        $branchId = $this->selectedBranchId($request->user(), $request->integer('branch_id') ?: null);
        $orders = $this->reports->forActor($request->user())->payableQuery($branchId)
            ->with(['supplier', 'branch', 'items'])
            ->orderBy('received_at')
            ->get();

        $accounts = CashAccount::query();
        $this->constrainBranch($accounts, $request->user(), 'branch_id', $branchId);
        $accounts = $accounts->orderBy('name')->get();

        return view('finance.payables', [
            'orders' => $orders,
            'accounts' => $accounts,
            'total' => $orders->reduce(
                fn (string $carry, PurchaseOrder $order) => bcadd($carry, $order->totalAmount(), 2),
                '0.00'
            ),
        ]);
    }

    public function pay(PayPayableRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $account = CashAccount::query()->findOrFail($request->validated('cash_account_id'));

        try {
            $this->finance->payPurchaseOrder($purchaseOrder, $request->user(), $account);
        } catch (FinanceException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('finance.payables')->with('success', 'Purchase order '.$purchaseOrder->number.' dilunasi.');
    }
}
