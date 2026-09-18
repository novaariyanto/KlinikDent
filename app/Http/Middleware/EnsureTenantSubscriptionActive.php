<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isPlatformAdmin()) {
            return $next($request);
        }

        if (! $request->isMethod('POST') && ! $request->isMethod('PUT') && ! $request->isMethod('PATCH') && ! $request->isMethod('DELETE')) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), ['logout', 'impersonate.leave'], true)) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if ($tenant && $tenant->status === TenantStatus::Suspended) {
            abort(403, 'Langganan klinik habis atau ditangguhkan. Hubungi pengelola platform.');
        }

        return $next($request);
    }
}
