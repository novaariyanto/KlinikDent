<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Billing\Concerns\ScopesBillingBranch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\GenerateInvoiceRequest;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Http\Requests\Billing\VoidInvoiceRequest;
use App\Models\Invoice;
use App\Models\Visit;
use App\Support\Billing\BillingException;
use App\Support\Billing\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    use ScopesBillingBranch;

    public function __construct(protected BillingService $billing)
    {
    }

    public function index(Request $request): View
    {
        return $this->listing($request, 'Tagihan', null, true);
    }

    public function today(Request $request): View
    {
        return $this->listing($request, 'Tagihan Hari Ini', 'today', false);
    }

    public function history(Request $request): View
    {
        return $this->listing($request, 'Riwayat Tagihan', 'paid', false);
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load(['items', 'payments.cashier', 'patient', 'payer', 'branch', 'visit.doctor']);

        return view('billing.invoices.show', [
            'invoice' => $invoice,
            'methods' => PaymentMethod::options(),
            'shift' => $this->billing->currentShift(request()->user(), (int) $invoice->branch_id),
        ]);
    }

    public function generate(GenerateInvoiceRequest $request): RedirectResponse
    {
        $visit = Visit::withoutGlobalScopes()->findOrFail($request->validated('visit_id'));
        abort_unless($request->user()?->belongsToTenantId($visit->tenant_id), 403);

        $branchId = $this->restrictedBranchId($request->user());
        abort_if($branchId && (int) $visit->branch_id !== $branchId, 403);

        $invoice = $this->billing->generateForVisit($visit, $request->user());

        if (! $invoice) {
            return back()->with('error', 'Tidak ada item yang dapat ditagih (tindakan unbilled atau resep terpenuhi).');
        }

        return redirect()->route('billing.invoices.show', $invoice)->with('success', 'Tagihan '.$invoice->number.' siap.');
    }

    public function pay(StorePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
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

    public function void(VoidInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        try {
            $this->billing->void($invoice, $request->user(), $request->validated('reason'));
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('billing.invoices.show', $invoice)->with('success', 'Tagihan dibatalkan.');
    }

    protected function listing(Request $request, string $title, ?string $preset, bool $showUnbilled): View
    {
        $this->authorize('viewAny', Invoice::class);

        $query = Invoice::query()->with(['patient', 'payer', 'branch', 'visit']);
        $this->constrainBranch($query, $request->user());

        if ($preset === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($preset === 'paid') {
            $query->where('status', InvoiceStatus::Paid);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('number', 'like', $term)
                    ->orWhereHas('patient', fn ($patient) => $patient->where('name', 'like', $term));
            });
        }

        $invoices = $query->latest('id')->paginate(20)->withQueryString();

        $unbilled = collect();

        if ($showUnbilled) {
            $unbilled = Visit::query()
                ->whereHas('procedureRecords', fn ($records) => $records->where('billing_status', BillingStatus::Unbilled))
                ->when($this->restrictedBranchId($request->user()), fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->with(['patient', 'branch'])
                ->latest('id')
                ->limit(15)
                ->get();
        }

        return view('billing.invoices.index', compact('invoices', 'title', 'unbilled', 'showUnbilled'));
    }
}
