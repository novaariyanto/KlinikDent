<?php

namespace App\Http\Middleware;

use App\Models\Visit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiVisitTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $visit = $request->route('visit');
        $user = $request->user();

        if ($visit instanceof Visit && $user && ! $user->belongsToTenantId($visit->tenant_id)) {
            abort(404);
        }

        return $next($request);
    }
}
