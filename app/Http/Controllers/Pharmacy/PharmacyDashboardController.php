<?php

namespace App\Http\Controllers\Pharmacy;

use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Pharmacy\Concerns\ScopesPharmacyBranch;
use App\Models\MedicineStock;
use App\Models\Prescription;
use App\Enums\PurchaseOrderStatus;
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
        $branchId = $this->restrictedBranchId($user);

        $incoming = Prescription::query()
            ->where('status', PrescriptionStatus::Sent)
            ->when($branchId, fn ($query) => $query->whereHas('visit', fn ($visit) => $visit->where('branch_id', $branchId)))
            ->count();

        $expired = MedicineStock::query()
            ->expired()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->count();

        $expiring = MedicineStock::query()
            ->expiring(PharmacyStockService::EXPIRING_DAYS)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->count();

        $pendingOrders = PurchaseOrder::query()
            ->where('status', PurchaseOrderStatus::Ordered)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->count();

        $lowStock = MedicineStock::query()
            ->usable()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw('medicine_id, SUM(quantity) as qty')
            ->groupBy('medicine_id')
            ->havingRaw('SUM(quantity) < 20')
            ->get()
            ->count();

        return view('pharmacy.dashboard', compact('incoming', 'expired', 'expiring', 'pendingOrders', 'lowStock'));
    }
}
