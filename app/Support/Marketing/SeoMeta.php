<?php

namespace App\Support\Marketing;

use App\Models\SeoSetting;
use Illuminate\Support\Facades\Schema;

final class SeoMeta
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $fallback = [
            'site_name' => (string) config('app.name', 'KlinikDent'),
            'site_description' => 'KlinikDent adalah software manajemen klinik gigi dengan rekam medis elektronik, odontogram digital FDI, SOAP, resep, kasir, dan laporan operasional.',
            'default_seo_title' => 'Software Klinik Gigi & Odontogram Digital - KlinikDent',
            'default_meta_description' => 'KlinikDent adalah software manajemen klinik gigi dengan rekam medis elektronik, odontogram digital FDI, SOAP, resep, kasir, dan laporan operasional.',
            'default_og_image' => '/marketing/og.png',
            'canonical_base' => rtrim((string) config('app.url'), '/'),
            'google_verification' => 'PP4J4lbbjulpthQ-xaoENPnr8gXhbiwua-qNLg7NVCs',
            'bing_verification' => '',
            'ga4_id' => '',
            'gtm_id' => '',
            'organization_name' => (string) config('app.name', 'KlinikDent'),
            'organization_description' => 'Sistem manajemen klinik gigi dengan odontogram sebagai bagian dari rekam medis.',
            'social_profiles' => [],
            'robots_extra_disallow' => [],
        ];

        try {
            if (! Schema::hasTable('seo_settings')) {
                return $cache = $fallback;
            }

            $stored = SeoSetting::query()->pluck('value', 'key')->all();
            foreach ($stored as $key => $value) {
                if (! array_key_exists($key, $fallback)) {
                    continue;
                }
                if (in_array($key, ['social_profiles', 'robots_extra_disallow'], true)) {
                    $decoded = json_decode((string) $value, true);
                    $fallback[$key] = is_array($decoded) ? $decoded : [];
                } elseif ($value !== null && $value !== '') {
                    $fallback[$key] = $value;
                }
            }
        } catch (\Throwable) {
            // Settings table may not exist yet during migrate.
        }

        return $cache = $fallback;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $defaults = self::defaults();

        return $defaults[$key] ?? $default;
    }

    public static function ogImageUrl(?string $override = null): string
    {
        $path = $override ?: (string) self::get('default_og_image', '/marketing/og.png');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url($path);
    }

    /**
     * @param  list<array{name: string, item: string}>  $crumbs
     * @return array<string, mixed>
     */
    public static function breadcrumbList(array $crumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn ($crumb, $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['item'],
            ])->all(),
        ];
    }
}
