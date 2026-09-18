<?php

namespace App\Http\Controllers\Saas;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\IntegrationLog;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\View\View;

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

        $logs = ActivityLog::query()
            ->with('user')
            ->whereNotNull('user_id')
            ->latest('id')
            ->paginate(30);

        return view('saas.users.activity', compact('logs'));
    }

    protected function moduleFor(Tenant $tenant, IntegrationProvider $provider): TenantIntegration
    {
        $existing = $tenant->integrations->first(
            fn (TenantIntegration $row) => $row->provider === $provider
        );

        return $existing ?? TenantIntegration::forTenant($tenant->id, $provider);
    }
}
