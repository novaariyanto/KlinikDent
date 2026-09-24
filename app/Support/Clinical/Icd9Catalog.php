<?php

namespace App\Support\Clinical;

use Illuminate\Support\Facades\Cache;

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

        /** @var list<array{code: string, description: string}> $items */
        $items = Cache::remember('icd9_catalog:'.(is_file($path) ? filemtime($path) : '0'), now()->addDay(), function () use ($path) {
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

    public static function description(string $code): ?string
    {
        foreach (self::all() as $item) {
            if ($item['code'] === $code) {
                return $item['description'];
            }
        }

        return null;
    }
}
