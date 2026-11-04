<?php

namespace App\Support\Clinical;

use App\Enums\ToothStatus;
use App\Enums\ToothSurface;
use App\Models\OdontogramTooth;

/**
 * Satu gigi menyimpan daftar kondisi di kolom `conditions`:
 * [{code, surfaces: list<string>, detail: array<string, string>, note: ?string}].
 * Kolom `status` (kondisi utama) dan `surfaces` (permukaan => kode) tetap diisi untuk data lama dan integrasi.
 */
final class ToothConditions
{
    public const MAX = 20;

    /**
     * @return array<string, array{key: string, label: string, type: string, options?: array<string, string>, min?: float, max?: float, step?: float, unit?: string, default?: string}>
     */
    public static function details(): array
    {
        return [
            ToothStatus::Filling->value => [
                'key' => 'material',
                'label' => 'Bahan restorasi',
                'type' => 'select',
                'options' => [
                    'composite' => 'Resin komposit',
                    'gic' => 'GIC / Glass ionomer',
                    'amalgam' => 'Amalgam',
                    'compomer' => 'Kompomer',
                    'ceramic' => 'Keramik',
                    'other' => 'Lainnya',
                ],
            ],
            ToothStatus::Fracture->value => [
                'key' => 'site',
                'label' => 'Lokasi fraktur',
                'type' => 'select',
                'options' => [
                    'enamel' => 'Email',
                    'crown' => 'Mahkota (email–dentin)',
                    'crown_pulp' => 'Mahkota, pulpa terbuka',
                    'crown_root' => 'Mahkota–akar',
                    'root' => 'Akar',
                ],
            ],
            ToothStatus::Mobility->value => [
                'key' => 'degree',
                'label' => 'Derajat mobilitas',
                'type' => 'choice',
                'options' => ['M1' => 'M1', 'M2' => 'M2', 'M3' => 'M3'],
                'default' => 'M1',
            ],
            ToothStatus::Impacted->value => [
                'key' => 'position',
                'label' => 'Posisi impaksi',
                'type' => 'select',
                'options' => [
                    'mesioangular' => 'Mesioangular',
                    'distoangular' => 'Distoangular',
                    'vertical' => 'Vertikal',
                    'horizontal' => 'Horizontal',
                    'buccoangular' => 'Bukoangular',
                    'linguoangular' => 'Linguoangular',
                    'inverted' => 'Terbalik (inverted)',
                ],
            ],
            ToothStatus::GingivalRecession->value => [
                'key' => 'mm',
                'label' => 'Ukuran resesi',
                'type' => 'number',
                'min' => 0.5,
                'max' => 20,
                'step' => 0.5,
                'unit' => 'mm',
            ],
            ToothStatus::Furcation->value => [
                'key' => 'degree',
                'label' => 'Derajat furkasi',
                'type' => 'choice',
                'options' => ['I' => 'Derajat I', 'II' => 'Derajat II', 'III' => 'Derajat III', 'IV' => 'Derajat IV'],
                'default' => 'I',
            ],
            ToothStatus::RootCanal->value => [
                'key' => 'progress',
                'label' => 'Status PSA',
                'type' => 'choice',
                'options' => ['in_progress' => 'Sedang perawatan', 'done' => 'Selesai'],
                'default' => 'in_progress',
            ],
        ];
    }

    public static function noteLabel(ToothStatus $status): string
    {
        return match ($status) {
            ToothStatus::Fracture => 'Keterangan fraktur',
            ToothStatus::Impacted => 'Catatan impaksi',
            default => 'Catatan tambahan',
        };
    }

    /**
     * @return array<string, string>
     */
    protected static function aliases(): array
    {
        return [
            ToothStatus::Caries->value => 'K lubang',
            ToothStatus::Filling->value => 'tambal restorasi',
            ToothStatus::RootCanal->value => 'PSA endo rct',
            ToothStatus::Crown->value => 'mahkota',
            ToothStatus::Extracted->value => 'ekstraksi cabut',
            ToothStatus::Missing->value => 'ekstraksi hilang',
            ToothStatus::Bridge->value => 'jembatan',
            ToothStatus::Impacted->value => 'impaksi molar tiga',
            ToothStatus::Mobility->value => 'goyang',
            ToothStatus::CariesProfunda->value => 'karies dalam',
            ToothStatus::ResidualRoot->value => 'radix gangren',
            ToothStatus::TemporaryFilling->value => 'tambal sementara',
            ToothStatus::PermanentFilling->value => 'tambal tetap',
            ToothStatus::PostCore->value => 'pasak inti',
            ToothStatus::PartialDenture->value => 'GTS gigi palsu',
            ToothStatus::CompleteDenture->value => 'GTL gigi palsu',
            ToothStatus::Supernumerary->value => 'berlebih mesiodens',
            ToothStatus::PrimaryPersistence->value => 'sulung',
            ToothStatus::GingivalRecession->value => 'gusi turun',
            ToothStatus::Calculus->value => 'karang gigi',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function meta(): array
    {
        $details = self::details();
        $aliases = self::aliases();

        return collect(ToothStatus::cases())->mapWithKeys(fn (ToothStatus $status) => [
            $status->value => [
                'label' => $status->label(),
                'color' => $status->color(),
                'symbol' => $status->symbol(),
                'whole' => $status->isWholeTooth(),
                'needsSurface' => $status->needsSurface(),
                'group' => $status->group(),
                'scope' => $status->scope(),
                'kinds' => $status->kinds(),
                'detail' => $details[$status->value] ?? null,
                'noteLabel' => self::noteLabel($status),
                'search' => strtolower(implode(' ', [$status->label(), $status->symbol(), $status->value, $aliases[$status->value] ?? ''])),
            ],
        ])->all();
    }

    public static function availableFor(ToothStatus $status, string $number): bool
    {
        if ($status === ToothStatus::Healthy) {
            return false;
        }

        $primary = FdiTeeth::isPrimary($number);

        if (($status->scope() === 'adult' && $primary) || ($status->scope() === 'primary' && ! $primary)) {
            return false;
        }

        $kinds = $status->kinds();

        return $kinds === null || in_array(FdiTeeth::kind($number), $kinds, true);
    }

    /**
     * @return list<array{code: string, surfaces: list<string>, detail: array<string, string>, note: ?string}>
     */
    public static function fromRecord(?OdontogramTooth $tooth): array
    {
        if (! $tooth) {
            return [];
        }

        $number = (string) $tooth->tooth_number;

        if (is_array($tooth->conditions)) {
            return self::clean($number, $tooth->conditions, strict: false);
        }

        $status = $tooth->status instanceof ToothStatus ? $tooth->status : ToothStatus::tryFrom((string) $tooth->status);

        if (! $status || $status === ToothStatus::Healthy) {
            return [];
        }

        $items = [['code' => $status->value, 'surfaces' => [], 'detail' => [], 'note' => null]];

        foreach (($tooth->surfaces ?? []) as $surface => $code) {
            $code = (string) $code;
            $index = collect($items)->search(fn ($item) => $item['code'] === $code);

            if ($index === false) {
                $items[] = ['code' => $code, 'surfaces' => [(string) $surface], 'detail' => [], 'note' => null];
            } else {
                $items[$index]['surfaces'][] = (string) $surface;
            }
        }

        return self::clean($number, $items, strict: false);
    }

    /**
     * strict = true untuk input baru: kondisi yang tidak relevan dengan jenis gigi ditolak.
     *
     * @param  array<int, mixed>  $items
     * @return list<array{code: string, surfaces: list<string>, detail: array<string, string>, note: ?string}>
     */
    public static function clean(string $number, array $items, bool $strict = true): array
    {
        $details = self::details();
        $allowedSurfaces = array_map(fn (ToothSurface $s) => $s->value, FdiTeeth::surfaces($number));
        $result = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $status = ToothStatus::tryFrom((string) ($item['code'] ?? ''));

            if (! $status || $status === ToothStatus::Healthy || isset($result[$status->value])) {
                continue;
            }

            if ($strict && ! self::availableFor($status, $number)) {
                continue;
            }

            $surfaces = [];

            if ($status->needsSurface()) {
                foreach ((array) ($item['surfaces'] ?? []) as $surface) {
                    $key = FdiTeeth::canonicalSurface($number, (string) $surface);

                    if ($key && in_array($key, $allowedSurfaces, true) && ! in_array($key, $surfaces, true)) {
                        $surfaces[] = $key;
                    }
                }

                if ($strict && $surfaces === []) {
                    continue;
                }

                usort($surfaces, fn ($a, $b) => array_search($a, $allowedSurfaces, true) <=> array_search($b, $allowedSurfaces, true));
            }

            $detail = [];
            $field = $details[$status->value] ?? null;
            $value = $field ? ($item['detail'][$field['key']] ?? null) : null;

            if ($field && $value !== null && $value !== '') {
                $value = (string) $value;

                if ($field['type'] === 'number') {
                    if (is_numeric($value) && (float) $value >= $field['min'] && (float) $value <= $field['max']) {
                        $detail[$field['key']] = rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
                    }
                } elseif (array_key_exists($value, $field['options'])) {
                    $detail[$field['key']] = $value;
                }
            }

            if ($field && ! isset($detail[$field['key']]) && isset($field['default'])) {
                $detail[$field['key']] = $field['default'];
            }

            $note = trim((string) ($item['note'] ?? ''));

            $result[$status->value] = [
                'code' => $status->value,
                'surfaces' => $surfaces,
                'detail' => $detail,
                'note' => $note !== '' ? mb_substr($note, 0, 255) : null,
            ];

            if (count($result) >= self::MAX) {
                break;
            }
        }

        return array_values($result);
    }

    /**
     * Permukaan => kode kondisi. Bila dua kondisi di permukaan sama, kondisi terakhir yang dipakai untuk warna.
     *
     * @param  list<array{code: string, surfaces: list<string>}>  $items
     * @return array<string, string>
     */
    public static function surfaceMap(array $items): array
    {
        $map = [];

        foreach ($items as $item) {
            foreach ($item['surfaces'] as $surface) {
                $map[$surface] = $item['code'];
            }
        }

        return $map;
    }

    /**
     * @param  list<array{code: string}>  $items
     */
    public static function primary(array $items): ToothStatus
    {
        return isset($items[0]) ? ToothStatus::from($items[0]['code']) : ToothStatus::Healthy;
    }

    /**
     * @param  array{code: string, surfaces: list<string>, detail: array<string, string>}  $item
     */
    public static function describe(array $item): string
    {
        $parts = [];

        if ($item['surfaces'] !== []) {
            $parts[] = collect($item['surfaces'])
                ->map(fn ($s) => ToothSurface::tryFrom($s)?->short())
                ->filter()
                ->implode('');
        }

        $field = self::details()[$item['code']] ?? null;
        $value = $field ? ($item['detail'][$field['key']] ?? null) : null;

        if ($field && $value !== null) {
            $parts[] = $field['type'] === 'number'
                ? $value.' '.($field['unit'] ?? '')
                : ($field['options'][$value] ?? $value);
        }

        return trim(implode(' · ', $parts));
    }

    /**
     * @param  list<array{code: string, surfaces: list<string>, detail: array<string, string>}>  $items
     */
    public static function summary(array $items): string
    {
        if ($items === []) {
            return ToothStatus::Healthy->label();
        }

        return collect($items)->map(function ($item) {
            $label = ToothStatus::from($item['code'])->label();
            $extra = self::describe($item);

            return $extra !== '' ? $label.' ('.$extra.')' : $label;
        })->implode(', ');
    }

    /**
     * Simbol yang digambar sebagai teks di tengah gigi (kondisi tanpa gambar khusus).
     *
     * @param  list<array{code: string, detail: array<string, string>}>  $items
     * @return list<array{code: string, text: string, color: string}>
     */
    public static function badges(array $items): array
    {
        $drawn = ['extracted', 'missing', 'crown', 'root_canal', 'fracture', 'implant', 'bridge', 'impacted'];
        $badges = [];

        foreach ($items as $item) {
            $status = ToothStatus::from($item['code']);

            if (! $status->isWholeTooth() || in_array($status->value, $drawn, true)) {
                continue;
            }

            $badges[] = [
                'code' => $status->value,
                'text' => $status === ToothStatus::Mobility ? ($item['detail']['degree'] ?? 'M') : $status->symbol(),
                'color' => $status->color(),
            ];
        }

        return $badges;
    }
}
