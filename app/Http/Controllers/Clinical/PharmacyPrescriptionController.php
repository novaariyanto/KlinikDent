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

    public function __construct(protected PharmacyStockService $stocks) {}

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

        $prescription->load([
            'visit.patient',
            'visit.branch',
            'doctor',
            'items.medicine',
            'items.ingredients.medicine',
        ]);

        $availability = [];
        $stockShort = false;

        if ($prescription->isSent() && $prescription->visit) {
            $tenantId = (int) $prescription->visit->tenant_id;
            $branchId = (int) $prescription->visit->branch_id;

            foreach ($prescription->items as $item) {
                $lines = [];

                foreach ($this->stockDemands($item) as $demand) {
                    if (! $demand['medicine_id']) {
                        $lines[] = [
                            'label' => $demand['label'],
                            'needed' => $demand['quantity'],
                            'unit' => $demand['unit'],
                            'tracked' => false,
                        ];

                        continue;
                    }

                    $available = $this->stocks->availableQuantity($tenantId, $branchId, $demand['medicine_id']);
                    $enough = $available >= $demand['quantity'];
                    $stockShort = $stockShort || ! $enough;

                    $lines[] = [
                        'label' => $demand['label'],
                        'needed' => $demand['quantity'],
                        'unit' => $demand['unit'],
                        'tracked' => true,
                        'available' => $available,
                        'enough' => $enough,
                        'allocations' => $this->stocks->previewFefo($tenantId, $branchId, $demand['medicine_id'], $demand['quantity']),
                    ];
                }

                $availability[$item->id] = $lines;
            }
        }

        return view('clinical.pharmacy.show', compact('prescription', 'availability', 'stockShort'));
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
            ->with('success', 'Resep diserahkan. Stok dipotong dan obat masuk tagihan.');
    }

    protected function list(Request $request, PrescriptionStatus $status, string $title, string $hint): View
    {
        abort_unless($request->user()?->can('prescription.view') && $request->user()?->can('pharmacy.view'), 403);

        $user = $request->user();
        $prescriptions = Prescription::query()
            ->where('status', $status);
        $this->constrainRelatedBranch($prescriptions, $user);
        $prescriptions = $prescriptions
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

        abort_unless($user->canAccessBranch((int) $prescription->visit?->branch_id), 403);

        if ($viewOnly) {
            abort_unless($prescription->isSent() || $prescription->isFulfilled(), 403);
        }
    }

    /**
     * @return list<array{label: string, medicine_id: int, quantity: int, unit: ?string}>
     */
    protected function stockDemands(\App\Models\PrescriptionItem $item): array
    {
        if ($item->isCompound()) {
            return $item->ingredients->map(fn ($ingredient) => [
                'label' => $ingredient->medicine?->name ?: $ingredient->name,
                'medicine_id' => (int) ($ingredient->medicine_id ?: 0),
                'quantity' => (int) $ingredient->quantity,
                'unit' => $ingredient->unit,
            ])->all();
        }

        if (! $item->medicine_id) {
            return [];
        }

        return [[
            'label' => $item->label(),
            'medicine_id' => (int) $item->medicine_id,
            'quantity' => (int) $item->quantity,
            'unit' => $item->medicine?->unitLabel(),
        ]];
    }
}
