<?php

namespace App\Http\Controllers\Finance\Concerns;

use App\Http\Controllers\Concerns\ScopesAssignedBranch;
use Illuminate\Http\Request;

trait ScopesFinanceBranch
{
    use ScopesAssignedBranch;

    protected function periodFrom(Request $request, ?string $fallback = null): string
    {
        return $request->date('date_from')?->toDateString()
            ?? $fallback
            ?? now()->startOfMonth()->toDateString();
    }

    protected function periodTo(Request $request, ?string $fallback = null): string
    {
        return $request->date('date_to')?->toDateString()
            ?? $fallback
            ?? now()->toDateString();
    }

    /**
     * @return array{0: string, 1: string, 2: int|null}
     */
    protected function filters(Request $request): array
    {
        return [
            $this->periodFrom($request),
            $this->periodTo($request),
            $this->selectedBranchId($request->user(), $request->integer('branch_id') ?: null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterViewData(Request $request, string $from, string $to, ?int $branchId): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => $this->assignedBranches($request->user()),
        ];
    }
}
