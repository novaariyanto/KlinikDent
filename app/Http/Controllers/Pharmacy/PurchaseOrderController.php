<?php

namespace App\Http\Controllers\Pharmacy;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Pharmacy\Concerns\ScopesPharmacyBranch;
use App\Http\Requests\Pharmacy\ReceivePurchaseOrderRequest;
use App\Http\Requests\Pharmacy\StorePurchaseOrderRequest;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PurchaseOrderController extends Controller
{
    use ScopesPharmacyBranch;

    public function __construct(protected PharmacyStockService $stocks)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = $this->orderQuery($request)
            ->with(['supplier', 'branch', 'items'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('pharmacy.orders.index', [
            'orders' => $orders,
            'title' => 'Purchase Order',
            'hint' => 'Draft → dipesan → diterima. Penerimaan menambah stok per batch.',
            'showCreate' => true,
        ]);
    }

    public function receipts(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = $this->orderQuery($request)
            ->whereIn('status', [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Received])
            ->with(['supplier', 'branch', 'items'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('pharmacy.orders.index', [
            'orders' => $orders,
            'title' => 'Penerimaan Barang',
            'hint' => 'PO yang menunggu penerimaan atau sudah diterima.',
            'showCreate' => false,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PurchaseOrder::class);

        return view('pharmacy.orders.form', $this->formData($request));
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $data['tenant_id'] = $request->user()?->tenant_id;
        $data['created_by'] = $request->user()?->id;
        $data['status'] = PurchaseOrderStatus::Draft;
        $data['number'] = $this->nextNumber((int) $data['tenant_id']);

        $order = PurchaseOrder::query()->create($data);

        foreach ($items as $item) {
            $order->items()->create($item);
        }

        activity_log('created', $order, $data, 'PO '.$order->number.' dibuat.', 'pharmacy');

        return redirect()->route('pharmacy.orders.show', $order)->with('success', 'Purchase order disimpan sebagai draft.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(['supplier', 'branch', 'items.medicine', 'creator']);

        return view('pharmacy.orders.show', ['order' => $purchaseOrder]);
    }

    public function submit(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        abort_unless($purchaseOrder->isDraft() && $purchaseOrder->items()->exists(), 422, 'PO draft harus memiliki item.');

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Ordered]);

        activity_log('ordered', $purchaseOrder, [], 'PO '.$purchaseOrder->number.' dipesan.', 'pharmacy');

        return back()->with('success', 'PO dikirim ke pemasok (status dipesan).');
    }

    public function receiveForm(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('update', $purchaseOrder);
        abort_unless($purchaseOrder->isOrdered(), 422, 'Hanya PO dipesan yang dapat diterima.');

        $purchaseOrder->load(['supplier', 'branch', 'items.medicine']);

        return view('pharmacy.orders.receive', ['order' => $purchaseOrder]);
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $receipts = [];

        foreach ($request->validated('items') as $itemId => $payload) {
            $receipts[(int) $itemId] = $payload;
        }

        try {
            $this->stocks->receive($purchaseOrder, $request->user(), $receipts);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('pharmacy.orders.show', $purchaseOrder)->with('success', 'Barang diterima dan stok bertambah.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<PurchaseOrder>
     */
    protected function orderQuery(Request $request)
    {
        $query = PurchaseOrder::query();
        $this->constrainBranch($query, $request->user());

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
    protected function formData(Request $request): array
    {
        $user = $request->user();
        $branchId = $this->restrictedBranchId($user);

        $branches = Branch::query()
            ->when($branchId, fn ($query) => $query->whereKey($branchId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return [
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id')->all(),
            'branches' => $branches,
            'medicines' => Medicine::query()->active()->orderBy('name')->get(['id', 'name', 'unit', 'base_price']),
            'defaultBranchId' => $branchId ?: $user?->branch_id,
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
