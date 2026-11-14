<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Pharmacy\Concerns\ScopesPharmacyBranch;
use App\Http\Requests\Pharmacy\StoreStockAdjustmentRequest;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\StockMovement;
use App\Support\Pharmacy\InsufficientStockException;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PharmacyStockController extends Controller
{
    use RespondsWithPage;
    use ScopesPharmacyBranch;

    public function __construct(protected PharmacyStockService $stocks) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicineStock::class);

        $query = $this->stockQuery($request)->usable()->with(['medicine', 'branch'])->orderBy('expired_date');

        return $this->paged($request, $query, fn (MedicineStock $stock) => $this->stockPayload($stock));
    }

    public function batches(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicineStock::class);

        $query = $this->stockQuery($request)->with(['medicine', 'branch'])->orderBy('expired_date');

        return $this->paged($request, $query, fn (MedicineStock $stock) => $this->stockPayload($stock));
    }

    public function expired(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicineStock::class);

        $days = PharmacyStockService::EXPIRING_DAYS;
        $query = $this->stockQuery($request)
            ->where(function ($inner) use ($days) {
                $inner->expired()->orWhere(function ($expiring) use ($days) {
                    $expiring->expiring($days);
                });
            })
            ->with(['medicine', 'branch'])
            ->orderBy('expired_date');

        return $this->paged($request, $query, fn (MedicineStock $stock) => $this->stockPayload($stock));
    }

    public function adjustments(Request $request): JsonResponse
    {
        $this->authorize('create', MedicineStock::class);

        $options = $this->stockQuery($request)
            ->with(['medicine', 'branch'])
            ->orderBy('id')
            ->get()
            ->map(function (MedicineStock $stock) {
                $medicineLabel = $stock->medicine?->catalogLabel(withUnit: false) ?? 'Obat';

                return [
                    'id' => $stock->id,
                    'label' => $medicineLabel.' / '.$stock->batch_number.' / stok '.$stock->quantity,
                    'branch_id' => $stock->branch_id,
                    'quantity' => $stock->quantity,
                ];
            })
            ->values();

        return response()->json(['data' => $options]);
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $stock = MedicineStock::query()->findOrFail($request->validated('medicine_stock_id'));
        $this->authorize('update', $stock);

        if (! $request->user()?->canAccessBranch((int) $stock->branch_id)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        try {
            $this->stocks->adjust(
                $stock,
                (int) $request->validated('delta'),
                $request->user(),
                $request->validated('notes'),
            );
        } catch (InsufficientStockException $exception) {
            throw ValidationException::withMessages([
                'stock' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => $this->stockPayload($stock->fresh(['medicine', 'branch'])),
            'message' => 'Stok berhasil disesuaikan.',
        ]);
    }

    public function card(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicineStock::class);

        $medicines = Medicine::query()->orderBy('name')->get(['id', 'name', 'unit', 'kfa_code']);

        return response()->json(['data' => ['medicines' => $medicines]]);
    }

    public function cardShow(Request $request, Medicine $medicine): JsonResponse
    {
        $this->authorize('viewAny', MedicineStock::class);

        $query = StockMovement::query()
            ->whereHas('stock', fn ($stock) => $stock->where('medicine_id', $medicine->id))
            ->with(['stock.branch', 'user'])
            ->latest('id');

        return $this->paged($request, $query, fn (StockMovement $movement) => [
            'id' => $movement->id,
            'type' => $movement->type?->value ?? $movement->type,
            'quantity' => $movement->quantity,
            'notes' => $movement->notes,
            'created_at' => $movement->created_at?->toIso8601String(),
            'branch' => $movement->stock?->branch?->name,
            'user' => $movement->user?->name,
            'batch_number' => $movement->stock?->batch_number,
        ]);
    }

    public function opname(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicineStock::class);

        $stocks = $this->stockQuery($request)->usable()->with(['medicine', 'branch'])->orderBy('expired_date')->get();

        return response()->json([
            'data' => $stocks->map(fn (MedicineStock $stock) => $this->stockPayload($stock))->values(),
        ]);
    }

    public function storeOpname(Request $request): JsonResponse
    {
        if (! $request->user()?->can('stock.manage')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'physical' => ['required', 'array'],
            'physical.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $grouped = [];

        foreach ($data['physical'] as $stockId => $qty) {
            if ($qty === null || $qty === '') {
                continue;
            }

            $stock = MedicineStock::query()->find($stockId);

            if (! $stock) {
                continue;
            }

            $grouped[$stock->branch_id][] = ['stock_id' => (int) $stock->id, 'physical_qty' => (int) $qty];
        }

        if ($grouped === []) {
            throw ValidationException::withMessages([
                'physical' => ['Isi qty fisik minimal satu batch.'],
            ]);
        }

        foreach ($grouped as $branchId => $lines) {
            $this->stocks->postOpname((int) $request->user()->tenant_id, (int) $branchId, $request->user(), $lines, $data['notes'] ?? null);
        }

        return response()->json([
            'data' => ['branches' => array_keys($grouped)],
            'message' => 'Opname stok diposting.',
        ]);
    }

    public function returns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MedicineStock::class);

        $stocks = $this->stockQuery($request)->with(['medicine', 'branch'])->orderBy('expired_date')->limit(200)->get();

        return response()->json([
            'data' => $stocks->map(fn (MedicineStock $stock) => $this->stockPayload($stock))->values(),
        ]);
    }

    public function storeReturn(Request $request): JsonResponse
    {
        if (! $request->user()?->can('stock.manage')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $data = $request->validate([
            'medicine_stock_id' => ['required', 'integer'],
            'direction' => ['required', 'in:supplier,patient'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['required', 'string', 'max:255'],
        ]);

        $stock = MedicineStock::query()->findOrFail($data['medicine_stock_id']);
        $this->authorize('update', $stock);

        try {
            $this->stocks->returnStock($stock, $data['direction'], (int) $data['quantity'], $request->user(), $data['notes']);
        } catch (InsufficientStockException $exception) {
            throw ValidationException::withMessages([
                'stock' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => $this->stockPayload($stock->fresh(['medicine', 'branch'])),
            'message' => 'Retur stok tercatat.',
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

    /**
     * @return array<string, mixed>
     */
    protected function stockPayload(MedicineStock $stock): array
    {
        return [
            'id' => $stock->id,
            'batch_number' => $stock->batch_number,
            'quantity' => $stock->quantity,
            'expired_date' => $stock->expired_date?->format('Y-m-d'),
            'medicine_id' => $stock->medicine_id,
            'medicine' => $stock->medicine?->name,
            'unit' => $stock->medicine?->unitLabel(),
            'branch_id' => $stock->branch_id,
            'branch' => $stock->branch?->name,
        ];
    }
}
