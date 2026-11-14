<?php

namespace App\Http\Controllers\Clinical;

use App\Enums\LabOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DentalLabOrder;
use App\Models\Visit;
use App\Support\Clinical\ClinicalCareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DentalLabController extends Controller
{
    public function __construct(protected ClinicalCareService $care) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('pharmacy.view') || $request->user()?->can('procedure.view'), 403);

        $orders = DentalLabOrder::query()
            ->with(['visit.patient', 'visit.doctor'])
            ->latest('id')
            ->paginate(20);

        return view('pharmacy.lab.index', [
            'orders' => $orders,
            'statuses' => LabOrderStatus::cases(),
        ]);
    }

    public function store(Request $request, Visit $visit): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        $user = $request->user();
        abort_unless($user?->can('procedure.manage'), 403);

        $order = $this->care->storeLabOrder($visit, $user, $request->all());

        return $this->respondLab($visit, 'Pesanan penunjang dicatat.', $order);
    }

    public function updateOrder(Request $request, Visit $visit, DentalLabOrder $order): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless($request->user()?->can('procedure.manage'), 403);

        $order = $this->care->updateLabOrder($visit, $order, $request->all());

        return $this->respondLab($visit, 'Pesanan penunjang diperbarui.', $order);
    }

    public function destroy(Request $request, Visit $visit, DentalLabOrder $order): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $visit);
        abort_unless($request->user()?->can('procedure.manage'), 403);

        $orderId = $this->care->destroyLabOrder($visit, $order);

        return $this->respondLab($visit, 'Pesanan penunjang dihapus.', null, $orderId);
    }

    public function update(Request $request, DentalLabOrder $order): RedirectResponse
    {
        abort_unless($request->user()?->can('pharmacy.view') || $request->user()?->can('procedure.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(LabOrderStatus::class)],
        ]);

        $order->update(['status' => $data['status']]);

        return back()->with('success', 'Status pesanan lab diperbarui.');
    }

    private function backToSoap(Visit $visit, string $message): RedirectResponse
    {
        return redirect()
            ->route('care.show', ['visit' => $visit, 'tab' => 'soap'])
            ->with('success', $message);
    }

    private function respondLab(Visit $visit, string $message, ?DentalLabOrder $order = null, ?int $deletedId = null): RedirectResponse|JsonResponse
    {
        if (! request()->ajax() && ! request()->wantsJson()) {
            return $this->backToSoap($visit, $message);
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'deleted_id' => $deletedId,
            'order' => $order ? [
                'id' => $order->id,
                'work_type' => $order->work_type,
                'tooth_numbers' => $order->tooth_numbers,
                'shade' => $order->shade,
                'vendor' => $order->vendor,
                'due_date' => $order->due_date?->format('Y-m-d'),
                'due_label' => $order->due_date?->format('d M Y'),
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'status_class' => $order->status->badgeClass(),
                'update_url' => route('care.lab.update', [$visit, $order]),
                'destroy_url' => route('care.lab.destroy', [$visit, $order]),
            ] : null,
        ]);
    }
}
