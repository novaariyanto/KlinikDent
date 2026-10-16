<?php

namespace App\Http\Controllers\Finance;

use App\Enums\PaymentMethod;
use App\Enums\SharingFeePeriodType;
use App\Enums\SharingFeeSettlementStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Http\Requests\Finance\SharingFee\AdjustSharingFeeSettlementRequest;
use App\Http\Requests\Finance\SharingFee\GenerateSharingFeeSettlementRequest;
use App\Http\Requests\Finance\SharingFee\PaySharingFeeSettlementRequest;
use App\Models\CashAccount;
use App\Models\SharingFeeSettlement;
use App\Models\User;
use App\Support\Finance\SharingFee\SharingFeeException;
use App\Support\Finance\SharingFee\SharingFeePaymentService;
use App\Support\Finance\SharingFee\SharingFeeSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SharingFeeSettlementController extends Controller
{
    use ScopesFinanceBranch;

    public function __construct(
        protected SharingFeeSettlementService $settlements,
        protected SharingFeePaymentService $payments,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SharingFeeSettlement::class);

        $query = SharingFeeSettlement::query()->with(['doctor', 'branch']);
        $this->constrainBranch($query, $request->user());

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('doctor_user_id')) {
            $query->where('doctor_user_id', $request->integer('doctor_user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('period_start', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('period_end', '<=', $request->date('date_to'));
        }

        return view('finance.sharing-fees.settlements.index', [
            'settlements' => $query->latest('id')->paginate(20)->withQueryString(),
            'statuses' => SharingFeeSettlementStatus::options(),
            'periodTypes' => SharingFeePeriodType::options(),
            'doctors' => $this->doctorOptions($request),
            'branches' => $this->assignedBranches($request->user()),
        ]);
    }

    public function store(GenerateSharingFeeSettlementRequest $request): RedirectResponse
    {
        try {
            $result = $this->settlements->generate(
                $request->user(),
                SharingFeePeriodType::from($request->string('period_type')->toString()),
                $request->date('period_start')->toDateString(),
                $request->date('period_end')->toDateString(),
                $request->filled('branch_id') ? $request->integer('branch_id') : null,
                $request->filled('doctor_user_id') ? $request->integer('doctor_user_id') : null,
            );
        } catch (SharingFeeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $count = $result['settlements']->count();
        $excluded = count($result['exclusions']);
        $message = "Berhasil membuat/memperbarui {$count} settlement draft.";
        if ($excluded > 0) {
            $message .= " {$excluded} item dikecualikan (Belum memiliki aturan Sharing Fee).";
        }

        return redirect()
            ->route('finance.sharing-fees.settlements.index')
            ->with('success', $message)
            ->with('sharing_fee_exclusions', $result['exclusions']);
    }

    public function show(Request $request, SharingFeeSettlement $sharingFeeSettlement): View
    {
        $this->authorize('view', $sharingFeeSettlement);

        $sharingFeeSettlement->load([
            'doctor',
            'branch',
            'items.procedure',
            'payments.creator',
            'adjustments.creator',
            'approver',
        ]);

        $accounts = CashAccount::query();
        $this->constrainBranch($accounts, $request->user(), 'branch_id', $sharingFeeSettlement->branch_id);
        if ($sharingFeeSettlement->branch_id) {
            $accounts->where('branch_id', $sharingFeeSettlement->branch_id);
        }
        $accounts = $accounts->orderBy('name')->get();

        return view('finance.sharing-fees.settlements.show', [
            'settlement' => $sharingFeeSettlement,
            'accounts' => $accounts,
            'methods' => PaymentMethod::options(),
        ]);
    }

    public function approve(Request $request, SharingFeeSettlement $sharingFeeSettlement): RedirectResponse
    {
        $this->authorize('approve', $sharingFeeSettlement);

        try {
            $this->settlements->approve($sharingFeeSettlement, $request->user());
        } catch (SharingFeeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Settlement sharing fee disetujui.');
    }

    public function pay(PaySharingFeeSettlementRequest $request, SharingFeeSettlement $sharingFeeSettlement): RedirectResponse
    {
        try {
            $this->payments->pay(
                $sharingFeeSettlement,
                $request->user(),
                (string) $request->input('amount'),
                PaymentMethod::from($request->string('method')->toString()),
                $request->integer('cash_account_id'),
                $request->date('paid_on')->toDateString(),
                $request->input('reference'),
                $request->input('notes'),
            );
        } catch (SharingFeeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pembayaran sharing fee berhasil dicatat.');
    }

    public function adjust(AdjustSharingFeeSettlementRequest $request, SharingFeeSettlement $sharingFeeSettlement): RedirectResponse
    {
        try {
            $this->settlements->addAdjustment(
                $sharingFeeSettlement,
                $request->user(),
                (string) $request->input('amount'),
                (string) $request->input('reason'),
                $request->filled('settlement_item_id') ? $request->integer('settlement_item_id') : null,
            );
        } catch (SharingFeeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Adjustment sharing fee berhasil dicatat.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function doctorOptions(Request $request)
    {
        return User::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'dentist'))
            ->orderBy('name')
            ->get();
    }
}
