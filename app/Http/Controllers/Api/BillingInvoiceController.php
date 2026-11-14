<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Billing\Concerns\ScopesBillingBranch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ConfirmServicesRequest;
use App\Http\Requests\Billing\DeclineMedicineRequest;
use App\Http\Requests\Billing\GenerateInvoiceRequest;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Http\Requests\Billing\StoreRefundRequest;
use App\Http\Requests\Billing\VoidInvoiceRequest;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use App\Support\Billing\BillingException;
use App\Support\Billing\BillingService;
use App\Support\Billing\CashierDesk;
use App\Support\ClinicSettings;
use App\Support\Clinical\PrintChrome;
use App\Support\Clinical\PrintSheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BillingInvoiceController extends Controller
{
    use RespondsWithPage;
    use ScopesBillingBranch;

    public function __construct(
        protected BillingService $billing,
        protected CashierDesk $cashier,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $scope = (string) $request->query('scope', 'today');
        $query = Invoice::query()->with(['patient', 'payer', 'branch', 'visit']);
        $active = $request->attributes->get('active_branch');
        $preferred = $active instanceof Branch ? (int) $active->id : null;
        $this->constrainBranch($query, $request->user(), 'branch_id', $preferred);

        if ($scope === 'today') {
            $query->whereDate('created_at', now()->toDateString());
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

        $query->latest('id');

        return $this->paged($request, $query, fn (Invoice $invoice) => [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status?->value,
            'total_amount' => $invoice->total_amount,
            'paid_amount' => $invoice->paid_amount,
            'patient' => $invoice->patient?->name,
            'payer' => $invoice->payer?->name,
            'branch' => $invoice->branch?->name,
            'created_at' => $invoice->created_at?->toIso8601String(),
            'visit_id' => $invoice->visit_id,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        return app(\App\Http\Controllers\Billing\InvoiceController::class)->search($request);
    }

    public function todayPatients(Request $request): JsonResponse
    {
        return app(\App\Http\Controllers\Billing\InvoiceController::class)->todayPatients($request);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        if ($invoice->isOpen() && $invoice->visit && $request->user()?->can('create', Invoice::class)) {
            $generated = $this->billing->generateForVisit($invoice->visit, $request->user());

            if ($generated && (int) $generated->id === (int) $invoice->id) {
                $invoice->refresh();
            }
        }

        $invoice->load([
            'items',
            'payments.cashier',
            'patient',
            'payer',
            'branch',
            'visit.doctor',
            'visit.procedureRecords.procedure',
            'visit.prescriptions.doctor',
            'visit.prescriptions.items.medicine',
            'visit.prescriptions.items.ingredients.medicine',
        ]);

        $pharmacyWaiting = $invoice->visit
            ? $invoice->visit->prescriptions->reject(fn ($prescription) => $prescription->isFulfilled())->values()
            : collect();

        $paid = (string) $invoice->paid_amount;
        $total = (string) $invoice->total_amount;
        $overpaid = bccomp($paid, $total, 2) > 0 ? bcsub($paid, $total, 2) : '0.00';

        return response()->json([
            'data' => [
                'invoice' => $invoice,
                'methods' => PaymentMethod::options(),
                'shift' => $this->billing->currentShift($request->user(), (int) $invoice->branch_id),
                'pharmacy_waiting' => $pharmacyWaiting,
                'review' => $this->cashier->review($invoice),
                'decline_reasons' => BillingService::DECLINE_REASONS,
                'overpaid' => $overpaid,
            ],
        ]);
    }

    public function generate(GenerateInvoiceRequest $request): JsonResponse
    {
        $visit = Visit::withoutGlobalScopes()->findOrFail($request->validated('visit_id'));

        if (! $request->user()?->belongsToTenantId($visit->tenant_id)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (! $request->user()?->canAccessBranch((int) $visit->branch_id)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $invoice = $this->billing->generateForVisit($visit, $request->user());

        if (! $invoice) {
            throw ValidationException::withMessages([
                'visit' => ['Tidak ada item yang dapat ditagih (tindakan unbilled atau resep terpenuhi).'],
            ]);
        }

        return response()->json([
            'data' => ['invoice_id' => $invoice->id, 'number' => $invoice->number],
            'message' => 'Tagihan '.$invoice->number.' siap.',
        ]);
    }

    public function pay(StorePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        try {
            $this->billing->pay(
                $invoice,
                $request->user(),
                (string) $request->validated('amount'),
                PaymentMethod::from($request->validated('method')),
                $request->validated('notes'),
                $request->validated('reference'),
                $request->filled('tendered') ? (string) $request->validated('tendered') : null,
            );
        } catch (BillingException $exception) {
            throw ValidationException::withMessages([
                'payment' => [$exception->getMessage()],
            ]);
        }

        $invoice->refresh();

        return response()->json([
            'data' => $invoice->load(['payments', 'patient']),
            'message' => 'Pembayaran tercatat.',
            'cashier_print' => $invoice->isPaid(),
        ]);
    }

    public function confirm(ConfirmServicesRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $this->cashier->confirm(
                $invoice,
                $request->user(),
                $request->validated('procedures') ?? [],
                $request->validated('medicines') ?? [],
            );
        } catch (BillingException $exception) {
            throw ValidationException::withMessages([
                'confirm' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => $this->cashier->review($invoice->fresh()),
            'message' => 'Konfirmasi layanan tercatat.',
        ]);
    }

    public function declineMedicine(DeclineMedicineRequest $request, Invoice $invoice, PrescriptionItem $prescriptionItem): JsonResponse
    {
        try {
            $message = $this->billing->declineMedicine(
                $invoice,
                $prescriptionItem,
                $request->user(),
                (int) $request->validated('quantity'),
                $request->validated('reason'),
                $request->validated('detail'),
            );
        } catch (BillingException|\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'medicine' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => ['invoice_id' => $invoice->id],
            'message' => $message,
        ]);
    }

    public function refund(StoreRefundRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $this->billing->refund(
                $invoice,
                $request->user(),
                (string) $request->validated('amount'),
                PaymentMethod::from($request->validated('method')),
                $request->validated('reason'),
            );
        } catch (BillingException $exception) {
            throw ValidationException::withMessages([
                'refund' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => $invoice->fresh(['payments']),
            'message' => 'Pengembalian dana tercatat.',
        ]);
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $this->billing->void($invoice, $request->user(), $request->validated('reason'));
        } catch (BillingException $exception) {
            throw ValidationException::withMessages([
                'void' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => $invoice->fresh(),
            'message' => 'Tagihan dibatalkan.',
        ]);
    }

    public function print(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'items',
            'payments.cashier',
            'patient',
            'payer',
            'branch',
            'visit.patient',
            'visit.doctor.doctorProfile',
            'visit.branch',
            'visit.room',
            'visit.payer',
            'visit.queue',
            'visit.medicalRecord',
        ]);

        $tenantId = (int) $invoice->tenant_id;
        $cashierName = $invoice->payments->sortByDesc('id')->first()?->cashier?->name;
        $sheet = $invoice->visit ? PrintSheet::for($invoice->visit) : null;

        $pdf = Pdf::loadView('billing.invoices.print', [
            ...PrintChrome::variables($tenantId, $invoice->branch),
            'title' => 'Tagihan '.$invoice->number,
            'invoice' => $invoice,
            'branch' => $invoice->branch,
            'visit' => $invoice->visit,
            'patient' => $invoice->patient,
            'sheet' => $sheet,
            'invoiceDateLabel' => $this->indonesianDate($invoice->created_at ?? now()),
            'cashierName' => $cashierName,
            'documentHash' => hash('sha256', implode('|', [
                'INV',
                (string) $invoice->number,
                (string) $invoice->total_amount,
                (string) $invoice->paid_amount,
                (string) $invoice->updated_at?->toIso8601String(),
            ])),
        ]);

        $paper = $request->query('paper', 'a4');

        if (! in_array($paper, ['a4', 'a5'], true)) {
            $paper = 'a4';
        }

        return $pdf->setPaper($paper)->stream('tagihan-'.$invoice->number.'.pdf');
    }

    public function receipt(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $paper = $request->query('paper', '80');

        if (! in_array($paper, ['58', '80'], true)) {
            $paper = '80';
        }

        $invoice->load([
            'items',
            'payments.cashier',
            'patient',
            'branch',
            'visit.doctor',
        ]);

        $html = view('billing.invoices.receipt', [
            'invoice' => $invoice,
            'paper' => $paper,
            'clinicName' => ClinicSettings::name((int) $invoice->tenant_id),
            'autoprint' => $request->boolean('print'),
        ])->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    protected function indonesianDate(mixed $date): string
    {
        $carbon = Carbon::parse($date);
        $months = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

        return $carbon->format('j').' '.$months[(int) $carbon->format('n')].' '.$carbon->format('Y');
    }
}
