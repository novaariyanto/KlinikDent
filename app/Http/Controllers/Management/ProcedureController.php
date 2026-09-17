<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procedure\StoreProcedureRequest;
use App\Http\Requests\Procedure\UpdateProcedureRequest;
use App\Models\Procedure;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ProcedureController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Procedure::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'code', 'name' => 'code', 'title' => 'Kode'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Tindakan'],
            ['data' => 'service', 'name' => 'service.name', 'title' => 'Layanan'],
            ['data' => 'category', 'name' => 'category', 'title' => 'Kategori'],
            ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('management.procedures.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Procedure::class);

        return DataTables::eloquent(Procedure::query()->with('service'))
            ->addIndexColumn()
            ->addColumn('service', fn (Procedure $procedure) => e($procedure->service?->name ?: '-'))
            ->editColumn('category', fn (Procedure $procedure) => e($procedure->categoryLabel()))
            ->editColumn('is_active', function (Procedure $procedure) {
                return $procedure->is_active
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Nonaktif</span>';
            })
            ->editColumn('created_at', fn (Procedure $procedure) => $procedure->created_at?->format('d M Y H:i'))
            ->addColumn('action', fn (Procedure $procedure) => view('management.procedures.partials.actions', compact('procedure'))->render())
            ->rawColumns(['is_active', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Procedure::class);

        return view('management.procedures.create', $this->formData());
    }

    public function store(StoreProcedureRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()?->tenant_id;

        $procedure = Procedure::query()->create($data);

        activity_log('created', $procedure, $data, 'Created procedure '.$procedure->code, 'procedures');

        return redirect()->route('management.procedures.index')->with('success', 'Tindakan created successfully.');
    }

    public function show(Procedure $procedure): View
    {
        $this->authorize('view', $procedure);
        $procedure->load('service');

        return view('management.procedures.show', compact('procedure'));
    }

    public function edit(Procedure $procedure): View
    {
        $this->authorize('update', $procedure);

        return view('management.procedures.edit', $this->formData($procedure));
    }

    public function update(UpdateProcedureRequest $request, Procedure $procedure): RedirectResponse
    {
        $data = $request->validated();
        $procedure->update($data);

        activity_log('updated', $procedure, $data, 'Updated procedure '.$procedure->code, 'procedures');

        return redirect()->route('management.procedures.index')->with('success', 'Tindakan updated successfully.');
    }

    public function destroy(Procedure $procedure): RedirectResponse
    {
        $this->authorize('delete', $procedure);

        activity_log('deleted', $procedure, ['code' => $procedure->code], 'Deleted procedure '.$procedure->code, 'procedures');
        $procedure->delete();

        return redirect()->route('management.procedures.index')->with('success', 'Tindakan deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Procedure $procedure = null): array
    {
        return [
            'procedure' => $procedure,
            'services' => Service::query()->orderBy('name')->get(),
        ];
    }
}
