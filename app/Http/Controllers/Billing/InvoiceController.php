<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingStatus;
use App\Enums\InvoiceItemSource;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\VisitStatus;
use App\Http\Controllers\Billing\Concerns\ScopesBillingBranch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ConfirmServicesRequest;
use App\Http\Requests\Billing\DeclineMedicineRequest;
use App\Http\Requests\Billing\GenerateInvoiceRequest;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Http\Requests\Billing\StoreRefundRequest;
use App\Http\Requests\Billing\VoidInvoiceRequest;
use App\Models\Invoice;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use App\Support\Billing\BillingException;
use App\Support\Billing\BillingService;
use App\Support\Billing\CashierDesk;
use App\Support\ClinicSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    use ScopesBillingBranch;

    public function __construct(protected BillingService $billing, protected CashierDesk $cashier) {}

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

    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%'.$term.'%';
        $invoices = Invoice::query()
            ->with(['patient:id,tenant_id,name,medical_record_number,phone', 'visit.doctor:id,name', 'branch:id,name'])
            ->where(function ($inner) use ($like) {
                $inner->where('number', 'like', $like)
                    ->orWhereHas('patient', function ($patient) use ($like) {
                        $patient->where('name', 'like', $like)
                            ->orWhere('medical_record_number', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });
            });
        $this->constrainBranch($invoices, $request->user());

        return response()->json([
            'data' => $invoices->latest('id')->limit(12)->get()->map(fn (Invoice $invoice) => [
                'number' => $invoice->number,
                'patient' => $invoice->patient?->name,
                'mrn' => $invoice->patient?->medical_record_number,
                'phone' => $invoice->patient?->phone,
                'status' => $invoice->status->label(),
                'visit' => $invoice->visit?->visit_date?->format('d M Y'),
                'doctor' => $invoice->visit?->doctor?->name,
                'branch' => $invoice->branch?->name,
                'url' => route('billing.invoices.show', $invoice),
            ])->values(),
        ]);
    }

    public function todayPatients(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $visits = Visit::query()
            ->with([
                'patient:id,tenant_id,name,medical_record_number,phone',
                'doctor:id,name',
                'invoices' => fn ($query) => $query
                    ->where('status', '!=', InvoiceStatus::Void->value)
                    ->latest('id'),
            ])
            ->whereDate('visit_date', now()->toDateString())
            ->where('status', '!=', VisitStatus::Cancelled->value);
        $this->constrainBranch($visits, $request->user());

        $canGenerate = $request->user()?->can('create', Invoice::class) ?? false;

        return response()->json([
            'data' => $visits->orderBy('id')->limit(100)->get()->map(function (Visit $visit) use ($canGenerate) {
                $invoice = $visit->invoices->first(fn (Invoice $row) => $row->isOpen())
                    ?? $visit->invoices->first();

                return [
                    'id' => $invoice?->id,
                    'patient' => $visit->patient?->name,
                    'mrn' => $visit->patient?->medical_record_number,
                    'phone' => $visit->patient?->phone,
                    'doctor' => $visit->doctor?->name,
                    'visit_status' => $visit->status?->label(),
                    'number' => $invoice?->number,
                    'status' => $invoice?->status->label(),
                    'url' => $invoice ? route('billing.invoices.show', $invoice) : null,
                    'visit_id' => $invoice || ! $canGenerate ? null : $visit->id,
                ];
            })->values(),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        if ($invoice->isOpen() && $invoice->visit && request()->user()?->can('create', Invoice::class)) {
            $generated = $this->billing->generateForVisit($invoice->visit, request()->user());

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

        return view('billing.invoices.show', [
            'invoice' => $invoice,
            'methods' => PaymentMethod::options(),
            'shift' => $this->billing->currentShift(request()->user(), (int) $invoice->branch_id),
            'pharmacyWaiting' => $pharmacyWaiting,
            'review' => $this->cashier->review($invoice),
            'declineReasons' => BillingService::DECLINE_REASONS,
            'overpaid' => $overpaid,
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
            'visit.doctor',
        ]);

        $tenantId = (int) $invoice->tenant_id;
        $settings = ClinicSettings::current($tenantId);
        $printedAt = now();
        $cashierName = $invoice->payments->sortByDesc('id')->first()?->cashier?->name;

        $pdf = Pdf::loadView('billing.invoices.print', [
            'title' => 'Tagihan '.$invoice->number,
            'invoice' => $invoice,
            'clinicName' => ClinicSettings::name($tenantId),
            'clinicTagline' => $settings['tagline'] ?: null,
            'clinicLegalName' => $settings['legal_name'] ?: null,
            'clinicAddressLines' => ClinicSettings::addressLines($tenantId) ?: array_values(array_filter([
                $invoice->branch?->address,
            ])),
            'clinicPhone' => $settings['phone'] ?: $invoice->branch?->phone,
            'clinicEmail' => $settings['email'] ?: null,
            'clinicWebsite' => $settings['website'] ?: null,
            'clinicLicense' => $settings['license_number'] ?: null,
            'clinicNpwp' => $settings['npwp'] ?: null,
            'picName' => $settings['pic_name'] ?: null,
            'picSip' => $settings['pic_sip'] ?: null,
            'printCity' => $settings['print_city'] ?: ($settings['city'] ?: $invoice->branch?->name),
            'printFooter' => $settings['print_footer'] ?: null,
            'branch' => $invoice->branch,
            'visit' => $invoice->visit,
            'patient' => $invoice->patient,
            'logoSrc' => ClinicSettings::printLogoSrc($tenantId),
            'printedAt' => $printedAt,
            'printedDateLabel' => $this->indonesianDate($printedAt),
            'invoiceDateLabel' => $this->indonesianDate($invoice->created_at ?? $printedAt),
            'cashierName' => $cashierName,
        ]);

        $paper = $request->query('paper', 'a4');

        if (! in_array($paper, ['a4', 'a5'], true)) {
            $paper = 'a4';
        }

        return $pdf->setPaper($paper)->stream('tagihan-'.$invoice->number.'.pdf');
    }

    public function receipt(Request $request, Invoice $invoice): View
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

        return view('billing.invoices.receipt', [
            'invoice' => $invoice,
            'paper' => $paper,
            'clinicName' => ClinicSettings::name((int) $invoice->tenant_id),
            'autoprint' => $request->boolean('print'),
        ]);
    }

    protected function indonesianDate(mixed $date): string
    {
        $carbon = Carbon::parse($date);
        $months = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

        return $carbon->format('j').' '.$months[(int) $carbon->format('n')].' '.$carbon->format('Y');
    }

    public function generate(GenerateInvoiceRequest $request): RedirectResponse
    {
        $visit = Visit::withoutGlobalScopes()->findOrFail($request->validated('visit_id'));
        abort_unless($request->user()?->belongsToTenantId($visit->tenant_id), 403);

        abort_unless($request->user()?->canAccessBranch((int) $visit->branch_id), 403);

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
                $request->validated('reference'),
                $request->filled('tendered') ? (string) $request->validated('tendered') : null,
                $request->boolean('service_confirmed'),
            );
        } catch (BillingException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $redirect = redirect()
            ->route('billing.invoices.show', $invoice)
            ->with('success', 'Pembayaran tercatat.');

        if ($invoice->fresh()?->isPaid()) {
            $redirect->with('cashier_print', true);
        }

        return $redirect;
    }

    public function confirm(ConfirmServicesRequest $request, Invoice $invoice): RedirectResponse
    {
        try {
            $this->cashier->confirm(
                $invoice,
                $request->user(),
                $request->validated('procedures') ?? [],
                $request->validated('medicines') ?? [],
            );
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Konfirmasi layanan tercatat.');
    }

    public function declineMedicine(DeclineMedicineRequest $request, Invoice $invoice, PrescriptionItem $prescriptionItem): RedirectResponse
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
        } catch (BillingException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $message);
    }

    public function refund(StoreRefundRequest $request, Invoice $invoice): RedirectResponse
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
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('billing.invoices.show', $invoice)->with('success', 'Pengembalian dana tercatat.');
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
                ->where(function ($visits) {
                    $visits->whereHas('procedureRecords', fn ($records) => $records->where('billing_status', BillingStatus::Unbilled))
                        ->orWhereExists(function ($query) {
                            $query->selectRaw('1')
                                ->from('prescription_items')
                                ->join('prescriptions', 'prescriptions.id', '=', 'prescription_items.prescription_id')
                                ->whereColumn('prescriptions.visit_id', 'visits.id')
                                ->whereNotExists(function ($billed) {
                                    $billed->selectRaw('1')
                                        ->from('invoice_items')
                                        ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                                        ->whereColumn('invoice_items.source_id', 'prescription_items.id')
                                        ->where('invoice_items.source_type', InvoiceItemSource::Medicine->value)
                                        ->whereColumn('invoices.visit_id', 'visits.id')
                                        ->where('invoices.status', '!=', InvoiceStatus::Void->value);
                                });
                        });
                });
            $this->constrainBranch($unbilled, $request->user());
            $unbilled = $unbilled
                ->with(['patient', 'branch'])
                ->latest('id')
                ->limit(15)
                ->get();
        }

        return view('billing.invoices.index', compact('invoices', 'title', 'unbilled', 'showUnbilled'));
    }
}
