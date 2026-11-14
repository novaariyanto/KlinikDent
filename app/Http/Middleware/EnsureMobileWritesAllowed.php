<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Exceptions\MobileApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileWritesAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $tenant = $request->user()?->tenant;

        if ($tenant && $tenant->status === TenantStatus::Suspended) {
            throw new MobileApiException(
                'Langganan klinik habis atau ditangguhkan. Hubungi pengelola platform.',
                'tenant_suspended',
                403,
            );
        }

        return $next($request);
    }
}