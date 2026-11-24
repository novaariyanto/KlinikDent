<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Medicine\ImportMedicineRequest;
use App\Http\Requests\Medicine\ImportMedicineRowRequest;
use App\Http\Requests\Medicine\AdjustMedicineStockRequest;
use App\Http\Requests\Medicine\StoreMedicineRequest;
use App\Http\Requests\Medicine\StoreMedicineStockRequest;
use App\Http\Requests\Medicine\UpdateMedicineRequest;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Support\Medicine\MedicineXlsxImporter;
use App\Support\Onboarding\OnboardingService;
use App\Support\Pharmacy\InsufficientStockException;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class MedicineController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Medicine::class);

        $columns = [
            ['data' => 'action', 'name' => 'action', 'title' => 'Aksi', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap', 'width' => '120px'],
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Obat'],
            ['data' => 'kfa_code', 'name' => 'kfa_code', 'title' => 'KFA'],
            ['data' => 'strength', 'name' => 'strength_value', 'title' => 'Kekuatan'],
            ['data' => 'unit', 'name' => 'unit', 'title' => 'Satuan'],
            ['data' => 'stock_qty', 'name' => 'stock_qty', 'title' => 'Stok', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'category', 'name' => 'category', 'title' => 'Kategori'],
            ['data' => 'base_price', 'name' => 'base_price', 'title' => 'Harga Dasar', 'className' => 'text-end'],
            ['data' => 'sell_price', 'name' => 'sell_price', 'title' => 'Harga Jual', 'className' => 'text-end'],
            ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
        ];

        return view('management.medicines.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Medicine::class);

        $user = request()->user();
        $query = Medicine::query()->withSum(['stocks as stock_qty' => function ($stocks) use ($user) {
            $user?->applyBranchLimit($stocks);
            $stocks->where('quantity', '>', 0)
                ->whereDate('expired_date', '>=', now()->toDateString());
        }], 'quantity');

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('kfa_code', function (Medicine $medicine) {
                return filled($medicine->kfa_code)
                    ? e($medicine->kfa_code)
                    : '<span class="badge bg-soft-warning text-warning">Belum KFA</span>';
            })
            ->addColumn('strength', fn (Medicine $medicine) => e($medicine->strengthLabel() ?: '—'))
            ->editColumn('unit', fn (Medicine $medicine) => e($medicine->unitLabel()))
            ->addColumn('stock_qty', function (Medicine $medicine) {
                $qty = (int) ($medicine->stock_qty ?? 0);
                $class = $qty > 0 ? 'bg-success' : 'bg-danger';

                return '<span class="badge '.$class.'">'.$qty.'</span>';
            })
            ->editColumn('category', fn (Medicine $medicine) => e($medicine->categoryLabel()))
            ->editColumn('base_price', fn (Medicine $medicine) => number_format((float) $medicine->base_price, 0, ',', '.'))
            ->editColumn('sell_price', fn (Medicine $medicine) => number_format((float) $medicine->sell_price, 0, ',', '.'))
            ->editColumn('is_active', function (Medicine $medicine) {
                return $medicine->is_active
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Nonaktif</span>';
            })
            ->editColumn('created_at', fn (Medicine $medicine) => $medicine->created_at?->format('d M Y H:i'))
            ->addColumn('action', fn (Medicine $medicine) => view('management.medicines.partials.actions', compact('medicine'))->render())
            ->rawColumns(['kfa_code', 'stock_qty', 'is_active', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Medicine::class);

        return view('management.medicines.create', ['medicine' => null]);
    }

    public function store(StoreMedicineRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()?->tenant_id;

        $medicine = Medicine::query()->create($data);

        activity_log('created', $medicine, $data, 'Created medicine '.$medicine->name, 'medicines');

        return redirect()->route('management.medicines.index')->with('success', 'Obat created successfully.');
    }

    public function importTemplate(MedicineXlsxImporter $importer): StreamedResponse
    {
        $this->authorize('create', Medicine::class);

        return $importer->templateResponse();
    }

    public function importCatalog(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        $this->authorize('create', Medicine::class);
        $tenant = Tenant::query()->findOrFail($request->user()?->tenant_id);
        $count = $onboarding->importMedicineTemplate($tenant);

        activity_log('created', $tenant, ['count' => $count], 'Imported medicine catalog', 'medicines');

        return back()->with('success', $count.' obat dari master onboarding diimpor.');
    }

    public function importParse(ImportMedicineRequest $request, MedicineXlsxImporter $importer): JsonResponse
    {
        $rows = $importer->parse($request->file('file'));

        return response()->json([
            'ok' => true,
            'count' => count($rows),
            'rows' => $rows,
        ]);
    }

    public function importRow(ImportMedicineRowRequest $request, MedicineXlsxImporter $importer): JsonResponse
    {
        $tenantId = (int) $request->user()?->tenant_id;
        abort_if($tenantId < 1, 403);

        $result = $importer->importOne($request->validated(), $tenantId);
        $row = $request->integer('row');

        return response()->json([
            'ok' => $result['status'] !== 'failed',
            'row' => $row > 0 ? $row : null,
            'status' => $result['status'],
            'message' => $result['message'],
            'medicine_id' => $result['medicine_id'] ?? null,
            'name' => $request->input('name'),
        ], $result['status'] === 'failed' ? 422 : 200);
    }

    public function show(Medicine $medicine): View
    {
        $this->authorize('view', $medicine);

        $user = request()->user();
        $stocks = $medicine->stocks()->with('branch')->orderBy('expired_date')->orderBy('id');
        if ($user) {
            $user->applyBranchLimit($stocks->getQuery());
        }
        $stocks = $stocks->get();
        $available = (int) $stocks->filter(fn (MedicineStock $stock) => $stock->quantity > 0 && ! $stock->isExpired())->sum('quantity');

        $branches = Branch::query()->orderBy('name');
        $user?->applyBranchLimit($branches, 'id');
        $branchOptions = $branches->pluck('name', 'id');

        $movements = StockMovement::query()
            ->whereIn('medicine_stock_id', $stocks->pluck('id'))
            ->with(['stock.branch', 'user'])
            ->latest('id')
            ->limit(15)
            ->get();

        return view('management.medicines.show', compact('medicine', 'stocks', 'available', 'branchOptions', 'movements'));
    }

    public function storeStock(StoreMedicineStockRequest $request, Medicine $medicine, PharmacyStockService $stocks): RedirectResponse
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($stocks, $medicine, $request, $data) {
                $stocks->addStock(
                    tenantId: (int) $medicine->tenant_id,
                    branchId: (int) $data['branch_id'],
                    medicineId: (int) $medicine->id,
                    batchNumber: trim($data['batch_number']),
                    expiredDate: $data['expired_date'],
                    quantity: (int) $data['quantity'],
                    user: $request->user(),
                    notes: filled($data['notes'] ?? null) ? $data['notes'] : 'Stok masuk dari master obat',
                );
            });
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('management.medicines.show', $medicine)
            ->withFragment('stok')
            ->with('success', 'Stok obat ditambahkan.');
    }

    public function adjustStock(AdjustMedicineStockRequest $request, Medicine $medicine, MedicineStock $medicineStock, PharmacyStockService $stocks): RedirectResponse
    {
        try {
            $stocks->adjust(
                $medicineStock,
                (int) $request->validated('delta'),
                $request->user(),
                $request->validated('notes'),
            );
        } catch (InsufficientStockException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('management.medicines.show', $medicine)
            ->withFragment('stok')
            ->with('success', 'Stok berhasil disesuaikan.');
    }

    public function edit(Medicine $medicine): View
    {
        $this->authorize('update', $medicine);

        return view('management.medicines.edit', compact('medicine'));
    }

    public function update(UpdateMedicineRequest $request, Medicine $medicine): RedirectResponse
    {
        $data = $request->validated();
        $medicine->update($data);

        activity_log('updated', $medicine, $data, 'Updated medicine '.$medicine->name, 'medicines');

        return redirect()->route('management.medicines.index')->with('success', 'Obat updated successfully.');
    }

    public function destroy(Medicine $medicine): RedirectResponse
    {
        $this->authorize('delete', $medicine);

        activity_log('deleted', $medicine, ['name' => $medicine->name], 'Deleted medicine '.$medicine->name, 'medicines');
        $medicine->delete();

        return redirect()->route('management.medicines.index')->with('success', 'Obat deleted successfully.');
    }
}
