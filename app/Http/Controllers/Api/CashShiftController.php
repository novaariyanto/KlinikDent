<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Billing\Concerns\ScopesBillingBranch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CloseShiftRequest;
use App\Http\Requests\Billing\OpenShiftRequest;
use App\Models\Branch;
use App\Models\CashierShift;
use App\Support\Billing\BillingException;
use App\Support\Billing\BillingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CashShiftController extends Controller
{
    use RespondsWithPage;
    use ScopesBillingBranch;

    public function __construct(protected BillingService $billing) {}

    public function openForm(Request $request): JsonResponse
    {
        $this->authorize('create', CashierShift::class);

        $user = $request->user();
        $current = $this->billing->currentShift($user);
        $active = $request->attributes->get('active_branch');

        return response()->json([
            'data' => [
                'current' => $current,
                'branches' => $this->assignedBranches($user)->map(fn (Branch $branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ])->values(),
                'default_branch_id' => $active instanceof Branch
                    ? (int) $active->id
                    : ($user?->branch_id ?: $this->assignedBranches($user)->first()?->id),
            ],
        ]);
    }

    public function open(OpenShiftRequest $request): JsonResponse
    {
        $branchId = (int) $request->validated('branch_id');
        $active = $request->attributes->get('active_branch');

        if ($active instanceof Branch && ! $request->filled('branch_id')) {
            $branchId = (int) $active->id;
        }

        try {
            $shift = $this->billing->openShift(
                $request->user(),
                $branchId,
                (string) $request->validated('opening_balance'),
            );
        } catch (BillingException $exception) {
            throw ValidationException::withMessages([
                'shift' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => $shift,
            'message' => 'Shift kasir dibuka.',
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        if (! $request->user()?->can('cash_shift.view')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $shift = $this->billing->currentShift($request->user())
            ?? CashierShift::query()
                ->where('cashier_id', $request->user()->id)
                ->latest('id')
                ->first();

        if (! $shift) {
            return response()->json([
                'data' => [
                    'shift' => null,
                    'payments' => [],
                    'cash_in' => '0.00',
                ],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0],
            ]);
        }

        $query = $shift->payments()->with(['invoice.patient'])->latest('id');
        $cashIn = $shift->cashReceived();

        $response = $this->paged($request, $query, fn ($payment) => [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'method' => $payment->method?->value,
            'invoice_number' => $payment->invoice?->number,
            'patient' => $payment->invoice?->patient?->name,
            'created_at' => $payment->created_at?->toIso8601String(),
        ]);

        $payload = $response->getData(true);
        $payload['data'] = [
            'shift' => $shift,
            'cash_in' => $cashIn,
            'payments' => $payload['data'],
        ];

        return response()->json($payload);
    }

    public function closeForm(Request $request): JsonResponse
    {
        if (! $request->user()?->can('cash_shift.manage')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $shift = $this->billing->currentShift($request->user());

        if (! $shift) {
            throw ValidationException::withMessages([
                'shift' => ['Tidak ada shift terbuka.'],
            ]);
        }

        $this->authorize('update', $shift);

        $cashIn = $shift->cashReceived();
        $system = bcadd((string) $shift->opening_balance, $cashIn, 2);

        return response()->json([
            'data' => [
                'shift' => $shift,
                'cash_in' => $cashIn,
                'system_balance' => $system,
            ],
        ]);
    }

    public function close(CloseShiftRequest $request): JsonResponse
    {
        $shift = $this->billing->currentShift($request->user());

        if (! $shift) {
            throw ValidationException::withMessages([
                'shift' => ['Tidak ada shift terbuka.'],
            ]);
        }

        $this->authorize('update', $shift);

        try {
            $closed = $this->billing->closeShift(
                $shift,
                $request->user(),
                (string) $request->validated('closing_balance'),
                $request->validated('close_notes'),
            );
        } catch (BillingException $exception) {
            throw ValidationException::withMessages([
                'shift' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => $closed,
            'message' => 'Shift ditutup. Selisih '.$closed->variance.'.',
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        if (! $request->user()?->can('report.view') && ! $request->user()?->can('cash_shift.view')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $query = CashierShift::query()->with(['cashier', 'branch']);
        $active = $request->attributes->get('active_branch');
        $preferred = $active instanceof Branch ? (int) $active->id : null;
        $this->constrainBranch($query, $request->user(), 'branch_id', $preferred);
        $query->latest('id');

        return $this->paged($request, $query, fn (CashierShift $shift) => [
            'id' => $shift->id,
            'status' => $shift->status?->value,
            'opening_balance' => $shift->opening_balance,
            'closing_balance' => $shift->closing_balance,
            'variance' => $shift->variance,
            'opened_at' => $shift->opened_at?->toIso8601String(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
            'cashier' => $shift->cashier?->name,
            'branch' => $shift->branch?->name,
        ]);
    }
}
