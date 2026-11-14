<?php

namespace App\Http\Middleware;

use App\Exceptions\MobileApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->isActive()) {
            throw new MobileApiException(
                'This account is inactive. Please contact the administrator.',
                'account_inactive',
                403,
            );
        }

        if ($user->isPlatformAdmin()) {
            throw new MobileApiException(
                'Akun platform hanya dapat masuk lewat web.',
                'platform_admin_web_only',
                403,
            );
        }

        if (! $user->tenant_id) {
            throw new MobileApiException('Klinik tidak ditemukan.', 'tenant_required', 403);
        }

        return $next($request);
    }
}