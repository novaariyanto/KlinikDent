<?php

namespace App\Http\Controllers\Pharmacy;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Pharmacy\Concerns\ScopesPharmacyBranch;
use App\Models\MedicineStock;
use App\Models\StockMovement;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyReportController extends Controller
{
    use ScopesPharmacyBranch;

    public function landing(Request $request): View
    {
        abort_unless($request->user()?->can('report.view'), 403);

        return view('pharmacy.reports.landing');
    }

    public function stock(Request $request): View
    {
        abort_unless($request->user()?->can('report.view') || $request->user()?->can('stock.view'), 403);

        $stocks = $this->stockQuery($request)
            ->usable()
            ->with(['medicine', 'branch'])
            ->orderBy('expired_date')
            ->paginate(30)
            ->withQueryString();

        return view('pharmacy.stock.index', [
            'stocks' => $stocks,
            'title' => 'Laporan Stok',
            'hint' => 'Stok usable (belum kedaluwarsa, qty > 0) per batch dan cabang.',
        ]);
    }

    public function incoming(Request $request): View
    {
        return $this->movements($request, StockMovementType::In, 'Laporan Obat Masuk');
    }

    public function outgoing(Request $request): View
    {
        return $this->movements($request, StockMovementType::Out, 'Laporan Obat Keluar');
    }

    public function transactions(Request $request): View
    {
        abort_unless($request->user()?->can('pharmacy.view') || $request->user()?->can('stock.view'), 403);

        return $this->movements($request, null, 'Transaksi Farmasi');
    }

    public function expired(Request $request): View
    {
        abort_unless($request->user()?->can('report.view') || $request->user()?->can('stock.view'), 403);

        $days = PharmacyStockService::EXPIRING_DAYS;
        $stocks = $this->stockQuery($request)
            ->where(function ($query) use ($days) {
                $query->expired()->orWhere(function ($inner) use ($days) {
                    $inner->expiring($days);
                });
            })
            ->with(['medicine', 'branch'])
            ->orderBy('expired_date')
            ->paginate(30)
            ->withQueryString();

        return view('pharmacy.stock.expired', [
            'stocks' => $stocks,
            'days' => $days,
            'title' => 'Laporan Expired',
        ]);
    }

    protected function movements(Request $request, ?StockMovementType $type, string $title): View
    {
        abort_unless($request->user()?->can('report.view') || $request->user()?->can('pharmacy.view') || $request->user()?->can('stock.view'), 403);

        $branchId = $this->restrictedBranchId($request->user());

        $movements = StockMovement::query()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($branchId, fn ($query) => $query->whereHas('stock', fn ($stock) => $stock->where('branch_id', $branchId)))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->with(['stock.medicine', 'stock.branch', 'user'])
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('pharmacy.reports.movements', compact('movements', 'title', 'type'));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<MedicineStock>
     */
    protected function stockQuery(Request $request)
    {
        $query = MedicineStock::query();
        $this->constrainBranch($query, $request->user());

        return $query;
    }
}
