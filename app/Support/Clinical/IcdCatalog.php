<?php

namespace App\Support\Clinical;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber utama: tabel icd10_codes dari public/icd/[PUBLIC] ICD-10 e-klaim.xlsx.
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

        $fromDatabase = self::fromDatabase();

        if ($fromDatabase !== []) {
            /** @var list<array{code: string, description: string}> $items */
            $items = Cache::remember('icd10_catalog:eklaim', now()->addDay(), fn () => self::fromDatabase());

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
        if (! Schema::hasTable('icd10_codes')) {
            return [];
        }

        return DB::table('icd10_codes')
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
        $path = database_path('master_icd_x.json');

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

            $mapped[] = [
                'code' => $code,
                'description' => trim((string) (
                    $row['description']
                    ?? $row['nama_icd']
                    ?? $row['nama_icd_indo']
                    ?? ''
                )),
            ];
        }

        return $mapped;
    }

    /**
     * @return array{results: list<array{id: string, text: string, code: string, description: string}>, pagination: array{more: bool}}
     */
    public static function search(string $query, int $limit = 30, int $page = 1): array
    {
        return IcdSearch::paginate(self::all(), $query, $limit, $page);
    }
}
