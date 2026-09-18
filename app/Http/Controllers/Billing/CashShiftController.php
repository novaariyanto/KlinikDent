<?php

namespace App\Http\Controllers\Billing;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Billing\Concerns\ScopesBillingBranch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CloseShiftRequest;
use App\Http\Requests\Billing\OpenShiftRequest;
use App\Models\CashierShift;
use App\Support\Billing\BillingException;
use App\Support\Billing\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashShiftController extends Controller
{
    use ScopesBillingBranch;

    public function __construct(protected BillingService $billing) {}

    public function openForm(Request $request): View
    {
        $this->authorize('create', CashierShift::class);

        $user = $request->user();
        $current = $this->billing->currentShift($user);

        $branches = $this->assignedBranches($user)
            ->pluck('name', 'id')
            ->all();

        return view('billing.shifts.open', [
            'branches' => $branches,
            'current' => $current,
            'defaultBranchId' => $user?->branch_id ?: array_key_first($branches),
        ]);
    }

    public function open(OpenShiftRequest $request): RedirectResponse
    {
        try {
            $this->billing->openShift(
                $request->user(),
                (int) $request->validated('branch_id'),
                (string) $request->validated('opening_balance'),
            );
        } catch (BillingException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('cashier.shifts.transactions')->with('success', 'Shift kasir dibuka.');
    }

    public function transactions(Request $request): View
    {
        abort_unless($request->user()?->can('cash_shift.view'), 403);

        $shift = $this->billing->currentShift($request->user())
            ?? CashierShift::query()
                ->where('cashier_id', $request->user()->id)
                ->latest('id')
                ->first();

        $payments = $shift
            ? $shift->payments()->with(['invoice.patient'])->latest('id')->paginate(20)
            : null;

        $cashIn = $shift
            ? (string) $shift->payments()->where('method', PaymentMethod::Cash)->sum('amount')
            : '0.00';

        return view('billing.shifts.transactions', compact('shift', 'payments', 'cashIn'));
    }

    public function closeForm(Request $request): View
    {
        abort_unless($request->user()?->can('cash_shift.manage'), 403);

        $shift = $this->billing->currentShift($request->user());
        abort_unless($shift, 422, 'Tidak ada shift terbuka.');

        $this->authorize('update', $shift);

        $cashIn = (string) $shift->payments()->where('method', PaymentMethod::Cash)->sum('amount');
        $system = bcadd((string) $shift->opening_balance, $cashIn, 2);

        return view('billing.shifts.close', compact('shift', 'cashIn', 'system'));
    }

    public function close(CloseShiftRequest $request): RedirectResponse
    {
        $shift = $this->billing->currentShift($request->user());
        abort_unless($shift, 422, 'Tidak ada shift terbuka.');
        $this->authorize('update', $shift);

        try {
            $closed = $this->billing->closeShift(
                $shift,
                $request->user(),
                (string) $request->validated('closing_balance'),
                $request->validated('close_notes'),
            );
        } catch (BillingException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $message = 'Shift ditutup. Selisih '.$closed->variance.'.';

        return redirect()->route('cashier.reports')->with('success', $message);
    }

    public function reports(Request $request): View
    {
        abort_unless($request->user()?->can('report.view') || $request->user()?->can('cash_shift.view'), 403);

        $query = CashierShift::query()->with(['cashier', 'branch']);
        $this->constrainBranch($query, $request->user());

        if (! $request->user()?->can('branch.manage')) {
            $query->where('cashier_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $shifts = $query->latest('id')->paginate(20)->withQueryString();

        return view('billing.shifts.reports', [
            'shifts' => $shifts,
            'statuses' => [
                CashShiftStatus::Open->value => CashShiftStatus::Open->label(),
                CashShiftStatus::Closed->value => CashShiftStatus::Closed->label(),
            ],
        ]);
    }
}
