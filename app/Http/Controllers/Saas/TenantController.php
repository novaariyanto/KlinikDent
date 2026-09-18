<?php

namespace App\Http\Controllers\Saas;

use App\Enums\IntegrationProvider;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\SaasPackage;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Support\Saas\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class TenantController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Tenant::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Klinik'],
            ['data' => 'subdomain', 'name' => 'subdomain', 'title' => 'Subdomain'],
            ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'branches_count', 'name' => 'branches_count', 'title' => 'Cabang', 'className' => 'text-center'],
            ['data' => 'users_count', 'name' => 'users_count', 'title' => 'User', 'className' => 'text-center'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('saas.tenants.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        $query = Tenant::query()->withCount(['branches', 'users']);

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('subdomain', fn (Tenant $tenant) => '<code>'.e($tenant->subdomain).'</code>')
            ->editColumn('status', function (Tenant $tenant) {
                return '<span class="'.$tenant->status->badgeClass().'">'.e($tenant->status->label()).'</span>';
            })
            ->editColumn('created_at', fn (Tenant $tenant) => $tenant->created_at?->format('d M Y H:i'))
            ->addColumn('action', fn (Tenant $tenant) => view('saas.tenants.partials.actions', compact('tenant'))->render())
            ->rawColumns(['subdomain', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Tenant::class);

        return view('saas.tenants.create', [
            'statuses' => TenantStatus::cases(),
            'packages' => SaasPackage::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreTenantRequest $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validated();
        $packageId = $data['package_id'] ?? null;
        unset($data['package_id']);
        $data['plan_id'] = $packageId;

        $tenant = Tenant::query()->create($data);

        if ($packageId) {
            $package = SaasPackage::query()->find($packageId);
            if ($package) {
                $subscriptions->start($tenant, $package, $tenant->status === TenantStatus::Trial);
            }
        }

        activity_log('created', $tenant, $request->validated(), 'Created tenant '.$tenant->name, 'tenants');

        return redirect()
            ->route('saas.tenants.show', $tenant)
            ->with('success', 'Klinik created successfully.');
    }

    public function show(Tenant $tenant): View
    {
        $this->authorize('view', $tenant);

        $tenant->load(['branches', 'users.roles', 'package', 'subscriptions.package', 'integrations']);

        return view('saas.tenants.show', [
            'tenant' => $tenant,
            'satusehat' => $tenant->integrations->first(
                fn ($row) => $row->provider === IntegrationProvider::SatuSehat
            ) ?? TenantIntegration::forTenant($tenant->id, IntegrationProvider::SatuSehat),
            'bpjs' => $tenant->integrations->first(
                fn ($row) => $row->provider === IntegrationProvider::Bpjs
            ) ?? TenantIntegration::forTenant($tenant->id, IntegrationProvider::Bpjs),
        ]);
    }

    public function edit(Tenant $tenant): View
    {
        $this->authorize('update', $tenant);

        return view('saas.tenants.edit', [
            'tenant' => $tenant,
            'statuses' => TenantStatus::cases(),
            'packages' => SaasPackage::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        activity_log('updated', $tenant, $request->validated(), 'Updated tenant '.$tenant->name, 'tenants');

        return redirect()
            ->route('saas.tenants.show', $tenant)
            ->with('success', 'Klinik updated successfully.');
    }

    public function toggleStatus(Tenant $tenant): RedirectResponse
    {
        $this->authorize('toggleStatus', $tenant);

        $tenant->update([
            'status' => $tenant->status === TenantStatus::Active
                ? TenantStatus::Suspended
                : TenantStatus::Active,
        ]);

        activity_log('status_changed', $tenant, [
            'status' => $tenant->status->value,
        ], 'Changed status of tenant '.$tenant->name.' to '.$tenant->status->label(), 'tenants');

        return back()->with('success', 'Status klinik updated successfully.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->authorize('delete', $tenant);

        activity_log('deleted', $tenant, [
            'name' => $tenant->name,
            'subdomain' => $tenant->subdomain,
        ], 'Deleted tenant '.$tenant->name, 'tenants');

        $tenant->delete();

        return redirect()
            ->route('saas.tenants.index')
            ->with('success', 'Klinik deleted successfully.');
    }
}
