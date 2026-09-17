<?php

namespace App\Http\Controllers;

use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class BranchController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Branch::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Cabang'],
            ['data' => 'address', 'name' => 'address', 'title' => 'Alamat'],
            ['data' => 'phone', 'name' => 'phone', 'title' => 'Telepon'],
            ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        if (auth()->user()?->isPlatformAdmin()) {
            array_splice($columns, 2, 0, [[
                'data' => 'tenant',
                'name' => 'tenant.name',
                'title' => 'Klinik',
            ]]);
        }

        return view('branches.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $query = Branch::query()->with('tenant');

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('tenant', fn (Branch $branch) => e($branch->tenant?->name ?: '-'))
            ->editColumn('address', fn (Branch $branch) => e(Str::limit((string) $branch->address, 60) ?: '-'))
            ->editColumn('phone', fn (Branch $branch) => e($branch->phone ?: '-'))
            ->editColumn('is_active', function (Branch $branch) {
                return $branch->is_active
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Nonaktif</span>';
            })
            ->editColumn('created_at', fn (Branch $branch) => $branch->created_at?->format('d M Y H:i'))
            ->addColumn('action', fn (Branch $branch) => view('branches.partials.actions', compact('branch'))->render())
            ->rawColumns(['is_active', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Branch::class);

        return view('branches.create', $this->formData());
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = auth()->user()?->isPlatformAdmin()
            ? $data['tenant_id']
            : auth()->user()?->tenant_id;

        $branch = Branch::query()->create($data);

        activity_log('created', $branch, $data, 'Created branch '.$branch->name, 'branches');

        return redirect()
            ->route('branches.index')
            ->with('success', 'Cabang created successfully.');
    }

    public function show(Branch $branch): View
    {
        $this->authorize('view', $branch);

        $branch->load('tenant');

        return view('branches.show', compact('branch'));
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);

        return view('branches.edit', $this->formData($branch));
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = auth()->user()?->isPlatformAdmin()
            ? $data['tenant_id']
            : $branch->tenant_id;

        $branch->update($data);

        activity_log('updated', $branch, $data, 'Updated branch '.$branch->name, 'branches');

        return redirect()
            ->route('branches.index')
            ->with('success', 'Cabang updated successfully.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        activity_log('deleted', $branch, [
            'name' => $branch->name,
            'tenant_id' => $branch->tenant_id,
        ], 'Deleted branch '.$branch->name, 'branches');

        $branch->delete();

        return redirect()
            ->route('branches.index')
            ->with('success', 'Cabang deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Branch $branch = null): array
    {
        $tenants = auth()->user()?->isPlatformAdmin()
            ? Tenant::query()->orderBy('name')->get()
            : collect();

        return [
            'branch' => $branch,
            'tenants' => $tenants,
        ];
    }
}
