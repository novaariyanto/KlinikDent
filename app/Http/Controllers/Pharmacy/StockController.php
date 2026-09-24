<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Pharmacy\Concerns\ScopesPharmacyBranch;
use App\Http\Requests\Pharmacy\StoreStockAdjustmentRequest;
use App\Models\MedicineStock;
use App\Support\Pharmacy\InsufficientStockException;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    use ScopesPharmacyBranch;

    public function __construct(protected PharmacyStockService $stocks) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MedicineStock::class);

        $stocks = $this->stockQuery($request)
            ->usable()
            ->with(['medicine', 'branch'])
            ->orderBy('expired_date')
            ->paginate(20)
            ->withQueryString();

        return view('pharmacy.stock.index', [
            'stocks' => $stocks,
            'title' => 'Stok Obat',
            'hint' => 'Stok aktif per batch (FEFO). Batch kedaluwarsa tidak dipakai saat meracik resep.',
        ]);
    }

    public function batches(Request $request): View
    {
        $this->authorize('viewAny', MedicineStock::class);

        $stocks = $this->stockQuery($request)
            ->with(['medicine', 'branch'])
            ->orderBy('expired_date')
            ->paginate(20)
            ->withQueryString();

        return view('pharmacy.stock.index', [
            'stocks' => $stocks,
            'title' => 'Batch Obat',
            'hint' => 'Semua batch termasuk yang sudah kedaluwarsa atau stok 0.',
        ]);
    }

    public function expired(Request $request): View
    {
        $this->authorize('viewAny', MedicineStock::class);

        $days = PharmacyStockService::EXPIRING_DAYS;
        $expired = $this->stockQuery($request)
            ->where(function ($query) use ($days) {
                $query->expired()->orWhere(function ($inner) use ($days) {
                    $inner->expiring($days);
                });
            })
            ->with(['medicine', 'branch'])
            ->orderBy('expired_date')
            ->paginate(20)
            ->withQueryString();

        return view('pharmacy.stock.expired', [
            'stocks' => $expired,
            'days' => $days,
            'title' => 'Obat Kedaluwarsa',
        ]);
    }

    public function adjustments(Request $request): View
    {
        $this->authorize('create', MedicineStock::class);

        $options = $this->stockQuery($request)
            ->with(['medicine', 'branch'])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (MedicineStock $stock) {
                $label = ($stock->medicine?->name ?? 'Obat').' / '.$stock->batch_number.' / stok '.$stock->quantity;

                return [$stock->id => $label];
            })
            ->all();

        return view('pharmacy.stock.adjust', compact('options'));
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        $stock = MedicineStock::query()->findOrFail($request->validated('medicine_stock_id'));
        $this->authorize('update', $stock);

        abort_unless($request->user()?->canAccessBranch((int) $stock->branch_id), 403);

        try {
            $this->stocks->adjust(
                $stock,
                (int) $request->validated('delta'),
                $request->user(),
                $request->validated('notes'),
            );
        } catch (InsufficientStockException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('pharmacy.stock.index')->with('success', 'Stok berhasil disesuaikan.');
    }

    public function card(Request $request): View
    {
        $this->authorize('viewAny', MedicineStock::class);

        $medicines = \App\Models\Medicine::query()->orderBy('name')->get();

        return view('pharmacy.stock.card', [
            'medicines' => $medicines,
            'movements' => collect(),
            'medicine' => null,
        ]);
    }

    public function cardShow(Request $request, \App\Models\Medicine $medicine): View
    {
        $this->authorize('viewAny', MedicineStock::class);

        $movements = \App\Models\StockMovement::query()
            ->whereHas('stock', fn ($query) => $query->where('medicine_id', $medicine->id))
            ->with(['stock.branch', 'user'])
            ->latest('id')
            ->paginate(30);

        return view('pharmacy.stock.card', [
            'medicines' => \App\Models\Medicine::query()->orderBy('name')->get(),
            'movements' => $movements,
            'medicine' => $medicine,
        ]);
    }

    public function opname(Request $request): View
    {
        $this->authorize('viewAny', MedicineStock::class);

        $stocks = $this->stockQuery($request)->usable()->with(['medicine', 'branch'])->orderBy('expired_date')->get();

        return view('pharmacy.stock.opname', compact('stocks'));
    }

    public function storeOpname(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('stock.manage'), 403);

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
            return back()->with('error', 'Isi qty fisik minimal satu batch.');
        }

        foreach ($grouped as $branchId => $lines) {
            $this->stocks->postOpname((int) $request->user()->tenant_id, (int) $branchId, $request->user(), $lines, $data['notes'] ?? null);
        }

        return redirect()->route('pharmacy.stock.opname')->with('success', 'Opname stok diposting.');
    }

    public function returns(Request $request): View
    {
        $this->authorize('viewAny', MedicineStock::class);

        $stocks = $this->stockQuery($request)->with(['medicine', 'branch'])->orderBy('expired_date')->limit(200)->get();

        return view('pharmacy.stock.returns', compact('stocks'));
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('stock.manage'), 403);

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
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('pharmacy.stock.returns')->with('success', 'Retur stok tercatat.');
    }

    /**
     * @return Builder<MedicineStock>
     */
    protected function stockQuery(Request $request)
    {
        $query = MedicineStock::query();
        $this->constrainBranch($query, $request->user());

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('batch_number', 'like', $term)
                    ->orWhereHas('medicine', fn ($medicine) => $medicine->where('name', 'like', $term));
            });
        }

        return $query;
    }
}
