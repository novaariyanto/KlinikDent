<?php

namespace App\Support\Clinical;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber utama: tabel icd9_codes dari public/icd/[PUBLIC] ICD-9CM e-klaim.xlsx.
 */
final class Icd9Catalog
{
    /**
     * @return list<array{code: string, description: string}>
     */
    public static function all(): array
    {
        static $items;

        if (is_array($items)) {
            return $items;
        }

        $fromDatabase = self::fromDatabase();

        if ($fromDatabase !== []) {
            /** @var list<array{code: string, description: string}> $items */
            $items = Cache::remember('icd9_catalog:eklaim', now()->addDay(), fn () => self::fromDatabase());

            return $items;
        }

        $items = self::fromJson();

        return $items;
    }

    /**
     * @return list<array{code: string, description: string}>
     */
    protected static function fromDatabase(): array
    {
        if (! Schema::hasTable('icd9_codes')) {
            return [];
        }

        return DB::table('icd9_codes')
            ->orderBy('code')
            ->get(['code', 'description'])
            ->map(fn ($row) => [
                'code' => (string) $row->code,
                'description' => (string) $row->description,
            ])
            ->all();
    }

    /**
     * @return list<array{code: string, description: string}>
     */
    protected static function fromJson(): array
    {
        $path = database_path('master_icd9_cm.json');

        if (! is_file($path)) {
            return [];
        }

        $raw = json_decode((string) file_get_contents($path), true);

        if (! is_array($raw)) {
            return [];
        }

        $mapped = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = trim((string) ($row['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            $mapped[] = [
                'code' => $code,
                'description' => trim((string) ($row['description'] ?? '')),
            ];
        }

        return $mapped;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $item) {
            $options[$item['code']] = $item['code'].' — '.$item['description'];
        }

        return $options;
    }

    public static function has(string $code): bool
    {
        return self::description($code) !== null;
    }

    public static function description(string $code): ?string
    {
        static $index;

        if (! is_array($index)) {
            $index = [];
            foreach (self::all() as $item) {
                $index[$item['code']] = $item['description'];
            }
        }

        return $index[$code] ?? null;
    }

    public static function label(string $code): string
    {
        $description = self::description($code);

        return $description !== null ? $code.' — '.$description : $code;
    }

    /**
     * @return array{results: list<array{id: string, text: string, code: string, description: string}>, pagination: array{more: bool}}
     */
    public static function search(string $query, int $limit = 30, int $page = 1): array
    {
        return IcdSearch::paginate(self::all(), $query, $limit, $page);
    }
}
