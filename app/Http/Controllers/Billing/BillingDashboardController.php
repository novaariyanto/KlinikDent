<?php

namespace App\Http\Controllers\Billing;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Billing\Concerns\ScopesBillingBranch;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Billing\BillingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingDashboardController extends Controller
{
    use ScopesBillingBranch;

    public function index(Request $request, BillingService $billing): View
    {
        abort_unless($request->user()?->can('billing.view'), 403);

        $user = $request->user();
        $branchId = $this->restrictedBranchId($user);

        $openInvoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->count();

        $todayPaid = Payment::query()
            ->whereDate('paid_at', now()->toDateString())
            ->when($branchId, fn ($query) => $query->whereHas('invoice', fn ($invoice) => $invoice->where('branch_id', $branchId)))
            ->sum('amount');

        $todayInvoices = Invoice::query()
            ->whereDate('created_at', now()->toDateString())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->count();

        $shift = $billing->currentShift($user);

        return view('billing.dashboard', compact('openInvoices', 'todayPaid', 'todayInvoices', 'shift'));
    }
}
