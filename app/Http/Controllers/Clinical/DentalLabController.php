<?php

namespace App\Http\Controllers\Clinical;

use App\Enums\LabOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DentalLabOrder;
use App\Models\Visit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DentalLabController extends Controller
{
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

    public function store(Request $request, Visit $visit): RedirectResponse
    {
        $this->authorize('view', $visit);
        abort_unless($request->user()?->can('procedure.manage'), 403);

        $data = $request->validate([
            'work_type' => ['required', 'string', 'max:120'],
            'tooth_numbers' => ['nullable', 'string', 'max:80'],
            'shade' => ['nullable', 'string', 'max:40'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DentalLabOrder::query()->create([
            ...$data,
            'tenant_id' => $visit->tenant_id,
            'visit_id' => $visit->id,
            'status' => LabOrderStatus::Ordered,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Pesanan laboratorium gigi dicatat.');
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
}
