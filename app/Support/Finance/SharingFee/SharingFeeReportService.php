<?php

namespace App\Support\Finance\SharingFee;

use App\Enums\SharingFeeSettlementStatus;
use App\Models\SharingFeeSettlement;
use App\Models\SharingFeeSettlementItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SharingFeeReportService
{
    /**
     * @return Collection<int, object>
     */
    public function summaryByDoctor(User $actor, string $from, string $to, ?int $branchId = null): Collection
    {
        $query = SharingFeeSettlement::query()
            ->selectRaw('doctor_user_id')
            ->selectRaw("SUM(CASE WHEN status = ? THEN net_fee_amount ELSE 0 END) as draft_total", [SharingFeeSettlementStatus::Draft->value])
            ->selectRaw("SUM(CASE WHEN status = ? THEN net_fee_amount ELSE 0 END) as approved_total", [SharingFeeSettlementStatus::Approved->value])
            ->selectRaw("SUM(CASE WHEN status = ? THEN net_fee_amount ELSE 0 END) as paid_total", [SharingFeeSettlementStatus::Paid->value])
            ->selectRaw('SUM(net_fee_amount) as net_total')
            ->selectRaw('SUM(paid_amount) as paid_amount_total')
            ->selectRaw('SUM(CASE WHEN net_fee_amount > paid_amount THEN net_fee_amount - paid_amount ELSE 0 END) as outstanding_total')
            ->whereDate('period_start', '>=', $from)
            ->whereDate('period_end', '<=', $to);

        $this->applyActorScope($query, $actor, $branchId);

        $rows = $query->groupBy('doctor_user_id')->get();
        $doctors = User::query()
            ->whereIn('id', $rows->pluck('doctor_user_id')->all())
            ->get()
            ->keyBy('id');

        return $rows->map(function (SharingFeeSettlement $row) use ($doctors) {
            return (object) [
                'doctor_user_id' => (int) $row->doctor_user_id,
                'doctor_name' => $doctors->get($row->doctor_user_id)?->name ?? ('User #'.$row->doctor_user_id),
                'draft_total' => (string) $row->draft_total,
                'approved_total' => (string) $row->approved_total,
                'paid_total' => (string) $row->paid_total,
                'net_total' => (string) $row->net_total,
                'paid_amount_total' => (string) $row->paid_amount_total,
                'outstanding_total' => (string) $row->outstanding_total,
            ];
        });
    }

    /**
     * @return Builder<SharingFeeSettlementItem>
     */
    public function detailsQuery(User $actor, string $from, string $to, ?int $branchId = null, ?int $doctorUserId = null): Builder
    {
        $query = SharingFeeSettlementItem::query()
            ->with(['settlement.doctor', 'settlement.branch', 'procedure', 'invoice'])
            ->whereHas('settlement', function ($q) use ($actor, $from, $to, $branchId, $doctorUserId) {
                $q->whereDate('period_start', '>=', $from)
                    ->whereDate('period_end', '<=', $to);

                $this->applyActorScope($q, $actor, $branchId);

                if ($doctorUserId) {
                    $q->where('doctor_user_id', $doctorUserId);
                }
            })
            ->orderByDesc('id');

        return $query;
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    public function exportRows(User $actor, string $from, string $to, ?int $branchId = null, ?int $doctorUserId = null): array
    {
        $items = $this->detailsQuery($actor, $from, $to, $branchId, $doctorUserId)->get();

        $headers = [
            'Dokter',
            'Cabang',
            'Periode Mulai',
            'Periode Akhir',
            'Status',
            'No. Invoice',
            'Tindakan',
            'Qty',
            'Basis Kotor',
            'Potongan',
            'Basis Fee',
            'Jenis',
            'Tarif',
            'Fee',
        ];

        $rows = $items->map(function (SharingFeeSettlementItem $item) {
            $settlement = $item->settlement;

            return [
                $settlement?->doctor?->name,
                $settlement?->branch?->name,
                $settlement?->period_start?->toDateString(),
                $settlement?->period_end?->toDateString(),
                $settlement?->status?->label(),
                $item->invoice_number,
                $item->item_description,
                $item->quantity,
                $item->gross_base,
                $item->material_deduction,
                $item->fee_base,
                $item->calc_type?->label(),
                $item->rate,
                $item->fee_amount,
            ];
        })->all();

        return compact('headers', 'rows');
    }

    /**
     * @param  Builder<SharingFeeSettlement>  $query
     */
    protected function applyActorScope(Builder $query, User $actor, ?int $branchId): void
    {
        $query->where('tenant_id', $actor->tenant_id);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $actor->applyBranchLimit($query);
        }
    }
}
