<?php

namespace App\Support\Clinical;

final class IcdSearch
{
    /**
     * @param  list<array{code: string, description: string}>  $items
     * @return array{results: list<array{id: string, text: string, code: string, description: string}>, pagination: array{more: bool}}
     */
    public static function paginate(array $items, string $query, int $limit = 30, int $page = 1): array
    {
        $query = trim($query);
        $limit = max(1, min(50, $limit));
        $page = max(1, $page);
        $collection = collect($items);

        if ($query !== '') {
            $needle = mb_strtolower($query);
            $codeNeedle = str_replace('.', '', $needle);

            $collection = $collection
                ->filter(function (array $item) use ($needle, $codeNeedle) {
                    $code = mb_strtolower($item['code']);
                    $codePlain = str_replace('.', '', $code);

                    return str_contains($code, $needle)
                        || str_contains($codePlain, $codeNeedle)
                        || str_contains(mb_strtolower($item['description']), $needle);
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

        $total = $collection->count();
        $offset = ($page - 1) * $limit;

        $results = $collection
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
