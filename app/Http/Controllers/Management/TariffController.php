<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tariff\StoreTariffRequest;
use App\Http\Requests\Tariff\UpdateTariffRequest;
use App\Models\Branch;
use App\Models\Payer;
use App\Models\Procedure;
use App\Models\Tariff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class TariffController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Tariff::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'procedure', 'name' => 'procedure.name', 'title' => 'Tindakan'],
            ['data' => 'branch', 'name' => 'branch.name', 'title' => 'Cabang'],
            ['data' => 'payer', 'name' => 'payer.name', 'title' => 'Penjamin'],
            ['data' => 'price', 'name' => 'price', 'title' => 'Harga', 'className' => 'text-end'],
            ['data' => 'effective_date', 'name' => 'effective_date', 'title' => 'Berlaku'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        $branchId = $request->integer('branch_id') ?: null;
        $procedures = Procedure::query()->active()->orderBy('name')->get();
        $payers = Payer::query()->orderBy('name')->get();
        $branches = Branch::query()->orderBy('name')->get();

        $tariffs = Tariff::query()
            ->with(['procedure', 'payer', 'branch'])
            ->when($branchId, fn ($query) => $query->where(fn ($inner) => $inner->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->orderByDesc('effective_date')
            ->get();

        $matrix = [];

        foreach ($tariffs as $tariff) {
            $payerKey = $tariff->payer_id ?: 'default';
            $key = $tariff->procedure_id.'|'.$payerKey;
            $existing = $matrix[$key] ?? null;

            if (! $existing || $tariff->effective_date->gt($existing['effective_date'])) {
                $matrix[$key] = [
                    'price' => $tariff->price,
                    'effective_date' => $tariff->effective_date,
                    'id' => $tariff->id,
                ];
            }
        }

        return view('management.tariffs.index', compact(
            'columns',
            'procedures',
            'payers',
            'branches',
            'branchId',
            'matrix',
        ));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Tariff::class);

        return DataTables::eloquent(Tariff::query()->with(['procedure', 'branch', 'payer']))
            ->addIndexColumn()
            ->addColumn('procedure', fn (Tariff $tariff) => e($tariff->procedure?->name ?: '-'))
            ->addColumn('branch', fn (Tariff $tariff) => e($tariff->branch?->name ?: 'Semua cabang'))
            ->addColumn('payer', fn (Tariff $tariff) => e($tariff->payer?->name ?: 'Default'))
            ->editColumn('price', fn (Tariff $tariff) => number_format((float) $tariff->price, 0, ',', '.'))
            ->editColumn('effective_date', fn (Tariff $tariff) => $tariff->effective_date?->format('d M Y'))
            ->addColumn('action', fn (Tariff $tariff) => view('management.tariffs.partials.actions', compact('tariff'))->render())
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Tariff::class);

        return view('management.tariffs.create', $this->formData());
    }

    public function store(StoreTariffRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()?->tenant_id;

        $tariff = Tariff::query()->create($data);

        activity_log('created', $tariff, $data, 'Created tariff', 'tariffs');

        return redirect()->route('management.tariffs.index')->with('success', 'Tarif created successfully.');
    }

    public function show(Tariff $tariff): View
    {
        $this->authorize('view', $tariff);
        $tariff->load(['procedure', 'branch', 'payer']);

        return view('management.tariffs.show', compact('tariff'));
    }

    public function edit(Tariff $tariff): View
    {
        $this->authorize('update', $tariff);

        return view('management.tariffs.edit', $this->formData($tariff));
    }

    public function update(UpdateTariffRequest $request, Tariff $tariff): RedirectResponse
    {
        $data = $request->validated();
        $tariff->update($data);

        activity_log('updated', $tariff, $data, 'Updated tariff', 'tariffs');

        return redirect()->route('management.tariffs.index')->with('success', 'Tarif updated successfully.');
    }

    public function destroy(Tariff $tariff): RedirectResponse
    {
        $this->authorize('delete', $tariff);

        activity_log('deleted', $tariff, ['procedure_id' => $tariff->procedure_id], 'Deleted tariff', 'tariffs');
        $tariff->delete();

        return redirect()->route('management.tariffs.index')->with('success', 'Tarif deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Tariff $tariff = null): array
    {
        return [
            'tariff' => $tariff,
            'procedures' => Procedure::query()->orderBy('name')->get(),
            'branches' => Branch::query()->orderBy('name')->get(),
            'payers' => Payer::query()->orderBy('name')->get(),
        ];
    }
}
