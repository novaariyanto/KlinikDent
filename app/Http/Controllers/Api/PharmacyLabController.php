<?php

namespace App\Http\Controllers\Api;

use App\Enums\LabOrderStatus;
use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Controller;
use App\Models\DentalLabOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PharmacyLabController extends Controller
{
    use RespondsWithPage;

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->can('pharmacy.view') && ! $request->user()?->can('procedure.view')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $query = DentalLabOrder::query()
            ->with(['visit.patient', 'visit.doctor'])
            ->latest('id');

        $response = $this->paged($request, $query, fn (DentalLabOrder $order) => [
            'id' => $order->id,
            'work_type' => $order->work_type,
            'tooth_numbers' => $order->tooth_numbers,
            'shade' => $order->shade,
            'vendor' => $order->vendor,
            'due_date' => $order->due_date?->format('Y-m-d'),
            'status' => $order->status?->value,
            'status_label' => $order->status?->label(),
            'patient' => $order->visit?->patient?->name,
            'doctor' => $order->visit?->doctor?->name,
            'visit_id' => $order->visit_id,
            'created_at' => $order->created_at?->toIso8601String(),
        ]);

        $payload = $response->getData(true);
        $payload['statuses'] = collect(LabOrderStatus::cases())->map(fn (LabOrderStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ])->values();

        return response()->json($payload);
    }

    public function update(Request $request, DentalLabOrder $order): JsonResponse
    {
        if (! $request->user()?->can('pharmacy.view') && ! $request->user()?->can('procedure.manage')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $data = $request->validate([
            'status' => ['required', Rule::enum(LabOrderStatus::class)],
        ]);

        $order->update(['status' => $data['status']]);

        return response()->json([
            'data' => $order->fresh(),
            'message' => 'Status pesanan lab diperbarui.',
        ]);
    }
}
