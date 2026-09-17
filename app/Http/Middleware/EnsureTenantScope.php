<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\Queue;
use App\Models\Tenant;
use App\Models\Visit;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isPlatformAdmin()) {
            return $next($request);
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Tenant) {
                if ((int) $parameter->id !== (int) $user->tenant_id) {
                    abort(403);
                }

                continue;
            }

            if ($parameter instanceof Queue) {
                $tenantId = $parameter->tenantId();
                if ($tenantId !== null && (int) $tenantId !== (int) $user->tenant_id) {
                    abort(403);
                }

                continue;
            }

            if ($parameter instanceof Model) {
                $tenantId = $parameter->getAttribute('tenant_id');

                if ($tenantId === null && method_exists($parameter, 'tenantId')) {
                    $tenantId = $parameter->tenantId();
                }

                if ($tenantId === null && $parameter->getAttribute('visit_id') !== null) {
                    $tenantId = Visit::withoutGlobalScopes()
                        ->whereKey($parameter->getAttribute('visit_id'))
                        ->value('tenant_id');
                }

                if ($tenantId === null && $parameter->getAttribute('patient_id') !== null) {
                    $tenantId = Patient::withoutGlobalScopes()
                        ->whereKey($parameter->getAttribute('patient_id'))
                        ->value('tenant_id');
                }

                if ($tenantId === null && $parameter->getAttribute('branch_id') !== null) {
                    $tenantId = Branch::withoutGlobalScopes()
                        ->whereKey($parameter->getAttribute('branch_id'))
                        ->value('tenant_id');
                }

                if ($tenantId !== null && (int) $tenantId !== (int) $user->tenant_id) {
                    abort(403);
                }
            }
        }

        return $next($request);
    }
}
