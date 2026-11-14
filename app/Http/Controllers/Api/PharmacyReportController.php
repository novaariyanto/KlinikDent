<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMovementType;
use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Pharmacy\Concerns\ScopesPharmacyBranch;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\StockMovement;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmacyReportController extends Controller
{
    use RespondsWithPage;
    use ScopesPharmacyBranch;

    public function medicines(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Medicine::class);

        $query = Medicine::query()->orderBy('name');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('name', 'like', $term)->orWhere('kfa_code', 'like', $term);
            });
        }

        return $this->paged($request, $query, fn (Medicine $medicine) => [
            'id' => $medicine->id,
            'name' => $medicine->name,
            'kfa_code' => $medicine->kfa_code,
            'unit' => $medicine->unitLabel(),
            'base_price' => $medicine->base_price,
            'is_active' => (bool) $medicine->is_active,
        ]);
    }

    public function stock(Request $request): JsonResponse
    {
        $this->assertReport($request);

        $query = $this->stockQuery($request)->usable()->with(['medicine', 'branch'])->orderBy('expired_date');

        return $this->paged($request, $query, fn (MedicineStock $stock) => [
            'id' => $stock->id,
            'medicine' => $stock->medicine?->name,
            'batch_number' => $stock->batch_number,
            'quantity' => $stock->quantity,
            'expired_date' => $stock->expired_date?->format('Y-m-d'),
            'branch' => $stock->branch?->name,
        ]);
    }

    public function incoming(Request $request): JsonResponse
    {
        return $this->movements($request, StockMovementType::In);
    }

    public function outgoing(Request $request): JsonResponse
    {
        return $this->movements($request, StockMovementType::Out);
    }

    public function transactions(Request $request): JsonResponse
    {
        if (! $request->user()?->can('pharmacy.view') && ! $request->user()?->can('stock.view')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return $this->movements($request, null);
    }

    public function expired(Request $request): JsonResponse
    {
        $this->assertReport($request);

        $days = PharmacyStockService::EXPIRING_DAYS;
        $query = $this->stockQuery($request)
            ->where(function ($builder) use ($days) {
                $builder->expired()->orWhere(fn ($inner) => $inner->expiring($days));
            })
            ->with(['medicine', 'branch'])
            ->orderBy('expired_date');

        return $this->paged($request, $query, fn (MedicineStock $stock) => [
            'id' => $stock->id,
            'medicine' => $stock->medicine?->name,
            'batch_number' => $stock->batch_number,
            'quantity' => $stock->quantity,
            'expired_date' => $stock->expired_date?->format('Y-m-d'),
            'branch' => $stock->branch?->name,
        ]);
    }

    protected function movements(Request $request, ?StockMovementType $type): JsonResponse
    {
        $this->assertReport($request, allowPharmacy: true);

        $query = StockMovement::query()
            ->when($type, fn ($builder) => $builder->where('type', $type))
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('created_at', '<=', $request->date('date_to')))
            ->with(['stock.medicine', 'stock.branch', 'user'])
            ->latest('id');

        $active = $request->attributes->get('active_branch');
        $preferred = $active instanceof Branch ? (int) $active->id : null;
        $this->constrainRelatedBranch($query, $request->user(), 'stock', 'branch_id', $preferred);

        return $this->paged($request, $query, fn (StockMovement $movement) => [
            'id' => $movement->id,
            'type' => $movement->type?->value ?? $movement->type,
            'quantity' => $movement->quantity,
            'notes' => $movement->notes,
            'medicine' => $movement->stock?->medicine?->name,
            'batch_number' => $movement->stock?->batch_number,
            'branch' => $movement->stock?->branch?->name,
            'user' => $movement->user?->name,
            'created_at' => $movement->created_at?->toIso8601String(),
        ]);
    }

    /**
     * @return Builder<MedicineStock>
     */
    protected function stockQuery(Request $request): Builder
    {
        $query = MedicineStock::query();
        $active = $request->attributes->get('active_branch');
        $preferred = $active instanceof Branch ? (int) $active->id : null;
        $this->constrainBranch($query, $request->user(), 'branch_id', $preferred);

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('batch_number', 'like', $term)
                    ->orWhereHas('medicine', function ($medicine) use ($term) {
                        $medicine->where('name', 'like', $term)
                            ->orWhere('kfa_code', 'like', $term);
                    });
            });
        }

        return $query;
    }

    protected function assertReport(Request $request, bool $allowPharmacy = false): void
    {
        $ok = $request->user()?->can('report.view') || $request->user()?->can('stock.view');

        if ($allowPharmacy) {
            $ok = $ok || $request->user()?->can('pharmacy.view');
        }

        if (! $ok) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }
}
