<?php

namespace App\Http\Controllers\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Billing\Concerns\ScopesBillingBranch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Billing\BillingException;
use App\Support\Billing\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    use ScopesBillingBranch;

    public function __construct(protected BillingService $billing) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()->with(['invoice.patient', 'cashier', 'shift']);

        $this->constrainRelatedBranch($query, $request->user(), 'invoice');

        if ($request->filled('date_from')) {
            $query->whereDate('paid_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('paid_at', '<=', $request->date('date_to'));
        }

        $payments = $query->latest('id')->paginate(20)->withQueryString();

        return view('billing.payments.index', [
            'payments' => $payments,
            'methods' => PaymentMethod::options(),
            'shift' => $this->billing->currentShift($request->user()),
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $invoice = Invoice::query()->findOrFail($request->validated('invoice_id'));
        $this->authorize('view', $invoice);

        try {
            $this->billing->pay(
                $invoice,
                $request->user(),
                (string) $request->validated('amount'),
                PaymentMethod::from($request->validated('method')),
                $request->validated('notes'),
            );
        } catch (BillingException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('billing.invoices.show', $invoice)->with('success', 'Pembayaran tercatat.');
    }

    public function receivables(Request $request): View
    {
        abort_unless($request->user()?->can('receivable.view'), 403);

        $query = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->with(['patient', 'payer', 'branch']);
        $this->constrainBranch($query, $request->user());

        $invoices = $query->orderBy('created_at')->get();

        $buckets = [
            '0-30' => ['label' => '0–30 hari', 'rows' => collect(), 'total' => '0.00'],
            '31-60' => ['label' => '31–60 hari', 'rows' => collect(), 'total' => '0.00'],
            '61-90' => ['label' => '61–90 hari', 'rows' => collect(), 'total' => '0.00'],
            '90+' => ['label' => '> 90 hari', 'rows' => collect(), 'total' => '0.00'],
        ];

        foreach ($invoices as $invoice) {
            $days = $invoice->created_at?->startOfDay()->diffInDays(now()->startOfDay()) ?? 0;
            $key = match (true) {
                $days <= 30 => '0-30',
                $days <= 60 => '31-60',
                $days <= 90 => '61-90',
                default => '90+',
            };
            $buckets[$key]['rows']->push($invoice);
            $buckets[$key]['total'] = bcadd($buckets[$key]['total'], $invoice->remainingAmount(), 2);
        }

        return view('billing.receivables.index', compact('buckets'));
    }
}
