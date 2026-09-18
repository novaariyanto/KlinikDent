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
