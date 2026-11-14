<?php

namespace App\Http\Controllers\Api;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Pharmacy\Concerns\ScopesPharmacyBranch;
use App\Http\Requests\Pharmacy\ReceivePurchaseOrderRequest;
use App\Http\Requests\Pharmacy\StorePurchaseOrderRequest;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PharmacyOrderController extends Controller
{
    use RespondsWithPage;
    use ScopesPharmacyBranch;

    public function __construct(protected PharmacyStockService $stocks) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $query = $this->orderQuery($request)->with(['supplier', 'branch', 'items'])->latest('id');

        return $this->paged($request, $query, fn (PurchaseOrder $order) => $this->summary($order));
    }

    public function receipts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $query = $this->orderQuery($request)
            ->whereIn('status', [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Received])
            ->with(['supplier', 'branch', 'items'])
            ->latest('id');

        return $this->paged($request, $query, fn (PurchaseOrder $order) => $this->summary($order));
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $active = $request->attributes->get('active_branch');
        if ($active instanceof Branch && empty($data['branch_id'])) {
            $data['branch_id'] = $active->id;
        }

        $data['tenant_id'] = $request->user()?->tenant_id;
        $data['created_by'] = $request->user()?->id;
        $data['status'] = PurchaseOrderStatus::Draft;
        $data['number'] = $this->nextNumber((int) $data['tenant_id']);

        $order = PurchaseOrder::query()->create($data);

        foreach ($items as $item) {
            $order->items()->create($item);
        }

        activity_log('created', $order, $data, 'PO '.$order->number.' dibuat.', 'pharmacy');

        return response()->json([
            'data' => $order->load(['items', 'supplier', 'branch']),
            'message' => 'Purchase order disimpan sebagai draft.',
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);
        $purchaseOrder->load(['supplier', 'branch', 'items.medicine', 'creator']);

        return response()->json([
            'data' => $purchaseOrder,
            'form' => [
                'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
                'medicines' => Medicine::query()->active()->orderBy('name')->get(['id', 'name', 'unit', 'base_price']),
            ],
        ]);
    }

    public function submit(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        if (! $purchaseOrder->isDraft() || ! $purchaseOrder->items()->exists()) {
            throw ValidationException::withMessages([
                'order' => ['PO draft harus memiliki item.'],
            ]);
        }

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Ordered]);
        activity_log('ordered', $purchaseOrder, [], 'PO '.$purchaseOrder->number.' dipesan.', 'pharmacy');

        return response()->json([
            'data' => $purchaseOrder->fresh(),
            'message' => 'PO dikirim ke pemasok (status dipesan).',
        ]);
    }

    public function receiveForm(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        if (! $purchaseOrder->isOrdered()) {
            throw ValidationException::withMessages([
                'order' => ['Hanya PO dipesan yang dapat diterima.'],
            ]);
        }

        $purchaseOrder->load(['supplier', 'branch', 'items.medicine']);

        return response()->json(['data' => $purchaseOrder]);
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $receipts = [];

        foreach ($request->validated('items') as $itemId => $payload) {
            $receipts[(int) $itemId] = $payload;
        }

        try {
            $this->stocks->receive($purchaseOrder, $request->user(), $receipts);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'receive' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'data' => $purchaseOrder->fresh(['items', 'supplier']),
            'message' => 'Barang diterima dan stok bertambah.',
        ]);
    }

    /**
     * @return Builder<PurchaseOrder>
     */
    protected function orderQuery(Request $request): Builder
    {
        $query = PurchaseOrder::query();
        $active = $request->attributes->get('active_branch');
        $preferred = $active instanceof Branch ? (int) $active->id : null;
        $this->constrainBranch($query, $request->user(), 'branch_id', $preferred);

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('number', 'like', $term)
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $term));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status?->value,
            'supplier' => $order->supplier?->name,
            'branch' => $order->branch?->name,
            'item_count' => $order->items->count(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    protected function nextNumber(int $tenantId): string
    {
        $year = now()->format('Y');
        $count = PurchaseOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('number', 'like', 'PO-'.$year.'-%')
            ->count() + 1;

        return sprintf('PO-%s-%05d', $year, $count);
    }
}
