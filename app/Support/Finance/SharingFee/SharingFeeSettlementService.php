<?php

namespace App\Support\Finance\SharingFee;

use App\Enums\InvoiceItemSource;
use App\Enums\InvoiceStatus;
use App\Enums\SharingFeePeriodType;
use App\Enums\SharingFeeSettlementStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProcedureRecord;
use App\Models\SharingFeeAdjustment;
use App\Models\SharingFeeSettlement;
use App\Models\SharingFeeSettlementItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SharingFeeSettlementService
{
    public function __construct(
        protected SharingFeeRuleResolver $resolver,
        protected SharingFeeCalculator $calculator,
    ) {}

    /**
     * @return array{settlements: Collection<int, SharingFeeSettlement>, exclusions: list<array<string, mixed>>}
     */
    public function generate(
        User $actor,
        SharingFeePeriodType $periodType,
        string $periodStart,
        string $periodEnd,
        ?int $branchId = null,
        ?int $doctorUserId = null,
    ): array {
        [$start, $end] = $this->normalizePeriod($periodType, $periodStart, $periodEnd);
        $tenantId = (int) $actor->tenant_id;

        return DB::transaction(function () use ($actor, $periodType, $start, $end, $branchId, $doctorUserId, $tenantId) {
            $items = $this->eligibleInvoiceItems($tenantId, $start, $end, $branchId, $doctorUserId);
            $exclusions = [];

            if ($items->isEmpty()) {
                $existing = SharingFeeSettlement::query()
                    ->where('tenant_id', $tenantId)
                    ->where('period_type', $periodType->value)
                    ->whereDate('period_start', $start)
                    ->whereDate('period_end', $end)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->when($doctorUserId, fn ($q) => $q->where('doctor_user_id', $doctorUserId))
                    ->with(['items', 'doctor', 'branch'])
                    ->get();

                if ($existing->isNotEmpty()) {
                    return ['settlements' => $existing, 'exclusions' => []];
                }

                throw new SharingFeeException('Tidak ada item tindakan lunas yang memenuhi syarat untuk periode ini.');
            }

            $grouped = $items->groupBy(function (InvoiceItem $item) {
                $invoice = $item->invoice;
                $doctorId = (int) ($invoice?->visit?->doctor_id ?? 0);
                $itemBranch = (int) ($invoice?->branch_id ?? 0);

                return $doctorId.'|'.$itemBranch;
            });

            $settlements = collect();

            foreach ($grouped as $key => $groupItems) {
                [$doctorId, $itemBranchId] = array_map('intval', explode('|', (string) $key));

                if ($doctorId <= 0) {
                    foreach ($groupItems as $orphan) {
                        $exclusions[] = [
                            'invoice_item_id' => $orphan->id,
                            'invoice_number' => $orphan->invoice?->number,
                            'description' => $orphan->description,
                            'reason' => 'Kunjungan tanpa dokter — Belum memiliki aturan Sharing Fee',
                        ];
                    }
                    continue;
                }

                $settlement = $this->findOrCreateDraft(
                    $tenantId,
                    $itemBranchId ?: null,
                    $doctorId,
                    $periodType,
                    $start,
                    $end,
                    $actor,
                );

                if (! $settlement->isDraft()) {
                    throw new SharingFeeException(
                        'Settlement untuk dokter dan periode ini sudah tidak berstatus draft.'
                    );
                }

                $result = $this->appendItems($settlement, $groupItems);
                $exclusions = array_merge($exclusions, $result['exclusions']);
                $this->recalculateTotals($settlement);

                if ($result['added'] === 0 && $settlement->items()->count() === 0) {
                    $settlement->delete();
                    continue;
                }

                $settlements->push($settlement->fresh(['items', 'doctor', 'branch']));
            }

            if ($settlements->isEmpty()) {
                $hint = $exclusions !== []
                    ? ' Semua item masuk pengecualian (Belum memiliki aturan Sharing Fee).'
                    : '';
                throw new SharingFeeException('Tidak ada item dengan aturan sharing fee yang cocok untuk dihitung.'.$hint);
            }

            return ['settlements' => $settlements, 'exclusions' => $exclusions];
        });
    }

    public function approve(SharingFeeSettlement $settlement, User $actor): SharingFeeSettlement
    {
        return DB::transaction(function () use ($settlement, $actor) {
            /** @var SharingFeeSettlement $locked */
            $locked = SharingFeeSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw new SharingFeeException('Hanya settlement berstatus draft yang dapat disetujui.');
            }

            if ($locked->items()->count() === 0) {
                throw new SharingFeeException('Settlement kosong tidak dapat disetujui.');
            }

            $locked->update([
                'status' => SharingFeeSettlementStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            activity_audit('approved', $locked, [], 'Settlement sharing fee disetujui.', 'sharing_fee', [
                'settlement_id' => $locked->id,
                'net_fee_amount' => (string) $locked->net_fee_amount,
            ], $actor);

            return $locked->fresh(['items', 'doctor', 'branch']);
        });
    }

    public function addAdjustment(
        SharingFeeSettlement $settlement,
        User $actor,
        string $amount,
        string $reason,
        ?int $settlementItemId = null,
    ): SharingFeeAdjustment {
        $amount = $this->calculator->normalize($amount);

        if (bccomp($amount, '0', 2) === 0) {
            throw new SharingFeeException('Nominal adjustment tidak boleh 0.');
        }

        return DB::transaction(function () use ($settlement, $actor, $amount, $reason, $settlementItemId) {
            /** @var SharingFeeSettlement $locked */
            $locked = SharingFeeSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();

            if ($locked->isDraft()) {
                throw new SharingFeeException('Adjustment hanya untuk settlement yang sudah disetujui atau dibayar.');
            }

            if ($settlementItemId) {
                $belongs = $locked->items()->whereKey($settlementItemId)->exists();
                if (! $belongs) {
                    throw new SharingFeeException('Item settlement tidak ditemukan pada settlement ini.');
                }
            }

            $adjustment = SharingFeeAdjustment::query()->create([
                'tenant_id' => $locked->tenant_id,
                'settlement_id' => $locked->id,
                'settlement_item_id' => $settlementItemId,
                'amount' => $amount,
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);

            $this->recalculateTotals($locked);
            $locked->refresh();

            if (
                $locked->status === SharingFeeSettlementStatus::Paid
                && bccomp($locked->outstandingAmount(), '0', 2) > 0
            ) {
                $locked->update([
                    'status' => SharingFeeSettlementStatus::Approved,
                    'paid_at' => null,
                    'paid_by' => null,
                ]);
            }

            activity_audit('adjusted', $locked, [], 'Adjustment sharing fee dicatat.', 'sharing_fee', [
                'amount' => $amount,
                'reason' => $reason,
            ], $actor);

            return $adjustment->fresh();
        });
    }

    public function recalculateTotals(SharingFeeSettlement $settlement): void
    {
        $gross = (string) ($settlement->items()->sum('fee_amount') ?? '0');
        $gross = $this->calculator->normalize($gross);
        $adjustment = (string) ($settlement->adjustments()->sum('amount') ?? '0');
        $adjustment = $this->calculator->normalize($adjustment);
        $net = bcadd($gross, $adjustment, 2);
        $paid = $this->calculator->normalize((string) ($settlement->payments()->sum('amount') ?? '0'));

        $settlement->update([
            'gross_fee_amount' => $gross,
            'adjustment_amount' => $adjustment,
            'net_fee_amount' => $net,
            'paid_amount' => $paid,
        ]);
    }

    /**
     * @return Collection<int, InvoiceItem>
     */
    protected function eligibleInvoiceItems(
        int $tenantId,
        string $start,
        string $end,
        ?int $branchId,
        ?int $doctorUserId,
    ): Collection {
        $settledItemIds = SharingFeeSettlementItem::query()
            ->where('tenant_id', $tenantId)
            ->pluck('invoice_item_id');

        return InvoiceItem::query()
            ->where('source_type', InvoiceItemSource::Procedure->value)
            ->whereNotIn('id', $settledItemIds)
            ->whereHas('invoice', function ($query) use ($tenantId, $start, $end, $branchId, $doctorUserId) {
                $query->where('tenant_id', $tenantId)
                    ->where('status', InvoiceStatus::Paid->value)
                    ->whereDate('updated_at', '>=', $start)
                    ->whereDate('updated_at', '<=', $end);

                if ($branchId) {
                    $query->where('branch_id', $branchId);
                }

                if ($doctorUserId) {
                    $query->whereHas('visit', fn ($v) => $v->where('doctor_id', $doctorUserId));
                }
            })
            ->with(['invoice.visit', 'invoice.branch'])
            ->orderBy('id')
            ->get();
    }

    protected function findOrCreateDraft(
        int $tenantId,
        ?int $branchId,
        int $doctorUserId,
        SharingFeePeriodType $periodType,
        string $start,
        string $end,
        User $actor,
    ): SharingFeeSettlement {
        $existing = SharingFeeSettlement::query()
            ->where('tenant_id', $tenantId)
            ->where('doctor_user_id', $doctorUserId)
            ->where('period_type', $periodType->value)
            ->whereDate('period_start', $start)
            ->whereDate('period_end', $end)
            ->when(
                $branchId === null,
                fn ($q) => $q->whereNull('branch_id'),
                fn ($q) => $q->where('branch_id', $branchId),
            )
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        return SharingFeeSettlement::query()->create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'doctor_user_id' => $doctorUserId,
            'period_type' => $periodType,
            'period_start' => $start,
            'period_end' => $end,
            'status' => SharingFeeSettlementStatus::Draft,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     * @return array{added: int, exclusions: list<array<string, mixed>>}
     */
    protected function appendItems(SharingFeeSettlement $settlement, Collection $items): array
    {
        $onDate = $settlement->period_end?->toDateString() ?? now()->toDateString();
        $rulesCache = $this->resolver->activeRules((int) $settlement->tenant_id, $onDate);
        $added = 0;
        $exclusions = [];

        foreach ($items as $item) {
            /** @var Invoice $invoice */
            $invoice = $item->invoice;
            Invoice::query()->whereKey($invoice->id)->lockForUpdate()->first();

            $already = SharingFeeSettlementItem::query()
                ->where('tenant_id', $settlement->tenant_id)
                ->where('invoice_item_id', $item->id)
                ->lockForUpdate()
                ->exists();

            if ($already) {
                continue;
            }

            $procedureId = $this->resolveProcedureId($item);
            $rule = $this->resolver->resolve(
                (int) $settlement->tenant_id,
                (int) $settlement->doctor_user_id,
                (int) $invoice->branch_id,
                $procedureId,
                $invoice->updated_at ?? $onDate,
                $rulesCache,
            );

            if (! $rule) {
                $exclusions[] = [
                    'invoice_item_id' => $item->id,
                    'invoice_number' => (string) $invoice->number,
                    'description' => (string) $item->description,
                    'reason' => 'Belum memiliki aturan Sharing Fee',
                ];
                continue;
            }

            $calc = $this->calculator->calculate(
                $rule,
                (string) $item->subtotal,
                (int) $item->quantity,
            );

            SharingFeeSettlementItem::query()->create([
                'tenant_id' => $settlement->tenant_id,
                'settlement_id' => $settlement->id,
                'invoice_id' => $invoice->id,
                'invoice_item_id' => $item->id,
                'procedure_id' => $procedureId,
                'rule_id' => $rule->id,
                'invoice_number' => (string) $invoice->number,
                'item_description' => (string) $item->description,
                'quantity' => (int) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'gross_base' => $calc['gross_base'],
                'material_deduction' => $calc['material_deduction'],
                'fee_base' => $calc['fee_base'],
                'calc_type' => $calc['calc_type'],
                'rate' => $calc['rate'],
                'fee_amount' => $calc['fee_amount'],
                'rule_priority' => (int) $rule->priority,
                'rule_snapshot' => $rule->toSnapshot(),
            ]);
            $added++;
        }

        return ['added' => $added, 'exclusions' => $exclusions];
    }

    protected function resolveProcedureId(InvoiceItem $item): ?int
    {
        if ($item->source_type !== InvoiceItemSource::Procedure) {
            return null;
        }

        $record = ProcedureRecord::query()->find($item->source_id);

        return $record?->procedure_id ? (int) $record->procedure_id : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function normalizePeriod(SharingFeePeriodType $type, string $periodStart, string $periodEnd): array
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->startOfDay();

        if ($end->lt($start)) {
            throw new SharingFeeException('Tanggal akhir periode harus setelah atau sama dengan tanggal mulai.');
        }

        return match ($type) {
            SharingFeePeriodType::Daily => [
                $start->toDateString(),
                $start->toDateString(),
            ],
            SharingFeePeriodType::Weekly => [
                $start->copy()->startOfWeek()->toDateString(),
                $start->copy()->startOfWeek()->addDays(6)->toDateString(),
            ],
            SharingFeePeriodType::Monthly => [
                $start->copy()->startOfMonth()->toDateString(),
                $start->copy()->endOfMonth()->toDateString(),
            ],
        };
    }
}
