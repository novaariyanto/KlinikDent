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
        $openInvoices = $user->applyBranchLimit(
            Invoice::query()->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
        )->count();

        $todayPaid = Payment::query()
            ->whereDate('paid_at', now()->toDateString())
            ->whereHas('invoice', fn ($invoice) => $user->applyBranchLimit($invoice))
            ->sum('amount');

        $todayInvoices = $user->applyBranchLimit(
            Invoice::query()->whereDate('created_at', now()->toDateString())
        )->count();

        $shift = $billing->currentShift($user);

        return view('billing.dashboard', compact('openInvoices', 'todayPaid', 'todayInvoices', 'shift'));
    }
}
