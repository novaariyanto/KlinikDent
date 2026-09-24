<?php

namespace App\Support\Pharmacy;

use App\Enums\PrescriptionStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\MedicineStock;
use App\Models\Prescription;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Visit;
use App\Support\Billing\BillingService;
use App\Support\WhatsApp\WhatsAppAutomationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PharmacyStockService
{
    public const EXPIRING_DAYS = 30;

    /**
     * @return Collection<int, MedicineStock>
     */
    public function usableStocks(int $tenantId, int $branchId, int $medicineId, bool $lock = false): Collection
    {
        $query = MedicineStock::query()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('medicine_id', $medicineId)
            ->usable()
            ->orderBy('expired_date')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    public function availableQuantity(int $tenantId, int $branchId, int $medicineId): int
    {
        return (int) $this->usableStocks($tenantId, $branchId, $medicineId)->sum('quantity');
    }

    /**
     * @return list<array{stock: MedicineStock, quantity: int}>
     */
    public function previewFefo(int $tenantId, int $branchId, int $medicineId, int $quantity): array
    {
        return $this->planFefo($this->usableStocks($tenantId, $branchId, $medicineId), $quantity);
    }

    public function fulfill(Prescription $prescription, User $user): void
    {
        if (! $prescription->isSent()) {
            throw new InvalidArgumentException('Resep belum bisa diproses.');
        }

        DB::transaction(function () use ($prescription, $user) {
            /** @var Prescription $locked */
            $locked = Prescription::query()
                ->whereKey($prescription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PrescriptionStatus::Sent) {
                throw new InvalidArgumentException('Resep sudah diproses.');
            }

            $locked->load(['items.medicine', 'items.ingredients.medicine', 'visit']);

            $tenantId = (int) $locked->visit?->tenant_id;
            $branchId = (int) $locked->visit?->branch_id;

            if (! $tenantId || ! $branchId) {
                throw new InvalidArgumentException('Resep tidak memiliki cabang kunjungan.');
            }

            $demands = [];

            foreach ($locked->items as $item) {
                if ($item->isCompound()) {
                    foreach ($item->ingredients as $ingredient) {
                        if (! $ingredient->medicine_id) {
                            continue;
                        }

                        $demands[] = [
                            'label' => $ingredient->medicine?->name ?: $ingredient->name,
                            'medicine_id' => (int) $ingredient->medicine_id,
                            'quantity' => (int) $ingredient->quantity,
                        ];
                    }

                    continue;
                }

                $demands[] = [
                    'label' => $item->medicine?->name ?? 'Obat #'.$item->medicine_id,
                    'medicine_id' => (int) $item->medicine_id,
                    'quantity' => (int) $item->quantity,
                ];
            }

            $shortages = [];
            $plans = [];

            foreach ($demands as $demand) {
                $needed = (int) $demand['quantity'];
                $stocks = $this->usableStocks($tenantId, $branchId, (int) $demand['medicine_id'], true);
                $allocations = $this->planFefo($stocks, $needed);
                $taken = array_sum(array_map(fn (array $row) => $row['quantity'], $allocations));

                if ($taken < $needed) {
                    $shortages[] = $demand['label'].' (butuh '.$needed.', tersedia '.$taken.')';

                    continue;
                }

                $plans[] = $allocations;
            }

            if ($shortages !== []) {
                throw new InsufficientStockException('Stok tidak cukup: '.implode('; ', $shortages));
            }

            foreach ($plans as $allocations) {
                foreach ($allocations as $allocation) {
                    /** @var MedicineStock $stock */
                    $stock = $allocation['stock'];
                    $quantity = (int) $allocation['quantity'];

                    if ($stock->quantity < $quantity) {
                        throw new InsufficientStockException('Stok berubah saat diproses. Muat ulang halaman.');
                    }

                    $stock->quantity -= $quantity;
                    $stock->save();

                    $this->recordMovement(
                        $stock,
                        StockMovementType::Out,
                        $quantity,
                        $user,
                        $locked,
                        'Resep #'.$locked->id
                    );
                }
            }

            $locked->update(['status' => PrescriptionStatus::Fulfilled]);

            activity_log('fulfilled', $locked, [], 'Resep #'.$locked->id.' dipenuhi farmasi.', 'pharmacy', $user);

            $visitId = (int) $locked->visit_id;
            $actorId = $user->id;

            DB::afterCommit(function () use ($locked, $visitId, $actorId) {
                app(WhatsAppAutomationService::class)->queuePrescription($locked->fresh(['visit.patient', 'visit.branch', 'doctor']));

                $visit = Visit::query()->find($visitId);

                if ($visit) {
                    app(BillingService::class)->generateForVisit($visit, User::query()->find($actorId));
                }
            });
        });
    }

    /**
     * @param  array<int, array{batch_number: string, expired_date: string}>  $receipts
     */
    public function receive(PurchaseOrder $order, User $user, array $receipts): void
    {
        if (! $order->isOrdered()) {
            throw new InvalidArgumentException('Hanya PO berstatus dipesan yang dapat diterima.');
        }

        DB::transaction(function () use ($order, $user, $receipts) {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PurchaseOrderStatus::Ordered) {
                throw new InvalidArgumentException('PO sudah diterima.');
            }

            $locked->load('items.medicine');

            foreach ($locked->items as $item) {
                $receipt = $receipts[$item->id] ?? null;

                if (! is_array($receipt) || blank($receipt['batch_number'] ?? null) || blank($receipt['expired_date'] ?? null)) {
                    throw new InvalidArgumentException('Batch dan tanggal kedaluwarsa wajib diisi untuk setiap item.');
                }

                $stock = $this->addStock(
                    tenantId: (int) $locked->tenant_id,
                    branchId: (int) $locked->branch_id,
                    medicineId: (int) $item->medicine_id,
                    batchNumber: trim((string) $receipt['batch_number']),
                    expiredDate: (string) $receipt['expired_date'],
                    quantity: (int) $item->quantity,
                    user: $user,
                    reference: $locked,
                    notes: 'Penerimaan '.$locked->number,
                );

                $item->update([
                    'batch_number' => $stock->batch_number,
                    'expired_date' => $stock->expired_date?->toDateString(),
                ]);
            }

            $locked->update([
                'status' => PurchaseOrderStatus::Received,
                'received_at' => now(),
            ]);

            activity_log('received', $locked, [], 'PO '.$locked->number.' diterima.', 'pharmacy', $user);
        });
    }

    public function adjust(MedicineStock $stock, int $delta, User $user, string $notes): void
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('Jumlah penyesuaian tidak boleh 0.');
        }

        DB::transaction(function () use ($stock, $delta, $user, $notes) {
            /** @var MedicineStock $locked */
            $locked = MedicineStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $next = $locked->quantity + $delta;

            if ($next < 0) {
                throw new InsufficientStockException('Penyesuaian membuat stok minus. Stok saat ini '.$locked->quantity.'.');
            }

            $locked->quantity = $next;
            $locked->save();

            $this->recordMovement(
                $locked,
                StockMovementType::Adjustment,
                $delta,
                $user,
                null,
                $notes
            );

            activity_log('adjusted', $locked, ['delta' => $delta], 'Penyesuaian stok '.$notes, 'pharmacy', $user);
        });
    }

    public function returnStock(MedicineStock $stock, string $direction, int $quantity, User $user, string $notes): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah retur minimal 1.');
        }

        $type = $direction === 'supplier'
            ? StockMovementType::ReturnOut
            : StockMovementType::ReturnIn;
        $delta = $direction === 'supplier' ? -$quantity : $quantity;

        DB::transaction(function () use ($stock, $type, $delta, $quantity, $user, $notes) {
            /** @var MedicineStock $locked */
            $locked = MedicineStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $next = $locked->quantity + $delta;

            if ($next < 0) {
                throw new InsufficientStockException('Retur melebihi stok batch. Stok saat ini '.$locked->quantity.'.');
            }

            $locked->quantity = $next;
            $locked->save();

            $this->recordMovement($locked, $type, $quantity, $user, null, $notes);
            activity_log('returned', $locked, ['delta' => $delta], $notes, 'pharmacy', $user);
        });
    }

    /**
     * @param  list<array{stock_id: int, physical_qty: int}>  $lines
     */
    public function postOpname(int $tenantId, int $branchId, User $user, array $lines, ?string $notes = null): void
    {
        DB::transaction(function () use ($tenantId, $branchId, $user, $lines, $notes) {
            $opname = \App\Models\StockOpname::query()->create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'status' => 'posted',
                'notes' => $notes,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                /** @var MedicineStock $stock */
                $stock = MedicineStock::query()
                    ->where('tenant_id', $tenantId)
                    ->where('branch_id', $branchId)
                    ->whereKey($line['stock_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $physical = (int) $line['physical_qty'];
                $system = (int) $stock->quantity;

                \App\Models\StockOpnameItem::query()->create([
                    'stock_opname_id' => $opname->id,
                    'medicine_stock_id' => $stock->id,
                    'system_qty' => $system,
                    'physical_qty' => $physical,
                ]);

                $delta = $physical - $system;

                if ($delta === 0) {
                    continue;
                }

                $stock->quantity = $physical;
                $stock->save();

                $this->recordMovement(
                    $stock,
                    StockMovementType::Adjustment,
                    $delta,
                    $user,
                    $opname,
                    'Opname #'.$opname->id
                );
            }
        });
    }

    public function addStock(
        int $tenantId,
        int $branchId,
        int $medicineId,
        string $batchNumber,
        string $expiredDate,
        int $quantity,
        User $user,
        ?object $reference = null,
        ?string $notes = null,
    ): MedicineStock {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah stok masuk minimal 1.');
        }

        /** @var MedicineStock $stock */
        $stock = MedicineStock::query()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('medicine_id', $medicineId)
            ->where('batch_number', $batchNumber)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            $stock->quantity += $quantity;
            $stock->save();
        } else {
            $stock = MedicineStock::query()->create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'medicine_id' => $medicineId,
                'batch_number' => $batchNumber,
                'expired_date' => $expiredDate,
                'quantity' => $quantity,
            ]);
        }

        $this->recordMovement($stock, StockMovementType::In, $quantity, $user, $reference, $notes);

        return $stock->refresh();
    }

    /**
     * @param  Collection<int, MedicineStock>  $stocks
     * @return list<array{stock: MedicineStock, quantity: int}>
     */
    protected function planFefo(Collection $stocks, int $needed): array
    {
        $allocations = [];
        $remaining = $needed;

        foreach ($stocks as $stock) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((int) $stock->quantity, $remaining);

            if ($take < 1) {
                continue;
            }

            $allocations[] = ['stock' => $stock, 'quantity' => $take];
            $remaining -= $take;
        }

        return $allocations;
    }

    protected function recordMovement(
        MedicineStock $stock,
        StockMovementType $type,
        int $quantity,
        User $user,
        ?object $reference,
        ?string $notes,
    ): StockMovement {
        return StockMovement::query()->create([
            'tenant_id' => $stock->tenant_id,
            'medicine_stock_id' => $stock->id,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference->id ?? null,
            'user_id' => $user->id,
            'notes' => $notes,
        ]);
    }
}
