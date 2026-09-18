<?php

namespace App\Http\Controllers\Pharmacy;

use App\Enums\PrescriptionStatus;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Pharmacy\Concerns\ScopesPharmacyBranch;
use App\Models\MedicineStock;
use App\Models\Prescription;
use App\Models\PurchaseOrder;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyDashboardController extends Controller
{
    use ScopesPharmacyBranch;

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('pharmacy.view'), 403);

        $user = $request->user();
        $incoming = Prescription::query()
            ->where('status', PrescriptionStatus::Sent);
        $this->constrainRelatedBranch($incoming, $user);
        $incoming = $incoming->count();

        $expired = MedicineStock::query()->expired();
        $this->constrainBranch($expired, $user);
        $expired = $expired->count();

        $expiring = MedicineStock::query()->expiring(PharmacyStockService::EXPIRING_DAYS);
        $this->constrainBranch($expiring, $user);
        $expiring = $expiring->count();

        $pendingOrders = PurchaseOrder::query()->where('status', PurchaseOrderStatus::Ordered);
        $this->constrainBranch($pendingOrders, $user);
        $pendingOrders = $pendingOrders->count();

        $lowStock = MedicineStock::query()->usable();
        $this->constrainBranch($lowStock, $user);
        $lowStock = $lowStock
            ->selectRaw('medicine_id, SUM(quantity) as qty')
            ->groupBy('medicine_id')
            ->havingRaw('SUM(quantity) < 20')
            ->get()
            ->count();

        return view('pharmacy.dashboard', compact('incoming', 'expired', 'expiring', 'pendingOrders', 'lowStock'));
    }
}
