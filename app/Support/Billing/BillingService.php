<?php

namespace App\Support\Billing;

use App\Enums\BillingStatus;
use App\Enums\CashShiftStatus;
use App\Enums\InvoiceItemSource;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PrescriptionStatus;
use App\Enums\RoleName;
use App\Models\CashierShift;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PrescriptionItem;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\Models\Visit;
use App\Support\Registration\DocumentSequenceGenerator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingService
{
    public function __construct(protected DocumentSequenceGenerator $sequences)
    {
    }

    public function generateForVisit(Visit $visit, ?User $user = null): ?Invoice
    {
        return DB::transaction(function () use ($visit, $user) {
            /** @var Visit $locked */
            $locked = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $locked->load(['procedureRecords.procedure', 'prescriptions.items.medicine', 'patient', 'payer', 'branch']);

            $procedures = $locked->procedureRecords->filter(fn (ProcedureRecord $record) => $record->isUnbilled());
            $medicines = $this->unbilledMedicines($locked);

            if ($procedures->isEmpty() && $medicines->isEmpty()) {
                return $this->openInvoiceForVisit($locked);
            }

            $invoice = $this->openInvoiceForVisit($locked) ?? $this->createInvoice($locked, $user);

            foreach ($procedures as $record) {
                $this->addItem(
                    $invoice,
                    InvoiceItemSource::Procedure,
                    (int) $record->id,
                    $this->procedureDescription($record),
                    (int) $record->quantity,
                    (string) $record->price_at_time,
                );
                $record->update(['billing_status' => BillingStatus::Billed]);
            }

            foreach ($medicines as $item) {
                $this->addItem(
                    $invoice,
                    InvoiceItemSource::Medicine,
                    (int) $item->id,
                    $this->medicineDescription($item),
                    (int) $item->quantity,
                    (string) ($item->medicine?->base_price ?? '0.00'),
                );
            }

            $this->recalculate($invoice);

            activity_log('invoiced', $invoice, ['number' => $invoice->number], 'Tagihan '.$invoice->number.' dibuat/diperbarui.', 'billing', $user);

            return $invoice->fresh(['items', 'patient', 'visit']);
        });
    }

    public function pay(Invoice $invoice, User $user, string $amount, PaymentMethod $method, ?string $notes = null): Payment
    {
        $amount = $this->normalizeAmount($amount);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new BillingException('Nominal pembayaran harus lebih dari 0.');
        }

        return DB::transaction(function () use ($invoice, $user, $amount, $method, $notes) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoid()) {
                throw new BillingException('Tagihan yang dibatalkan tidak dapat dibayar.');
            }

            if ($locked->isPaid()) {
                throw new BillingException('Tagihan sudah lunas.');
            }

            $remaining = $locked->remainingAmount();

            if (bccomp($amount, $remaining, 2) > 0) {
                throw new BillingException('Pembayaran melebihi sisa tagihan ('.$remaining.').');
            }

            $shift = $this->shiftForPayment($user, (int) $locked->branch_id);

            $payment = Payment::query()->create([
                'invoice_id' => $locked->id,
                'cashier_id' => $user->id,
                'shift_id' => $shift?->id,
                'amount' => $amount,
                'method' => $method,
                'paid_at' => now(),
                'notes' => $notes,
            ]);

            $this->recalculate($locked);

            activity_log('paid', $locked, ['amount' => $amount, 'method' => $method->value], 'Pembayaran tagihan '.$locked->number, 'billing', $user);

            return $payment;
        });
    }

    public function void(Invoice $invoice, User $user, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new BillingException('Alasan void wajib diisi.');
        }

        DB::transaction(function () use ($invoice, $user, $reason) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->with('items')->firstOrFail();

            if ($locked->isVoid()) {
                throw new BillingException('Tagihan sudah dibatalkan.');
            }

            foreach ($locked->items as $item) {
                if ($item->source_type === InvoiceItemSource::Procedure) {
                    ProcedureRecord::query()
                        ->whereKey($item->source_id)
                        ->update(['billing_status' => BillingStatus::Unbilled]);
                }
            }

            $locked->update([
                'status' => InvoiceStatus::Void,
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'voided_at' => now(),
            ]);

            activity_log('voided', $locked, ['reason' => $reason], 'Tagihan '.$locked->number.' dibatalkan.', 'billing', $user);
        });
    }

    public function openShift(User $user, int $branchId, string $openingBalance): CashierShift
    {
        $openingBalance = $this->normalizeAmount($openingBalance);

        if (bccomp($openingBalance, '0', 2) < 0) {
            throw new BillingException('Saldo awal tidak boleh negatif.');
        }

        return DB::transaction(function () use ($user, $branchId, $openingBalance) {
            $existing = CashierShift::query()
                ->where('cashier_id', $user->id)
                ->where('branch_id', $branchId)
                ->where('status', CashShiftStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new BillingException('Masih ada shift terbuka di cabang ini. Tutup shift lama terlebih dahulu.');
            }

            $shift = CashierShift::query()->create([
                'tenant_id' => $user->tenant_id,
                'branch_id' => $branchId,
                'cashier_id' => $user->id,
                'opening_balance' => $openingBalance,
                'opened_at' => now(),
                'status' => CashShiftStatus::Open,
            ]);

            activity_log('opened', $shift, ['opening_balance' => $openingBalance], 'Shift kasir dibuka.', 'billing', $user);

            return $shift;
        });
    }

    public function closeShift(CashierShift $shift, User $user, string $countedBalance, ?string $notes = null): CashierShift
    {
        $countedBalance = $this->normalizeAmount($countedBalance);

        return DB::transaction(function () use ($shift, $user, $countedBalance, $notes) {
            /** @var CashierShift $locked */
            $locked = CashierShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new BillingException('Shift sudah ditutup.');
            }

            if ((int) $locked->cashier_id !== (int) $user->id && ! $user->can('branch.manage')) {
                throw new BillingException('Hanya kasir pemilik shift yang dapat menutupnya.');
            }

            $cashIn = (string) Payment::query()
                ->where('shift_id', $locked->id)
                ->where('method', PaymentMethod::Cash)
                ->sum('amount');

            $system = bcadd((string) $locked->opening_balance, $cashIn, 2);
            $variance = bcsub($countedBalance, $system, 2);

            $locked->update([
                'closing_balance' => $countedBalance,
                'system_balance' => $system,
                'variance' => $variance,
                'close_notes' => $notes,
                'closed_at' => now(),
                'status' => CashShiftStatus::Closed,
            ]);

            activity_log('closed', $locked, [
                'system_balance' => $system,
                'closing_balance' => $countedBalance,
                'variance' => $variance,
            ], 'Shift kasir ditutup. Selisih '.$variance, 'billing', $user);

            return $locked->fresh();
        });
    }

    public function currentShift(User $user, ?int $branchId = null): ?CashierShift
    {
        $branchId ??= $user->branch_id;

        if (! $branchId) {
            return CashierShift::query()
                ->where('cashier_id', $user->id)
                ->where('status', CashShiftStatus::Open)
                ->latest('id')
                ->first();
        }

        return CashierShift::query()
            ->where('cashier_id', $user->id)
            ->where('branch_id', $branchId)
            ->where('status', CashShiftStatus::Open)
            ->first();
    }

    public function recalculate(Invoice $invoice): void
    {
        $invoice->load('items', 'payments');

        $total = '0.00';
        foreach ($invoice->items as $item) {
            $total = bcadd($total, (string) $item->subtotal, 2);
        }

        $paid = '0.00';
        if (! $invoice->isVoid()) {
            foreach ($invoice->payments as $payment) {
                $paid = bcadd($paid, (string) $payment->amount, 2);
            }
        }

        $status = $invoice->status;

        if (! $invoice->isVoid()) {
            if (bccomp($paid, '0', 2) <= 0) {
                $status = InvoiceStatus::Unpaid;
            } elseif (bccomp($paid, $total, 2) >= 0 && bccomp($total, '0', 2) > 0) {
                $status = InvoiceStatus::Paid;
            } else {
                $status = InvoiceStatus::Partial;
            }
        }

        $invoice->update([
            'total_amount' => $total,
            'paid_amount' => $paid,
            'status' => $status,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, PrescriptionItem>
     */
    protected function unbilledMedicines(Visit $visit)
    {
        $itemIds = $visit->prescriptions
            ->filter(fn ($prescription) => $prescription->status === PrescriptionStatus::Fulfilled)
            ->flatMap(fn ($prescription) => $prescription->items);

        if ($itemIds->isEmpty()) {
            return $itemIds;
        }

        $billed = InvoiceItem::query()
            ->where('source_type', InvoiceItemSource::Medicine)
            ->whereIn('source_id', $itemIds->pluck('id'))
            ->whereHas('invoice', fn ($query) => $query
                ->where('visit_id', $visit->id)
                ->where('status', '!=', InvoiceStatus::Void))
            ->pluck('source_id')
            ->all();

        return $itemIds->reject(fn (PrescriptionItem $item) => in_array($item->id, $billed, true))->values();
    }

    protected function openInvoiceForVisit(Visit $visit): ?Invoice
    {
        return Invoice::query()
            ->where('visit_id', $visit->id)
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->lockForUpdate()
            ->latest('id')
            ->first();
    }

    protected function createInvoice(Visit $visit, ?User $user): Invoice
    {
        if (! $visit->branch) {
            throw new InvalidArgumentException('Kunjungan tidak memiliki cabang.');
        }

        return Invoice::query()->create([
            'tenant_id' => $visit->tenant_id,
            'branch_id' => $visit->branch_id,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'payer_id' => $visit->payer_id,
            'number' => $this->sequences->nextInvoice($visit->tenant, $visit->branch),
            'total_amount' => '0.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::Unpaid,
            'created_by' => $user?->id,
        ]);
    }

    protected function addItem(
        Invoice $invoice,
        InvoiceItemSource $source,
        int $sourceId,
        string $description,
        int $quantity,
        string $unitPrice,
    ): InvoiceItem {
        $quantity = max(1, $quantity);
        $subtotal = bcmul($unitPrice, (string) $quantity, 2);

        return $invoice->items()->create([
            'source_type' => $source,
            'source_id' => $sourceId,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
        ]);
    }

    protected function procedureDescription(ProcedureRecord $record): string
    {
        $name = $record->procedure?->name ?? 'Tindakan';
        $tooth = $record->tooth_number ? ' gigi '.$record->tooth_number : '';

        return $name.$tooth;
    }

    protected function medicineDescription(PrescriptionItem $item): string
    {
        $name = $item->medicine?->name ?? 'Obat';
        $extra = collect([$item->dosage, $item->frequency])->filter()->implode(' / ');

        return $extra !== '' ? $name.' ('.$extra.')' : $name;
    }

    protected function shiftForPayment(User $user, int $branchId): ?CashierShift
    {
        if (! $user->hasRole(RoleName::Cashier)) {
            return $this->currentShift($user, $branchId);
        }

        $shift = $this->currentShift($user, $branchId);

        if (! $shift) {
            throw new BillingException('Buka shift kasir sebelum menerima pembayaran.');
        }

        if ((int) $shift->branch_id !== $branchId) {
            throw new BillingException('Shift kasir tidak sesuai cabang tagihan.');
        }

        return $shift;
    }

    protected function normalizeAmount(string $amount): string
    {
        $normalized = str_replace(',', '.', trim($amount));

        if (! is_numeric($normalized)) {
            throw new BillingException('Nominal tidak valid.');
        }

        return number_format((float) $normalized, 2, '.', '');
    }
}
