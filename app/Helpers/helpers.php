<?php

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
