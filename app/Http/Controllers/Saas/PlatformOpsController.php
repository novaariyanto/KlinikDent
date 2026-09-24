<?php

namespace App\Http\Controllers\Saas;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\IntegrationLog;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PlatformOpsController extends Controller
{
    public function integrations(): View
    {
        abort_unless(auth()->user()?->can('saas.integration.view'), 403);

        $tenants = Tenant::query()
            ->with('integrations')
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant) {
                return [
                    'tenant' => $tenant,
                    'satusehat' => $this->moduleFor($tenant, IntegrationProvider::SatuSehat),
                    'bpjs' => $this->moduleFor($tenant, IntegrationProvider::Bpjs),
                ];
            });

        $logs = IntegrationLog::withoutGlobalScopes()
            ->with('tenant')
            ->latest('id')
            ->paginate(30);

        return view('saas.system.integrations', [
            'tenants' => $tenants,
            'logs' => $logs,
            'satusehatBaseUrl' => config('integrations.satusehat.base_url'),
            'bpjsBaseUrl' => config('integrations.bpjs.base_url'),
        ]);
    }

    public function audit(): View
    {
        abort_unless(auth()->user()?->can('saas.audit.view'), 403);

        $logs = ActivityLog::query()
            ->with('user')
            ->whereIn('module', ['saas', 'tenants', 'users'])
            ->latest('id')
            ->paginate(30);

        return view('saas.system.audit', compact('logs'));
    }

    public function userActivity(): View
    {
        abort_unless(auth()->user()?->can('saas.activity.view'), 403);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Waktu'],
            ['data' => 'user', 'name' => 'user.name', 'title' => 'User', 'orderable' => false],
            ['data' => 'event', 'name' => 'event', 'title' => 'Event', 'className' => 'text-center'],
            ['data' => 'module', 'name' => 'module', 'title' => 'Modul'],
            ['data' => 'description', 'name' => 'description', 'title' => 'Deskripsi'],
        ];

        return view('saas.users.activity', compact('columns'));
    }

    public function userActivityData(): JsonResponse
    {
        abort_unless(auth()->user()?->can('saas.activity.view'), 403);

        $query = ActivityLog::query()
            ->with('user')
            ->whereNotNull('user_id');

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('created_at', fn (ActivityLog $log) => $log->created_at?->format('d M Y H:i'))
            ->addColumn('user', fn (ActivityLog $log) => e($log->user?->name ?: '-'))
            ->editColumn('event', fn (ActivityLog $log) => '<span class="'.$log->eventBadgeClass().'">'.e($log->eventLabel()).'</span>')
            ->editColumn('module', fn (ActivityLog $log) => e($log->moduleLabel()))
            ->filterColumn('user', function ($query, $keyword) {
                $query->whereHas('user', function ($userQuery) use ($keyword) {
                    $userQuery->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->rawColumns(['event'])
            ->toJson();
    }

    protected function moduleFor(Tenant $tenant, IntegrationProvider $provider): TenantIntegration
    {
        $existing = $tenant->integrations->first(
            fn (TenantIntegration $row) => $row->provider === $provider
        );

        return $existing ?? TenantIntegration::forTenant($tenant->id, $provider);
    }
}
