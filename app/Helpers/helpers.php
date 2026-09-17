<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AppSettings;
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

if (! function_exists('app_logo')) {
    function app_logo(string $variant = 'dark'): string
    {
        try {
            return AppSettings::logoUrl($variant);
        } catch (Throwable) {
            return $variant === 'light'
                ? theme('images/logo-light.png')
                : theme('images/logo-dark.png');
        }
    }
}
