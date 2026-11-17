<?php

namespace App\Http\Middleware;

use App\Enums\AddonCode;
use App\Support\Saas\AddonGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

class EnsureAddonActive
{
    public function handle(Request $request, Closure $next, string $code): Response
    {
        $user = $request->user();

        if (! $user || $user->isPlatformAdmin()) {
            return $next($request);
        }

        try {
            $addonCode = AddonCode::from($code);
        } catch (ValueError) {
            abort(403, 'Add-on tidak dikenali.');
        }

        $tenant = $user->tenant;

        if (! $tenant || ! AddonGate::isActive($tenant, $addonCode)) {
            abort(403, 'Add-on belum aktif untuk klinik ini.');
        }

        return $next($request);
    }
}
