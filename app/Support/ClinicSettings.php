<?php

namespace App\Support;

use App\Models\ClinicSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

class ClinicSettings
{
    /**
     * @var list<string>
     */
    public const KEYS = [
        'clinic_name',
        'legal_name',
        'tagline',
        'address',
        'city',
        'province',
        'postal_code',
        'phone',
        'email',
        'website',
        'npwp',
        'license_number',
        'pic_name',
        'pic_sip',
        'logo',
        'print_city',
        'print_footer',
        'timezone',
        'rm_prefix',
    ];

    /**
     * @return array<string, string>
     */
    public static function timezones(): array
    {
        return [
            'Asia/Jakarta' => 'WIB — Asia/Jakarta',
            'Asia/Makassar' => 'WITA — Asia/Makassar',
            'Asia/Jayapura' => 'WIT — Asia/Jayapura',
            'UTC' => 'UTC',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function current(?int $tenantId = null): array
    {
        $tenantId ??= current_tenant_id();
        $tenant = $tenantId ? Tenant::query()->find($tenantId) : null;
        $stored = $tenantId ? ClinicSetting::valuesFor($tenantId) : [];
        $defaults = static::defaults($tenant);

        $settings = [];

        foreach (self::KEYS as $key) {
            $value = $stored[$key] ?? null;
            $settings[$key] = filled($value) ? $value : $defaults[$key];
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(?Tenant $tenant = null): array
    {
        return [
            'clinic_name' => $tenant?->name ?: config('app.name'),
            'legal_name' => '',
            'tagline' => '',
            'address' => '',
            'city' => '',
            'province' => '',
            'postal_code' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'npwp' => '',
            'license_number' => '',
            'pic_name' => '',
            'pic_sip' => '',
            'logo' => '',
            'print_city' => '',
            'print_footer' => '',
            'timezone' => 'Asia/Jakarta',
            'rm_prefix' => $tenant ? strtoupper((string) $tenant->subdomain) : '',
        ];
    }

    public static function get(string $key, mixed $default = null, ?int $tenantId = null): mixed
    {
        $settings = static::current($tenantId);

        return $settings[$key] ?? $default;
    }

    public static function name(?int $tenantId = null): string
    {
        $tenantId ??= current_tenant_id();
        $stored = $tenantId ? ClinicSetting::getValue('clinic_name', null, $tenantId) : null;

        if (filled($stored)) {
            return (string) $stored;
        }

        if ($tenantId) {
            $tenant = Tenant::query()->find($tenantId);

            return (string) ($tenant?->name ?: config('app.name'));
        }

        return (string) config('app.name');
    }

    public static function rmPrefix(Tenant $tenant): string
    {
        $prefix = ClinicSetting::getValue('rm_prefix', null, (int) $tenant->id);

        return strtoupper((string) (filled($prefix) ? $prefix : $tenant->subdomain));
    }

    public static function hasCustomLogo(?int $tenantId = null): bool
    {
        $path = ClinicSetting::getValue('logo', null, $tenantId);

        return (bool) ($path && Storage::disk('public')->exists($path));
    }

    public static function logoUrl(string $variant = 'dark', ?int $tenantId = null): string
    {
        $path = ClinicSetting::getValue('logo', null, $tenantId);

        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return AppSettings::logoUrl($variant);
    }

    public static function printLogoSrc(?int $tenantId = null): ?string
    {
        $path = ClinicSetting::getValue('logo', null, $tenantId);

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $full = Storage::disk('public')->path($path);

        if (! is_file($full) || ! is_readable($full)) {
            return null;
        }

        $contents = file_get_contents($full);

        if ($contents === false || $contents === '') {
            return null;
        }

        $mime = mime_content_type($full) ?: 'image/png';

        if (! str_starts_with((string) $mime, 'image/')) {
            return null;
        }

        if (str_contains((string) $mime, 'svg')) {
            return 'file:///'.str_replace('\\', '/', $full);
        }

        $printable = static::printableLogo($contents, (string) $mime);

        return 'data:'.$printable['mime'].';base64,'.base64_encode($printable['contents']);
    }

    /**
     * @return array{mime: string, contents: string}
     */
    protected static function printableLogo(string $contents, string $mime): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return ['mime' => $mime, 'contents' => $contents];
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            return ['mime' => $mime, 'contents' => $contents];
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $max = 240;
        $scale = min(1, $max / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        $background = imagecolorallocate($resized, 255, 255, 255);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $background);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        ob_start();
        imagejpeg($resized, null, 84);
        $encoded = (string) ob_get_clean();
        imagedestroy($resized);

        return ['mime' => 'image/jpeg', 'contents' => $encoded !== '' ? $encoded : $contents];
    }

    /**
     * @return list<string>
     */
    public static function addressLines(?int $tenantId = null): array
    {
        $settings = static::current($tenantId);
        $cityLine = trim(implode(' ', array_filter([
            $settings['city'] ?? '',
            $settings['province'] ?? '',
            $settings['postal_code'] ?? '',
        ])));

        return array_values(array_filter([
            $settings['address'] ?? '',
            $cityLine,
        ]));
    }

    public static function apply(?int $tenantId = null): void
    {
        $tenantId ??= current_tenant_id();

        if (! $tenantId) {
            return;
        }

        $timezone = (string) ClinicSetting::getValue('timezone', 'Asia/Jakarta', $tenantId);

        if (! array_key_exists($timezone, static::timezones())) {
            $timezone = 'Asia/Jakarta';
        }

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }
}
