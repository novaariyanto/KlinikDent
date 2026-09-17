<?php

namespace App\Http\Controllers\Management;

use App\Enums\PayerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payer\StorePayerRequest;
use App\Http\Requests\Payer\UpdatePayerRequest;
use App\Models\Payer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PayerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Payer::class);

        $payerType = PayerType::fromRouteKey((string) $request->route('payerType', ''));

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Penjamin'],
            ['data' => 'type', 'name' => 'type', 'title' => 'Tipe'],
            ['data' => 'contract_number', 'name' => 'contract_number', 'title' => 'No. Kontrak'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('management.payers.index', compact('columns', 'payerType'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payer::class);

        $payerType = PayerType::fromRouteKey((string) $request->query('type', ''));
        $query = Payer::query();

        if ($payerType) {
            $query->where('type', $payerType);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('type', fn (Payer $payer) => e($payer->type->label()))
            ->editColumn('contract_number', fn (Payer $payer) => e($payer->contract_number ?: '-'))
            ->editColumn('created_at', fn (Payer $payer) => $payer->created_at?->format('d M Y H:i'))
            ->addColumn('action', fn (Payer $payer) => view('management.payers.partials.actions', compact('payer'))->render())
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Payer::class);

        return view('management.payers.create', ['payer' => null]);
    }

    public function store(StorePayerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()?->tenant_id;

        $payer = Payer::query()->create($data);

        activity_log('created', $payer, $data, 'Created payer '.$payer->name, 'payers');

        return redirect()->route('management.payers.index')->with('success', 'Penjamin created successfully.');
    }

    public function show(Payer $payer): View
    {
        $this->authorize('view', $payer);

        return view('management.payers.show', compact('payer'));
    }

    public function edit(Payer $payer): View
    {
        $this->authorize('update', $payer);

        return view('management.payers.edit', compact('payer'));
    }

    public function update(UpdatePayerRequest $request, Payer $payer): RedirectResponse
    {
        $data = $request->validated();
        $payer->update($data);

        activity_log('updated', $payer, $data, 'Updated payer '.$payer->name, 'payers');

        return redirect()->route('management.payers.index')->with('success', 'Penjamin updated successfully.');
    }

    public function destroy(Payer $payer): RedirectResponse
    {
        $this->authorize('delete', $payer);

        activity_log('deleted', $payer, ['name' => $payer->name], 'Deleted payer '.$payer->name, 'payers');
        $payer->delete();

        return redirect()->route('management.payers.index')->with('success', 'Penjamin deleted successfully.');
    }
}
