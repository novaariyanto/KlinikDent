<?php

namespace App\Support\Clinical;

use Illuminate\Support\Facades\Cache;

/**
 * Sumber master: https://github.com/LuChang-CS/icd_hierarchical_structure (ICD-10-CM diagnosis).
 * File: database/master_icd_x.json (leaf codes, Bahasa Inggris).
 */
final class IcdCatalog
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

        $path = database_path('master_icd_x.json');
        $mtime = is_file($path) ? (string) filemtime($path) : '0';

        /** @var list<array{code: string, description: string}> $items */
        $items = Cache::remember('icd_catalog:luchang:'.$mtime, now()->addDays(7), function () use ($path) {
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

                $code = trim((string) ($row['code'] ?? $row['kode_icd'] ?? ''));

                if ($code === '') {
                    continue;
                }

                $description = trim((string) (
                    $row['description']
                    ?? $row['nama_icd']
                    ?? $row['nama_icd_indo']
                    ?? ''
                ));

                $mapped[] = [
                    'code' => $code,
                    'description' => $description,
                ];
            }

            return $mapped;
        });

        return $items;
    }

    /**
     * @return array{results: list<array{id: string, text: string, code: string, description: string}>, pagination: array{more: bool}}
     */
    public static function search(string $query, int $limit = 30, int $page = 1): array
    {
        return IcdSearch::paginate(self::all(), $query, $limit, $page);
    }
}
