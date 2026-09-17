<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Medicine\StoreMedicineRequest;
use App\Http\Requests\Medicine\UpdateMedicineRequest;
use App\Models\Medicine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class MedicineController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Medicine::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Obat'],
            ['data' => 'unit', 'name' => 'unit', 'title' => 'Satuan'],
            ['data' => 'category', 'name' => 'category', 'title' => 'Kategori'],
            ['data' => 'base_price', 'name' => 'base_price', 'title' => 'Harga Dasar', 'className' => 'text-end'],
            ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('management.medicines.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Medicine::class);

        return DataTables::eloquent(Medicine::query())
            ->addIndexColumn()
            ->editColumn('unit', fn (Medicine $medicine) => e($medicine->unitLabel()))
            ->editColumn('category', fn (Medicine $medicine) => e($medicine->categoryLabel()))
            ->editColumn('base_price', fn (Medicine $medicine) => number_format((float) $medicine->base_price, 0, ',', '.'))
            ->editColumn('is_active', function (Medicine $medicine) {
                return $medicine->is_active
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Nonaktif</span>';
            })
            ->editColumn('created_at', fn (Medicine $medicine) => $medicine->created_at?->format('d M Y H:i'))
            ->addColumn('action', fn (Medicine $medicine) => view('management.medicines.partials.actions', compact('medicine'))->render())
            ->rawColumns(['is_active', 'action'])
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

    public function show(Medicine $medicine): View
    {
        $this->authorize('view', $medicine);

        return view('management.medicines.show', compact('medicine'));
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
