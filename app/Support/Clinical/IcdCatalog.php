<?php

namespace App\Support\Clinical;

use Illuminate\Support\Facades\Cache;

final class IcdCatalog
{
    /**
     * @return list<array{code: string, description: string, description_en: string}>
     */
    public static function all(): array
    {
        static $items;

        if (is_array($items)) {
            return $items;
        }

        $path = database_path('master_icd_x.json');
        $mtime = is_file($path) ? (string) filemtime($path) : '0';

        /** @var list<array{code: string, description: string, description_en: string}> $items */
        $items = Cache::remember('icd_catalog:'.$mtime, now()->addDays(7), function () use ($path) {
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

                $code = trim((string) ($row['kode_icd'] ?? ''));

                if ($code === '') {
                    continue;
                }

                $indo = trim((string) ($row['nama_icd_indo'] ?? ''));
                $en = trim((string) ($row['nama_icd'] ?? ''));

                $mapped[] = [
                    'code' => $code,
                    'description' => $indo !== '' ? $indo : $en,
                    'description_en' => $en,
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
        $query = trim($query);
        $limit = max(1, min(50, $limit));
        $page = max(1, $page);
        $items = collect(self::all());

        if ($query !== '') {
            $needle = mb_strtolower($query);
            $codeNeedle = str_replace('.', '', $needle);

            $items = $items
                ->filter(function (array $item) use ($needle, $codeNeedle) {
                    $code = mb_strtolower($item['code']);
                    $codePlain = str_replace('.', '', $code);

                    return str_contains($code, $needle)
                        || str_contains($codePlain, $codeNeedle)
                        || str_contains(mb_strtolower($item['description']), $needle)
                        || str_contains(mb_strtolower($item['description_en']), $needle);
                })
                ->sortBy(function (array $item) use ($needle, $codeNeedle) {
                    $code = mb_strtolower($item['code']);
                    $codePlain = str_replace('.', '', $code);

                    if ($code === $needle || $codePlain === $codeNeedle) {
                        return '0'.$code;
                    }

                    if (str_starts_with($code, $needle) || str_starts_with($codePlain, $codeNeedle)) {
                        return '1'.$code;
                    }

                    return '2'.$code;
                });
        }

        $total = $items->count();
        $offset = ($page - 1) * $limit;

        $results = $items
            ->slice($offset, $limit)
            ->values()
            ->map(fn (array $item) => [
                'id' => $item['code'],
                'text' => $item['code'].' — '.$item['description'],
                'code' => $item['code'],
                'description' => $item['description'],
            ])
            ->all();

        return [
            'results' => $results,
            'pagination' => [
                'more' => ($offset + $limit) < $total,
            ],
        ];
    }
}
