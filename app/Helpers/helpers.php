<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\ClinicSettings;
use App\Support\Impersonation;

if (! function_exists('theme')) {
    function theme(string $path = ''): string
    {
        return asset('themes/skote/'.ltrim($path, '/'));
    }
}

if (! function_exists('is_impersonating')) {
    function is_impersonating(): bool
    {
        return Impersonation::active();
    }
}

if (! function_exists('activity_log')) {
    /**
     * @param  array<string, mixed>  $properties
     */
    function activity_log(
        string $event,
        mixed $subject = null,
        array $properties = [],
        ?string $description = null,
        ?string $module = null,
        ?User $causer = null,
    ): void {
        ActivityLog::record($event, $subject, $properties, $description, $module, $causer);
    }
}

if (! function_exists('current_tenant_id')) {
    function current_tenant_id(): ?int
    {
        if (! auth()->hasUser()) {
            return null;
        }

        $user = auth()->user();

        if (! $user || $user->isPlatformAdmin()) {
            return null;
        }

        return $user->tenant_id ? (int) $user->tenant_id : 0;
    }
}

if (! function_exists('app_logo')) {
    function app_logo(string $variant = 'dark'): string
    {
        try {
            if (current_tenant_id()) {
                return ClinicSettings::logoUrl($variant);
            }

            return AppSettings::logoUrl($variant);
        } catch (Throwable) {
            return $variant === 'light'
                ? theme('images/logo-light.png')
                : theme('images/logo-dark.png');
        }
    }
}

if (! function_exists('clinic_name')) {
    function clinic_name(?string $fallback = null): string
    {
        try {
            if (current_tenant_id()) {
                return ClinicSettings::name();
            }
        } catch (Throwable) {
            //
        }

        return $fallback ?: (string) config('app.name');
    }
}
