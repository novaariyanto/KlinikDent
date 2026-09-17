<?php

namespace App\Support\Clinical;

use App\Models\Tariff;
use App\Models\Visit;

class TariffResolver
{
    public function priceFor(Visit $visit, int $procedureId): string
    {
        $tariffs = Tariff::query()
            ->where('tenant_id', $visit->tenant_id)
            ->where('procedure_id', $procedureId)
            ->whereDate('effective_date', '<=', $visit->visit_date?->toDateString() ?? now()->toDateString())
            ->orderByDesc('effective_date')
            ->get();

        $best = null;
        $bestScore = -1;

        foreach ($tariffs as $tariff) {
            if ($tariff->branch_id !== null && (int) $tariff->branch_id !== (int) $visit->branch_id) {
                continue;
            }

            if ($tariff->payer_id !== null && (int) $tariff->payer_id !== (int) $visit->payer_id) {
                continue;
            }

            $score = 0;
            $score += $tariff->branch_id === null ? 1 : 4;
            $score += $tariff->payer_id === null ? 1 : 4;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $tariff;
            }
        }

        return $best?->price ?? '0.00';
    }
}
