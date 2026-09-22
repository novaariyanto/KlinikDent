<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs('onboarding.*')) {
            if ($user->isPlatformAdmin() || ! $user->hasRole(RoleName::Owner) || ! $user->tenant_id) {
                abort(403);
            }

            $tenant = $user->tenant;

            if ($tenant && ! $tenant->needsOnboarding() && ! $request->routeIs('onboarding.done')) {
                return redirect()->route('dashboard');
            }

            return $next($request);
        }

        if ($user->isPlatformAdmin() || ! $user->hasRole(RoleName::Owner) || ! $user->tenant_id) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if ($tenant && $tenant->needsOnboarding() && ! $request->routeIs('logout')) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
