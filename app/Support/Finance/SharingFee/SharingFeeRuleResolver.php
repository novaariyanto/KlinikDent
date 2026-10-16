<?php

namespace App\Support\Finance\SharingFee;

use App\Models\SharingFeeRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SharingFeeRuleResolver
{
    public const SCOPE_ALL = '__all__';

    /**
     * @param  Collection<int, SharingFeeRule>|null  $rules
     */
    public function resolve(
        int $tenantId,
        ?int $doctorUserId,
        ?int $branchId,
        ?int $procedureId,
        CarbonInterface|string $onDate,
        ?Collection $rules = null,
    ): ?SharingFeeRule {
        $date = $onDate instanceof CarbonInterface
            ? $onDate->toDateString()
            : (string) $onDate;

        $candidates = ($rules ?? $this->activeRules($tenantId, $date))
            ->filter(fn (SharingFeeRule $rule) => $this->matches($rule, $doctorUserId, $branchId, $procedureId))
            ->sort(function (SharingFeeRule $a, SharingFeeRule $b) {
                $rankA = $this->specificityRank($a);
                $rankB = $this->specificityRank($b);

                if ($rankA !== $rankB) {
                    return $rankB <=> $rankA;
                }

                if ((int) $a->priority !== (int) $b->priority) {
                    return (int) $b->priority <=> (int) $a->priority;
                }

                return (int) $b->id <=> (int) $a->id;
            })
            ->values();

        return $candidates->first();
    }

    public function matches(
        SharingFeeRule $rule,
        ?int $doctorUserId,
        ?int $branchId,
        ?int $procedureId,
    ): bool {
        if ($rule->doctor_user_id !== null && (int) $rule->doctor_user_id !== (int) $doctorUserId) {
            return false;
        }

        if ($rule->branch_id !== null && (int) $rule->branch_id !== (int) $branchId) {
            return false;
        }

        if ($rule->procedure_id !== null && (int) $rule->procedure_id !== (int) $procedureId) {
            return false;
        }

        return true;
    }

    /**
     * @return Collection<int, SharingFeeRule>
     */
    public function activeRules(int $tenantId, string $onDate): Collection
    {
        return SharingFeeRule::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $onDate)
            ->where(function ($query) use ($onDate) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $onDate);
            })
            ->get();
    }

    /**
     * Rank spesifisitas (lebih tinggi = lebih menang).
     *
     * 8 D+B+P, 7 D+P, 6 D+B, 5 B+P, 4 D, 3 P, 2 B, 1 semua.
     */
    public function specificityRank(SharingFeeRule $rule): int
    {
        $d = $rule->doctor_user_id !== null;
        $b = $rule->branch_id !== null;
        $p = $rule->procedure_id !== null;

        return match (true) {
            $d && $b && $p => 8,
            $d && $p && ! $b => 7,
            $d && $b && ! $p => 6,
            ! $d && $b && $p => 5,
            $d && ! $b && ! $p => 4,
            ! $d && ! $b && $p => 3,
            ! $d && $b && ! $p => 2,
            ! $d && ! $b && ! $p => 1,
            default => 0,
        };
    }

    /** @deprecated Gunakan specificityRank() */
    public function specificityScore(SharingFeeRule $rule): int
    {
        return $this->specificityRank($rule);
    }

    /**
     * Validasi konflik: cakupan sama + prioritas sama + periode overlap + aktif.
     */
    public function assertNoConflictingPriority(SharingFeeRule $rule, ?int $ignoreId = null): void
    {
        DB::transaction(function () use ($rule, $ignoreId) {
            $query = SharingFeeRule::query()
                ->where('tenant_id', $rule->tenant_id)
                ->where('is_active', true)
                ->where('priority', $rule->priority)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->lockForUpdate();

            if ($rule->doctor_user_id === null) {
                $query->whereNull('doctor_user_id');
            } else {
                $query->where('doctor_user_id', $rule->doctor_user_id);
            }

            if ($rule->branch_id === null) {
                $query->whereNull('branch_id');
            } else {
                $query->where('branch_id', $rule->branch_id);
            }

            if ($rule->procedure_id === null) {
                $query->whereNull('procedure_id');
            } else {
                $query->where('procedure_id', $rule->procedure_id);
            }

            $from = $rule->effective_from?->toDateString() ?? (string) $rule->effective_from;
            $to = $rule->effective_to?->toDateString() ?? ($rule->effective_to ? (string) $rule->effective_to : null);

            $query->where(function ($q) use ($from, $to) {
                $q->whereDate('effective_from', '<=', $to ?? '9999-12-31')
                    ->where(function ($range) use ($from) {
                        $range->whereNull('effective_to')
                            ->orWhereDate('effective_to', '>=', $from);
                    });
            });

            /** @var SharingFeeRule|null $conflict */
            $conflict = $query->with(['doctor', 'branch', 'procedure'])->first();

            if ($conflict) {
                $period = $conflict->effective_from?->format('d/m/Y')
                    .'–'
                    .($conflict->effective_to?->format('d/m/Y') ?? 'tanpa batas');

                throw new SharingFeeException(
                    'Bentrok dengan aturan #'.$conflict->id
                    .' ('.$conflict->coverageSummary().')'
                    .', prioritas '.$conflict->priority
                    .', periode '.$period
                    .'. Ubah prioritas, cakupan, atau periode.'
                );
            }
        });
    }

    /**
     * Preview perhitungan memakai algoritma yang sama dengan settlement.
     *
     * @return array<string, mixed>
     */
    public function preview(
        int $tenantId,
        ?int $doctorUserId,
        ?int $branchId,
        ?int $procedureId,
        string $onDate,
        string $grossBase,
        int $quantity,
        SharingFeeCalculator $calculator,
    ): array {
        $rule = $this->resolve($tenantId, $doctorUserId, $branchId, $procedureId, $onDate);

        if (! $rule) {
            return [
                'matched' => false,
                'message' => 'Belum memiliki aturan Sharing Fee',
                'rule' => null,
                'calculation' => null,
            ];
        }

        $calc = $calculator->calculate($rule, $grossBase, $quantity);

        return [
            'matched' => true,
            'message' => null,
            'rule' => [
                'id' => $rule->id,
                'coverage' => $rule->coverageSummary(),
                'calc_type' => $rule->calc_type?->value,
                'calc_type_label' => $rule->calc_type?->label(),
                'rate' => (string) $rule->rate,
                'priority' => (int) $rule->priority,
                'specificity_rank' => $this->specificityRank($rule),
                'material_deduction_type' => $rule->material_deduction_type?->value,
                'material_deduction_value' => (string) $rule->material_deduction_value,
                'note_fixed' => $rule->calc_type?->value === 'fixed'
                    ? 'Fee nominal tetap = tarif × kuantitas (potongan bahan tidak mengurangi fee nominal).'
                    : null,
            ],
            'calculation' => $calc + [
                'quantity' => max(1, $quantity),
            ],
        ];
    }
}
