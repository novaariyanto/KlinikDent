<?php

namespace App\Support;

class ClinicTheme
{
    public const PRESET_CUSTOM = 'custom';

    /**
     * @return array<string, array{label: string, description: string, primary: string, navy: string, accent: string}>
     */
    public static function presets(): array
    {
        return [
            'teal' => [
                'label' => 'Teal / Cyan',
                'description' => 'Bawaan. Teal 700 dan Cyan dari Material Palette.',
                'primary' => '#00796B',
                'navy' => '#00695C',
                'accent' => '#80DEEA',
            ],
            'blue' => [
                'label' => 'Blue / Cyan',
                'description' => 'Blue 700 dan Cyan dari Material Palette.',
                'primary' => '#1976D2',
                'navy' => '#1565C0',
                'accent' => '#80DEEA',
            ],
            'indigo' => [
                'label' => 'Cyan / Teal',
                'description' => 'Cyan 800 dan Teal dari Material Palette.',
                'primary' => '#00838F',
                'navy' => '#006064',
                'accent' => '#80CBC4',
            ],
            'forest' => [
                'label' => 'Green / Teal',
                'description' => 'Green 800 dan Teal dari Material Palette.',
                'primary' => '#2E7D32',
                'navy' => '#1B5E20',
                'accent' => '#80CBC4',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (static::presets() as $key => $preset) {
            $options[$key] = $preset['label'];
        }

        $options[self::PRESET_CUSTOM] = 'Warna sendiri';

        return $options;
    }

    /**
     * @return array{preset: string, primary: string, navy: string, accent: string, custom: array{primary: string, navy: string, accent: string}}
     */
    public static function current(): array
    {
        $fallback = static::presets()['teal'];
        $stored = static::stored();
        $storedPreset = (string) ($stored['preset'] ?? 'teal');
        $preset = array_key_exists($storedPreset, static::presets()) || $storedPreset === self::PRESET_CUSTOM
            ? $storedPreset
            : 'teal';

        $custom = [
            'primary' => static::normalizeHex((string) ($stored['primary'] ?? $fallback['primary']), $fallback['primary']),
            'navy' => static::normalizeHex((string) ($stored['navy'] ?? $fallback['navy']), $fallback['navy']),
            'accent' => static::normalizeHex((string) ($stored['accent'] ?? $fallback['accent']), $fallback['accent']),
        ];

        foreach ($custom as $channel => $hex) {
            if (static::isNearBlack($hex)) {
                $custom[$channel] = $fallback[$channel];
            }
        }

        $active = $preset === self::PRESET_CUSTOM
            ? $custom
            : [
                'primary' => static::presets()[$preset]['primary'],
                'navy' => static::presets()[$preset]['navy'],
                'accent' => static::presets()[$preset]['accent'],
            ];

        return [
            'preset' => $preset,
            'primary' => $active['primary'],
            'navy' => $active['navy'],
            'accent' => $active['accent'],
            'custom' => $custom,
        ];
    }

    public static function remember(string $preset, ?string $primary = null, ?string $navy = null, ?string $accent = null): void
    {
        $key = static::sessionKey();

        if ($key === null) {
            return;
        }

        $stored = static::stored();
        $stored['preset'] = $preset;

        if ($preset === self::PRESET_CUSTOM) {
            $fallback = static::presets()['teal'];
            $stored['primary'] = static::normalizeHex((string) $primary, $fallback['primary']);
            $stored['navy'] = static::normalizeHex((string) $navy, $fallback['navy']);
            $stored['accent'] = static::normalizeHex((string) $accent, $fallback['accent']);
        }

        session([$key => $stored]);
    }

    /**
     * @return array<string, string>
     */
    protected static function stored(): array
    {
        $key = static::sessionKey();

        if ($key === null) {
            return [];
        }

        $stored = session($key, []);

        return is_array($stored) ? $stored : [];
    }

    protected static function sessionKey(): ?string
    {
        $userId = auth()->id();

        return $userId ? 'user_theme.'.$userId : null;
    }

    /**
     * @return array<string, string>
     */
    public static function contrastErrors(string $primary, string $navy, string $accent): array
    {
        $errors = [];
        $primaryL = static::luminance($primary);
        $navyL = static::luminance($navy);
        $accentL = static::luminance($accent);

        foreach ([
            'theme_primary' => $primary,
            'theme_navy' => $navy,
            'theme_accent' => $accent,
        ] as $field => $hex) {
            if (static::isNearBlack($hex)) {
                $errors[$field] = 'Hindari warna hitam. Gunakan teal atau biru medis.';
            }
        }

        if (! isset($errors['theme_primary']) && static::contrast($primaryL, 1) < 4.5) {
            $errors['theme_primary'] = 'Warna utama terlalu terang. Teks putih pada tombol tidak terbaca.';
        }

        if (! isset($errors['theme_navy']) && static::contrast($navyL, 1) < 4.5) {
            $errors['theme_navy'] = 'Warna sidebar terlalu terang untuk teks putih.';
        }

        if (! isset($errors['theme_accent']) && static::contrast($accentL, $navyL) < 3) {
            $errors['theme_accent'] = 'Aksen kurang kontras terhadap warna sidebar.';
        }

        return $errors;
    }

    public static function isNearBlack(string $hex): bool
    {
        [$r, $g, $b] = static::rgb($hex);

        return max($r, $g, $b) < 48 || static::luminance($hex) < 0.03;
    }

    public static function style(): string
    {
        $theme = static::current();
        $primary = $theme['primary'];
        $navy = $theme['navy'];
        $accent = $theme['accent'];
        [$r, $g, $b] = static::rgb($primary);
        $hover = static::darken($primary, 0.14);
        $active = static::darken($primary, 0.24);
        $soft = static::mixWhite($primary, 0.88);

        $vars = [
            '--kd-primary' => $primary,
            '--kd-primary-hover' => $hover,
            '--kd-primary-active' => $active,
            '--kd-primary-soft' => $soft,
            '--kd-primary-ring' => static::rgba($primary, 0.18),
            '--kd-primary-glow' => static::rgba($primary, 0.22),
            '--kd-primary-line' => static::rgba($primary, 0.45),
            '--kd-primary-ring-strong' => static::rgba($primary, 0.7),
            '--kd-navy' => $navy,
            '--kd-accent' => $accent,
            '--bs-primary' => $primary,
            '--bs-primary-rgb' => $r.', '.$g.', '.$b,
            '--bs-link-color' => $primary,
            '--bs-link-hover-color' => $hover,
            '--bs-link-color-rgb' => $r.', '.$g.', '.$b,
            '--bs-primary-text-emphasis' => $active,
            '--bs-primary-bg-subtle' => $soft,
            '--bs-primary-border-subtle' => $primary,
            '--bs-emphasis-color' => $navy,
            '--bs-heading-color' => $navy,
            '--bs-header-item-color' => $navy,
            '--bs-menu-item-active-color' => $primary,
            '--booking-accent' => $primary,
            '--booking-accent-hover' => $hover,
            '--booking-accent-soft' => $soft,
        ];

        $body = [];

        foreach ($vars as $name => $value) {
            if (! preg_match('/^--[a-z0-9-]+$/', $name) || ! preg_match('/^[#a-z0-9(),.%\s-]+$/i', $value)) {
                continue;
            }

            $body[] = $name.':'.$value;
        }

        return ':root{'.implode(';', $body).'}'
            .'.btn-primary{--bs-btn-focus-shadow-rgb:'.$r.', '.$g.', '.$b.'}';
    }

    public static function normalizeHex(string $value, string $fallback): string
    {
        $value = strtoupper(trim($value));

        if (preg_match('/^#[0-9A-F]{6}$/', $value) !== 1) {
            return strtoupper($fallback);
        }

        return $value;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $hex = ltrim(static::normalizeHex($hex, '#00796B'), '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    public static function darken(string $hex, float $amount): string
    {
        [$r, $g, $b] = static::rgb($hex);
        $scale = max(0, 1 - $amount);

        return static::toHex((int) round($r * $scale), (int) round($g * $scale), (int) round($b * $scale));
    }

    public static function mixWhite(string $hex, float $white): string
    {
        [$r, $g, $b] = static::rgb($hex);
        $white = max(0, min(1, $white));

        return static::toHex(
            (int) round($r + (255 - $r) * $white),
            (int) round($g + (255 - $g) * $white),
            (int) round($b + (255 - $b) * $white),
        );
    }

    public static function rgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = static::rgb($hex);

        return 'rgba('.$r.', '.$g.', '.$b.', '.$alpha.')';
    }

    public static function luminance(string $hex): float
    {
        $channels = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }, static::rgb($hex));

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    public static function contrast(float $first, float $second): float
    {
        $lighter = max($first, $second);
        $darker = min($first, $second);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    protected static function toHex(int $r, int $g, int $b): string
    {
        return sprintf(
            '#%02X%02X%02X',
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b)),
        );
    }
}
