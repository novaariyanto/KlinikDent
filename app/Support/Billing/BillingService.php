<?php

namespace App\Support\Billing;

use App\Enums\BillingStatus;
use App\Enums\CashShiftStatus;
use App\Enums\InvoiceItemSource;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PrescriptionStatus;
use App\Enums\RoleName;
use App\Enums\WhatsAppEvent;
use App\Models\CashierShift;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PrescriptionItem;
use App\Models\ProcedureRecord;
use App\Models\User;
use App\Models\Visit;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Support\Str;
use App\Support\Registration\DocumentSequenceGenerator;
use App\Support\WhatsApp\WhatsAppAutomationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingService
{
    /**
     * @var array<string, string>
     */
    public const DECLINE_REASONS = [
        'tidak_jadi' => 'Pasien tidak jadi membeli obat',
        'beli_luar' => 'Pasien memilih membeli obat di luar klinik',
        'stok_habis' => 'Obat tidak tersedia',
        'menolak' => 'Pasien menolak obat',
        'diganti' => 'Obat diganti dengan obat lain',
        'dikurangi' => 'Jumlah obat dikurangi berdasarkan instruksi dokter',
        'salah_input' => 'Kesalahan input resep atau billing',
        'lainnya' => 'Alasan lainnya',
    ];

    public function __construct(protected DocumentSequenceGenerator $sequences) {}

    public function generateForVisit(Visit $visit, ?User $user = null): ?Invoice
    {
        return DB::transaction(function () use ($visit, $user) {
            /** @var Visit $locked */
            $locked = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $locked->load(['procedureRecords.procedure', 'prescriptions.items.medicine', 'prescriptions.items.ingredients.medicine', 'patient', 'payer', 'branch']);

            $procedures = $locked->procedureRecords->filter(fn (ProcedureRecord $record) => $record->isUnbilled());
            $medicines = $this->unbilledMedicines($locked);

            if ($procedures->isEmpty() && $medicines->isEmpty()) {
                return $this->openInvoiceForVisit($locked);
            }

            $existing = $this->openInvoiceForVisit($locked);
            $created = $existing === null;
            $invoice = $existing ?? $this->createInvoice($locked, $user);

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
                if ($item->isCompound()) {
                    $price = '0.00';

                    foreach ($item->ingredients as $ingredient) {
                        $unit = (string) ($ingredient->medicine?->sell_price ?? $ingredient->medicine?->base_price ?? '0.00');
                        $price = bcadd($price, bcmul($unit, (string) $ingredient->quantity, 2), 2);
                    }

                    $this->addItem(
                        $invoice,
                        InvoiceItemSource::Medicine,
                        (int) $item->id,
                        $this->medicineDescription($item),
                        1,
                        $price,
                    );

                    continue;
                }

                $qty = $this->billableQuantity($item);

                if ($qty < 1) {
                    continue;
                }

                $this->addItem(
                    $invoice,
                    InvoiceItemSource::Medicine,
                    (int) $item->id,
                    $this->medicineDescription($item),
                    $qty,
                    (string) ($item->medicine?->sell_price ?? $item->medicine?->base_price ?? '0.00'),
                );
            }

            $this->recalculate($invoice);

            activity_audit('invoiced', $invoice, [], 'Tagihan '.$invoice->number.' dibuat/diperbarui.', 'billing', [
                'number' => $invoice->number,
            ], $user);

            $fresh = $invoice->fresh(['items', 'patient', 'visit', 'branch']);

            if ($created && $fresh) {
                DB::afterCommit(function () use ($fresh) {
                    app(WhatsAppAutomationService::class)->queueInvoice(WhatsAppEvent::InvoiceCreated, $fresh);
                });
            }

            return $fresh;
        });
    }

    public function pay(
        Invoice $invoice,
        User $user,
        string $amount,
        PaymentMethod $method,
        ?string $notes = null,
        ?string $reference = null,
        ?string $tendered = null,
        bool $acknowledged = false,
    ): Payment {
        $amount = $this->normalizeAmount($amount);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new BillingException('Nominal pembayaran harus lebih dari 0.');
        }

        $change = null;

        if ($tendered !== null && trim($tendered) !== '') {
            $tendered = $this->normalizeAmount($tendered);

            if ($method === PaymentMethod::Cash && bccomp($tendered, $amount, 2) < 0) {
                throw new BillingException('Uang diterima lebih kecil dari nominal pembayaran.');
            }

            if ($method === PaymentMethod::Cash) {
                $change = bcsub($tendered, $amount, 2);
            }
        } else {
            $tendered = null;
        }

        $reference = $reference !== null ? trim($reference) : null;
        $notes = $this->paymentNotes($notes, $reference, $method === PaymentMethod::Cash ? $tendered : null, $change);

        return DB::transaction(function () use ($invoice, $user, $amount, $method, $notes, $reference, $tendered, $change, $acknowledged) {
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

            $before = activity_snapshot($locked);

            $this->recalculate($locked);

            activity_audit('paid', $locked->fresh(), $before, 'Pembayaran tagihan '.$locked->number, 'billing', array_filter([
                'amount' => $amount,
                'method' => $method->value,
                'reference' => $reference,
                'tendered' => $tendered,
                'change' => $change,
            ], fn ($value) => $value !== null && $value !== ''), $user);

            if ($acknowledged) {
                activity_log('cashier_payment_ack', $locked, [
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                ], 'Kasir menyatakan layanan sudah diperiksa sebelum pembayaran '.$locked->number, 'billing', $user);
            }

            $paidInvoice = $locked->fresh(['patient', 'branch', 'visit']);

            DB::afterCommit(function () use ($paidInvoice, $payment) {
                app(WhatsAppAutomationService::class)->queueInvoice(
                    WhatsAppEvent::PaymentReceived,
                    $paidInvoice,
                    $payment,
                );
            });

            return $payment;
        });
    }

    public function refund(Invoice $invoice, User $user, string $amount, PaymentMethod $method, string $reason): Payment
    {
        $amount = $this->normalizeAmount($amount);
        $reason = trim($reason);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new BillingException('Nominal pengembalian harus lebih dari 0.');
        }

        if ($reason === '') {
            throw new BillingException('Alasan pengembalian wajib diisi.');
        }

        return DB::transaction(function () use ($invoice, $user, $amount, $method, $reason) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoid()) {
                throw new BillingException('Tagihan yang dibatalkan tidak dapat dikembalikan.');
            }

            $paid = (string) $locked->payments()->get()->reduce(
                fn (string $carry, Payment $payment) => bcadd($carry, $payment->signedAmount(), 2),
                '0.00'
            );

            if (bccomp($amount, $paid, 2) > 0) {
                throw new BillingException('Pengembalian melebihi dana yang sudah diterima.');
            }

            $shift = $this->shiftForPayment($user, (int) $locked->branch_id);

            if (! $shift) {
                throw new BillingException('Buka shift kasir sebelum pengembalian dana.');
            }

            $payment = Payment::query()->create([
                'invoice_id' => $locked->id,
                'cashier_id' => $user->id,
                'shift_id' => $shift->id,
                'kind' => \App\Enums\PaymentKind::Refund,
                'amount' => $amount,
                'method' => $method,
                'paid_at' => now(),
                'notes' => $reason,
            ]);

            $this->recalculate($locked);

            activity_audit('refunded', $locked->fresh(), [], 'Pengembalian dana tagihan '.$locked->number, 'billing', [
                'amount' => $amount,
                'reason' => $reason,
            ], $user);

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

            $before = activity_snapshot($locked);

            $locked->update([
                'status' => InvoiceStatus::Void,
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'voided_at' => now(),
            ]);

            activity_audit('voided', $locked, $before, 'Tagihan '.$locked->number.' dibatalkan.', 'billing', [
                'reason' => $reason,
            ], $user);
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
                ->get()
                ->reduce(fn (string $carry, Payment $payment) => bcadd($carry, $payment->signedAmount(), 2), '0.00');

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
                $paid = bcadd($paid, $payment->signedAmount(), 2);
            }
        }

        $status = $invoice->status;

        if (! $invoice->isVoid()) {
            if (bccomp($paid, '0', 2) <= 0) {
                $status = InvoiceStatus::Unpaid;
            } elseif (bccomp($paid, $total, 2) >= 0) {
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
     * @return Collection<int, PrescriptionItem>
     */
    public function unbilledMedicines(Visit $visit)
    {
        $itemIds = $visit->prescriptions
            ->filter(fn ($prescription) => $prescription->status === PrescriptionStatus::Fulfilled)
            ->flatMap(fn ($prescription) => $prescription->items);

        if ($itemIds->isEmpty()) {
            return $itemIds;
        }

        $ids = $itemIds->pluck('id')->map(fn ($id) => (int) $id)->all();
        $declined = $this->declinedQuantities($ids);

        $billed = InvoiceItem::query()
            ->where('source_type', InvoiceItemSource::Medicine)
            ->whereIn('source_id', $ids)
            ->whereHas('invoice', fn ($query) => $query
                ->where('visit_id', $visit->id)
                ->where('status', '!=', InvoiceStatus::Void))
            ->pluck('source_id')
            ->all();

        return $itemIds->reject(function (PrescriptionItem $item) use ($billed, $declined) {
            if (in_array($item->id, $billed, true)) {
                return true;
            }

            return $this->billableQuantity($item, (int) ($declined[$item->id] ?? 0)) < 1;
        })->values();
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, int>
     */
    public function declinedQuantities(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $sums = array_fill_keys($itemIds, 0);

        $logs = ActivityLog::query()
            ->where('subject_type', PrescriptionItem::class)
            ->whereIn('subject_id', $itemIds)
            ->where('event', 'medicine_not_taken')
            ->get(['subject_id', 'properties']);

        foreach ($logs as $log) {
            $id = (int) $log->subject_id;
            $sums[$id] = ($sums[$id] ?? 0) + (int) ($log->properties['quantity'] ?? 0);
        }

        return $sums;
    }

    public function billableQuantity(PrescriptionItem $item, ?int $declined = null): int
    {
        $declined ??= (int) ($this->declinedQuantities([(int) $item->id])[(int) $item->id] ?? 0);

        if ($item->isCompound()) {
            return $declined > 0 ? 0 : 1;
        }

        return max(0, (int) $item->quantity - $declined);
    }

    public function declineMedicine(Invoice $invoice, PrescriptionItem $item, User $user, int $quantity, string $reason, ?string $detail = null): string
    {
        $item->loadMissing(['prescription.visit', 'medicine', 'ingredients.medicine']);
        $reasonLabel = self::DECLINE_REASONS[$reason] ?? null;

        if (! $reasonLabel) {
            throw new BillingException('Alasan pembatalan tidak valid.');
        }

        if ($reason === 'lainnya' && trim((string) $detail) === '') {
            throw new BillingException('Isi keterangan untuk alasan lainnya.');
        }

        if ((int) $item->prescription?->visit_id !== (int) $invoice->visit_id) {
            throw new BillingException('Obat tidak termasuk kunjungan tagihan ini.');
        }

        if ($quantity < 1) {
            throw new BillingException('Jumlah yang tidak diambil minimal 1.');
        }

        $invoice->refresh();

        if ($invoice->isVoid()) {
            throw new BillingException('Tagihan yang dibatalkan tidak dapat diubah.');
        }

        $confirmed = app(CashierDesk::class)->confirmation($invoice);

        if (in_array((int) $item->id, $confirmed['medicines'], true)) {
            throw new BillingException('Obat sudah dikonfirmasi diserahkan. Gunakan pengembalian dana, bukan pembatalan pengambilan.');
        }

        if ($invoice->isPaid()) {
            activity_log('medicine_decline_requested', $item, [
                'invoice_id' => $invoice->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'reason_label' => $reasonLabel,
                'detail' => $detail,
            ], 'Permintaan koreksi obat pada tagihan lunas '.$invoice->number.' dicatat tanpa mengubah total.', 'billing', $user);

            throw new BillingException('Tagihan sudah lunas. Permintaan koreksi dicatat. Total tidak diubah. Gunakan pengembalian dana.');
        }

        return DB::transaction(function () use ($invoice, $item, $user, $quantity, $reason, $reasonLabel, $detail) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPaid()) {
                throw new BillingException('Tagihan sudah lunas. Total tidak diubah. Gunakan pengembalian dana.');
            }

            if ($locked->isVoid()) {
                throw new BillingException('Tagihan yang dibatalkan tidak dapat diubah.');
            }

            /** @var PrescriptionItem $rxItem */
            $rxItem = PrescriptionItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $rxItem->loadMissing(['prescription', 'medicine', 'ingredients.medicine']);
            $prescription = $rxItem->prescription;

            if (! $prescription || (int) $prescription->visit_id !== (int) $locked->visit_id) {
                throw new BillingException('Obat tidak termasuk kunjungan tagihan ini.');
            }

            $prescribed = $rxItem->isCompound() ? 1 : (int) $rxItem->quantity;
            $already = (int) ($this->declinedQuantities([(int) $rxItem->id])[(int) $rxItem->id] ?? 0);

            if ($quantity > max(0, $prescribed - $already)) {
                throw new BillingException('Jumlah melebihi sisa obat yang masih bisa dibatalkan.');
            }

            if ($rxItem->isCompound() && $quantity !== 1) {
                throw new BillingException('Racikan hanya dapat dibatalkan utuh.');
            }

            $line = InvoiceItem::query()
                ->where('invoice_id', $locked->id)
                ->where('source_type', InvoiceItemSource::Medicine)
                ->where('source_id', $rxItem->id)
                ->lockForUpdate()
                ->first();

            if (! $line) {
                $elsewhere = InvoiceItem::query()
                    ->where('source_type', InvoiceItemSource::Medicine)
                    ->where('source_id', $rxItem->id)
                    ->where('invoice_id', '!=', $locked->id)
                    ->whereHas('invoice', fn ($query) => $query
                        ->where('visit_id', $locked->visit_id)
                        ->where('status', '!=', InvoiceStatus::Void))
                    ->exists();

                if ($elsewhere) {
                    throw new BillingException('Obat tercatat pada tagihan lain. Buka tagihan tersebut.');
                }
            }

            if ($line && $quantity > (int) $line->quantity) {
                throw new BillingException('Jumlah melebihi yang masih ditagihkan.');
            }

            $beforeTotal = (string) $locked->total_amount;
            $unitPrice = $line ? (string) $line->unit_price : (string) ($rxItem->medicine?->sell_price ?? $rxItem->medicine?->base_price ?? '0.00');
            $amount = $line
                ? bcmul((string) $line->unit_price, (string) $quantity, 2)
                : bcmul($unitPrice, (string) ($rxItem->isCompound() ? 1 : $quantity), 2);
            $billedBefore = $line ? (int) $line->quantity : 0;
            $billedAfter = $billedBefore;

            if ($line) {
                $billedAfter = $billedBefore - $quantity;
                $description = (string) $line->description;

                if ($billedAfter === 0 && ! str_contains($description, 'tidak diambil')) {
                    $description = Str::limit($description.' — tidak diambil', 255, '');
                }

                $line->update([
                    'quantity' => $billedAfter,
                    'subtotal' => bcmul((string) $line->unit_price, (string) $billedAfter, 2),
                    'description' => $description,
                ]);
            }

            $stockReturned = false;

            if ($prescription->isFulfilled()) {
                $stock = app(PharmacyStockService::class);

                if ($rxItem->isCompound()) {
                    foreach ($rxItem->ingredients as $ingredient) {
                        if (! $ingredient->medicine_id || (int) $ingredient->quantity < 1) {
                            continue;
                        }

                        $stock->returnDispensed($prescription, (int) $ingredient->medicine_id, (int) $ingredient->quantity, $user, 'Obat tidak jadi diambil');
                    }
                } elseif ($rxItem->medicine_id) {
                    $stock->returnDispensed($prescription, (int) $rxItem->medicine_id, $quantity, $user, 'Obat tidak jadi diambil');
                }

                $stockReturned = true;
            }

            $before = activity_snapshot($locked);

            if ($line) {
                $this->recalculate($locked);
            }

            $fresh = $locked->fresh();
            $overpaid = bcsub((string) $fresh->paid_amount, (string) $fresh->total_amount, 2);

            $properties = [
                'invoice_id' => $locked->id,
                'visit_id' => $locked->visit_id,
                'prescription_id' => $prescription->id,
                'prescription_item_id' => $rxItem->id,
                'quantity' => $quantity,
                'prescribed_quantity' => $prescribed,
                'billed_quantity_before' => $billedBefore,
                'billed_quantity_after' => $billedAfter,
                'unit_price' => $line ? (string) $line->unit_price : $unitPrice,
                'amount' => $amount,
                'reason' => $reason,
                'reason_label' => $reasonLabel,
                'detail' => $detail,
                'handover_before' => $prescription->isFulfilled() ? 'disiapkan_farmasi' : 'belum_diserahkan',
                'handover_after' => $billedAfter > 0 ? 'sebagian_tidak_diambil' : 'tidak_diambil',
                'stock_returned' => $stockReturned,
                'invoice_total_before' => $beforeTotal,
                'invoice_total_after' => (string) $fresh->total_amount,
                'paid_amount' => (string) $fresh->paid_amount,
                'overpaid' => bccomp($overpaid, '0', 2) > 0 ? $overpaid : '0.00',
            ];

            activity_log('medicine_not_taken', $rxItem, $properties, 'Obat tidak jadi diambil pada tagihan '.$locked->number, 'billing', $user);
            activity_audit('medicine_declined', $fresh, $before, 'Obat tidak jadi diambil pada tagihan '.$locked->number, 'billing', $properties, $user);

            $message = 'Obat tidak jadi diambil. Nilai yang dikeluarkan dari tagihan: Rp'.number_format((float) $amount, 0, ',', '.');

            if (! $line) {
                $message = 'Obat belum ditagih. Jumlah ini tidak akan masuk tagihan.';
            }

            if (! $stockReturned) {
                $message .= ' Stok tidak diubah karena resep belum dipenuhi farmasi.';
            }

            if (bccomp($overpaid, '0', 2) > 0) {
                $message .= ' Kelebihan pembayaran Rp'.number_format((float) $overpaid, 0, ',', '.').'. Gunakan pengembalian dana. Riwayat pembayaran tidak dihapus.';
            }

            return $message;
        });
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
        if ($item->isCompound()) {
            return $item->label();
        }

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

    protected function paymentNotes(?string $notes, ?string $reference, ?string $tendered, ?string $change): ?string
    {
        $parts = array_filter([
            $reference !== null && $reference !== '' ? 'Ref: '.$reference : null,
            $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            $tendered !== null ? 'Diterima '.$tendered.' kembalian '.($change ?? '0.00') : null,
        ]);

        if ($parts === []) {
            return null;
        }

        return Str::limit(implode(' | ', $parts), 255, '');
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
