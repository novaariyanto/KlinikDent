<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ServiceController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Service::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Layanan'],
            ['data' => 'category', 'name' => 'category', 'title' => 'Kategori'],
            ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('management.services.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        return DataTables::eloquent(Service::query())
            ->addIndexColumn()
            ->editColumn('category', fn (Service $service) => e($service->categoryLabel()))
            ->editColumn('is_active', function (Service $service) {
                return $service->is_active
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Nonaktif</span>';
            })
            ->editColumn('created_at', fn (Service $service) => $service->created_at?->format('d M Y H:i'))
            ->addColumn('action', fn (Service $service) => view('management.services.partials.actions', compact('service'))->render())
            ->rawColumns(['is_active', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Service::class);

        return view('management.services.create', ['service' => null]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()?->tenant_id;

        $service = Service::query()->create($data);

        activity_log('created', $service, $data, 'Created service '.$service->name, 'services');

        return redirect()->route('management.services.index')->with('success', 'Layanan created successfully.');
    }

    public function show(Service $service): View
    {
        $this->authorize('view', $service);

        return view('management.services.show', compact('service'));
    }

    public function edit(Service $service): View
    {
        $this->authorize('update', $service);

        return view('management.services.edit', compact('service'));
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $data = $request->validated();
        $service->update($data);

        activity_log('updated', $service, $data, 'Updated service '.$service->name, 'services');

        return redirect()->route('management.services.index')->with('success', 'Layanan updated successfully.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->authorize('delete', $service);

        activity_log('deleted', $service, ['name' => $service->name], 'Deleted service '.$service->name, 'services');
        $service->delete();

        return redirect()->route('management.services.index')->with('success', 'Layanan deleted successfully.');
    }
}
