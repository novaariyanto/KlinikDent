<?php

namespace App\Support\Clinical;

use Illuminate\Support\Facades\Cache;

/**
 * Sumber master: https://github.com/LuChang-CS/icd_hierarchical_structure (ICD-9-CM procedure).
 * File: database/master_icd9_cm.json (leaf codes, Bahasa Inggris).
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

        $path = database_path('master_icd9_cm.json');
        $mtime = is_file($path) ? (string) filemtime($path) : '0';

        /** @var list<array{code: string, description: string}> $items */
        $items = Cache::remember('icd9_catalog:luchang:'.$mtime, now()->addDays(7), function () use ($path) {
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
        });

        return $items;
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
