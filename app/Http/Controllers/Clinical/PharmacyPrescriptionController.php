<?php

namespace App\Http\Controllers\Clinical;

use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Pharmacy\Concerns\ScopesPharmacyBranch;
use App\Models\Prescription;
use App\Models\User;
use App\Support\Pharmacy\InsufficientStockException;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyPrescriptionController extends Controller
{
    use ScopesPharmacyBranch;

    public function __construct(protected PharmacyStockService $stocks)
    {
    }

    public function incoming(Request $request): View
    {
        return $this->list($request, PrescriptionStatus::Sent, 'Resep Masuk', 'Resep yang sudah dikirim dokter dan menunggu proses farmasi.');
    }

    public function processing(Request $request): View
    {
        return $this->list($request, PrescriptionStatus::Sent, 'Antrian Proses', 'Resep masuk yang siap diproses (stok dicek saat penyerahan).');
    }

    public function completed(Request $request): View
    {
        return $this->list($request, PrescriptionStatus::Fulfilled, 'Resep Selesai', 'Resep yang sudah diserahkan dan stoknya dipotong.');
    }

    public function history(Request $request): View
    {
        return $this->list($request, PrescriptionStatus::Fulfilled, 'Riwayat Resep', 'Seluruh resep yang sudah dipenuhi farmasi.');
    }

    public function show(Request $request, Prescription $prescription): View
    {
        $this->authorizePrescription($request->user(), $prescription, viewOnly: true);

        $prescription->load(['visit.patient', 'visit.branch', 'doctor', 'items.medicine']);

        $availability = [];

        if ($prescription->isSent() && $prescription->visit) {
            foreach ($prescription->items as $item) {
                $available = $this->stocks->availableQuantity(
                    (int) $prescription->visit->tenant_id,
                    (int) $prescription->visit->branch_id,
                    (int) $item->medicine_id,
                );
                $allocations = $this->stocks->previewFefo(
                    (int) $prescription->visit->tenant_id,
                    (int) $prescription->visit->branch_id,
                    (int) $item->medicine_id,
                    (int) $item->quantity,
                );

                $availability[$item->id] = [
                    'available' => $available,
                    'enough' => $available >= (int) $item->quantity,
                    'allocations' => $allocations,
                ];
            }
        }

        return view('clinical.pharmacy.show', compact('prescription', 'availability'));
    }

    public function fulfill(Request $request, Prescription $prescription): RedirectResponse
    {
        abort_unless(
            $request->user()?->can('prescription.update')
            && ($request->user()?->can('pharmacy.manage') || $request->user()?->can('stock.manage')),
            403
        );
        $this->authorizePrescription($request->user(), $prescription, viewOnly: false);
        abort_unless($prescription->isSent(), 422, 'Resep belum bisa diproses.');

        try {
            $this->stocks->fulfill($prescription, $request->user());
        } catch (InsufficientStockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('pharmacy.prescriptions.show', $prescription)
            ->with('success', 'Resep diserahkan. Stok dipotong menurut FEFO.');
    }

    protected function list(Request $request, PrescriptionStatus $status, string $title, string $hint): View
    {
        abort_unless($request->user()?->can('prescription.view') && $request->user()?->can('pharmacy.view'), 403);

        $user = $request->user();
        $branchId = $this->restrictedBranchId($user);

        $prescriptions = Prescription::query()
            ->where('status', $status)
            ->when($branchId, fn ($query) => $query->whereHas('visit', fn ($visit) => $visit->where('branch_id', $branchId)))
            ->with(['visit.patient', 'visit.doctor', 'visit.branch', 'doctor', 'items.medicine'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('clinical.pharmacy.incoming', compact('prescriptions', 'title', 'hint', 'status'));
    }

    protected function authorizePrescription(?User $user, Prescription $prescription, bool $viewOnly): void
    {
        abort_unless($user?->can('prescription.view') && $user->can('pharmacy.view'), 403);

        $prescription->loadMissing('visit');

        abort_unless($user->belongsToTenantId($prescription->visit?->tenant_id), 403);

        $branchId = $this->restrictedBranchId($user);

        if ($branchId && (int) $prescription->visit?->branch_id !== $branchId) {
            abort(403);
        }

        if ($viewOnly) {
            abort_unless($prescription->isSent() || $prescription->isFulfilled(), 403);
        }
    }
}
