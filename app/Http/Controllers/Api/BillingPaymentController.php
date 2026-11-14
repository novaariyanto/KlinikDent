<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Billing\Concerns\ScopesBillingBranch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Billing\BillingException;
use App\Support\Billing\BillingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BillingPaymentController extends Controller
{
    use RespondsWithPage;
    use ScopesBillingBranch;

    public function __construct(protected BillingService $billing) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()->with(['invoice.patient', 'cashier', 'shift']);
        $active = $request->attributes->get('active_branch');
        $preferred = $active instanceof Branch ? (int) $active->id : null;
        $this->constrainRelatedBranch($query, $request->user(), 'invoice', 'branch_id', $preferred);

        if ($request->filled('date_from')) {
            $query->whereDate('paid_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('paid_at', '<=', $request->date('date_to'));
        }

        $query->latest('id');

        $response = $this->paged($request, $query, fn (Payment $payment) => [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'method' => $payment->method?->value,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'invoice_number' => $payment->invoice?->number,
            'patient' => $payment->invoice?->patient?->name,
            'cashier' => $payment->cashier?->name,
        ]);

        $payload = $response->getData(true);
        $payload['data'] = [
            'payments' => $payload['data'],
            'methods' => PaymentMethod::options(),
            'shift' => $this->billing->currentShift($request->user()),
        ];

        return response()->json($payload);
    }

    public function store(StorePaymentRequest $request): JsonResponse
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
            throw ValidationException::withMessages([
                'payment' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => ['invoice_id' => $invoice->id],
            'message' => 'Pembayaran tercatat.',
        ]);
    }

    public function receivables(Request $request): JsonResponse
    {
        if (! $request->user()?->can('receivable.view')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $query = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->with(['patient', 'payer', 'branch']);
        $active = $request->attributes->get('active_branch');
        $preferred = $active instanceof Branch ? (int) $active->id : null;
        $this->constrainBranch($query, $request->user(), 'branch_id', $preferred);

        $invoices = $query->orderBy('created_at')->get();

        $buckets = [
            '0-30' => ['label' => '0–30 hari', 'rows' => [], 'total' => '0.00'],
            '31-60' => ['label' => '31–60 hari', 'rows' => [], 'total' => '0.00'],
            '61-90' => ['label' => '61–90 hari', 'rows' => [], 'total' => '0.00'],
            '90+' => ['label' => '> 90 hari', 'rows' => [], 'total' => '0.00'],
        ];

        foreach ($invoices as $invoice) {
            $days = $invoice->created_at?->startOfDay()->diffInDays(now()->startOfDay()) ?? 0;
            $key = match (true) {
                $days <= 30 => '0-30',
                $days <= 60 => '31-60',
                $days <= 90 => '61-90',
                default => '90+',
            };
            $buckets[$key]['rows'][] = [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'patient' => $invoice->patient?->name,
                'remaining' => $invoice->remainingAmount(),
                'days' => $days,
            ];
            $buckets[$key]['total'] = bcadd($buckets[$key]['total'], $invoice->remainingAmount(), 2);
        }

        return response()->json(['data' => array_values($buckets)]);
    }
}
