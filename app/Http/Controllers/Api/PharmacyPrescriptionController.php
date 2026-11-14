<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrescriptionStatus;
use App\Exceptions\MobileApiException;
use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Pharmacy\Concerns\ScopesPharmacyBranch;
use App\Models\Branch;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Support\Clinical\PrintChrome;
use App\Support\Pharmacy\InsufficientStockException;
use App\Support\Pharmacy\PharmacyStockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PharmacyPrescriptionController extends Controller
{
    use RespondsWithPage;
    use ScopesPharmacyBranch;

    public function __construct(protected PharmacyStockService $stocks) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertCanView($request->user());

        $scope = (string) $request->query('scope', 'incoming');
        $status = match ($scope) {
            'completed', 'history' => PrescriptionStatus::Fulfilled,
            default => PrescriptionStatus::Sent,
        };

        $query = Prescription::query()->where('status', $status);
        $active = $request->attributes->get('active_branch');
        $preferred = $active instanceof Branch ? (int) $active->id : null;
        $this->constrainRelatedBranch($query, $request->user(), 'visit', 'branch_id', $preferred);
        $query->with(['visit.patient', 'visit.doctor', 'visit.branch', 'doctor', 'items.medicine'])
            ->latest('id');

        return $this->paged($request, $query, fn (Prescription $prescription) => [
            'id' => $prescription->id,
            'status' => $prescription->status?->value,
            'sent_at' => $prescription->sent_at?->toIso8601String(),
            'fulfilled_at' => $prescription->fulfilled_at?->toIso8601String(),
            'patient' => $prescription->visit?->patient?->name,
            'doctor' => $prescription->doctor?->name ?? $prescription->visit?->doctor?->name,
            'branch' => $prescription->visit?->branch?->name,
            'item_count' => $prescription->items->count(),
            'visit_id' => $prescription->visit_id,
        ]);
    }

    public function show(Request $request, Prescription $prescription): JsonResponse
    {
        $this->authorizePrescription($request->user(), $prescription, viewOnly: true);

        $prescription->load([
            'visit.patient',
            'visit.branch',
            'visit.invoices',
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

        return response()->json([
            'data' => [
                'prescription' => $prescription,
                'availability' => $availability,
                'stock_short' => $stockShort,
            ],
        ]);
    }

    public function fulfill(Request $request, Prescription $prescription): JsonResponse
    {
        $user = $request->user();

        if (! $user?->can('prescription.update')
            || (! $user->can('pharmacy.manage') && ! $user->can('stock.manage'))) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $this->authorizePrescription($user, $prescription, viewOnly: false);

        if (! $prescription->isSent()) {
            throw ValidationException::withMessages([
                'prescription' => ['Resep belum bisa diproses.'],
            ]);
        }

        try {
            $this->stocks->fulfill($prescription, $user);
        } catch (InsufficientStockException $exception) {
            throw ValidationException::withMessages([
                'stock' => [$exception->getMessage()],
            ]);
        }

        $prescription->refresh()->load(['visit.patient', 'items.medicine']);

        return response()->json([
            'data' => $prescription,
            'message' => 'Resep diserahkan. Stok dipotong dan obat masuk tagihan.',
        ]);
    }

    public function printLabels(Request $request, Prescription $prescription): Response
    {
        $this->authorizePrescription($request->user(), $prescription, viewOnly: true);

        $prescription->load([
            'visit.patient',
            'visit.branch',
            'items.medicine',
        ]);

        if ($prescription->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Resep belum memiliki item obat.'],
            ]);
        }

        $visit = $prescription->visit;
        $chrome = PrintChrome::variables((int) $visit->tenant_id, $visit->branch);

        activity_log('printed', $prescription, ['scope' => 'all_labels'], 'Cetak semua label obat farmasi', 'prescriptions');

        return Pdf::loadView('clinical.pharmacy.print.labels', [
            'clinicName' => $chrome['clinicName'],
            'patientName' => (string) ($visit->patient?->name ?: '-'),
            'items' => $prescription->items,
        ])->setPaper('a6')->stream('label-resep-'.$prescription->id.'.pdf');
    }

    public function printItemLabel(Request $request, Prescription $prescription, PrescriptionItem $item): Response
    {
        $this->authorizePrescription($request->user(), $prescription, viewOnly: true);

        if ((int) $item->prescription_id !== (int) $prescription->id) {
            abort(404);
        }

        $prescription->load(['visit.patient', 'visit.branch']);
        $item->loadMissing('medicine');

        $visit = $prescription->visit;
        $chrome = PrintChrome::variables((int) $visit->tenant_id, $visit->branch);

        activity_log('printed', $item, ['prescription_id' => $prescription->id], 'Cetak label obat farmasi', 'prescriptions');

        return Pdf::loadView('clinical.pharmacy.print.label', [
            'clinicName' => $chrome['clinicName'],
            'patientName' => (string) ($visit->patient?->name ?: '-'),
            'item' => $item,
        ])->setPaper('a6')->stream('label-resep-'.$prescription->id.'-item-'.$item->id.'.pdf');
    }

    protected function assertCanView(?User $user): void
    {
        if (! $user?->can('prescription.view') || ! $user->can('pharmacy.view')) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    protected function authorizePrescription(?User $user, Prescription $prescription, bool $viewOnly): void
    {
        $this->assertCanView($user);

        $prescription->loadMissing('visit');

        if (! $user->belongsToTenantId($prescription->visit?->tenant_id)) {
            abort(404);
        }

        if (! $user->canAccessBranch((int) $prescription->visit?->branch_id)) {
            throw new MobileApiException('Cabang tidak dapat diakses.', 'branch_forbidden', 403);
        }

        if ($viewOnly && ! $prescription->isSent() && ! $prescription->isFulfilled()) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    /**
     * @return list<array{label: string, medicine_id: int, quantity: int, unit: ?string}>
     */
    protected function stockDemands(PrescriptionItem $item): array
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
