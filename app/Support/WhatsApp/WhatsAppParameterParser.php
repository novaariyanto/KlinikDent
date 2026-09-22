<?php

namespace App\Support\WhatsApp;

final class WhatsAppParameterParser
{
    /**
     * @return list<int>
     */
    public static function positions(string $body): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        $positions = array_map('intval', $matches[1] ?? []);
        $positions = array_values(array_unique($positions));
        sort($positions);

        return $positions;
    }

    /**
     * @param  list<array<string, mixed>>  $mapping
     * @return list<array{position: int, label: string, source: string}>
     */
    public static function mergeMapping(string $body, array $mapping = []): array
    {
        $existing = [];

        foreach ($mapping as $row) {
            $position = (int) ($row['position'] ?? 0);

            if ($position < 1) {
                continue;
            }

            $existing[$position] = [
                'position' => $position,
                'label' => (string) ($row['label'] ?? ''),
                'source' => (string) ($row['source'] ?? ''),
            ];
        }

        $merged = [];

        foreach (self::positions($body) as $position) {
            $merged[] = $existing[$position] ?? [
                'position' => $position,
                'label' => '',
                'source' => '',
            ];
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $mapping
     * @return list<string>
     */
    public static function missing(array $mapping): array
    {
        $errors = [];

        foreach ($mapping as $row) {
            $position = (int) ($row['position'] ?? 0);
            $label = trim((string) ($row['label'] ?? ''));
            $source = trim((string) ($row['source'] ?? ''));

            if ($position < 1 || $label === '' || $source === '') {
                $errors[] = '{{'.$position.'}}';
            }
        }

        return $errors;
    }

    /**
     * @param  list<mixed>  $placeholders
     * @return array{preview: string, positions: list<int>}
     */
    public static function fromRemote(string $body, array $placeholders = []): array
    {
        $fromList = [];

        foreach ($placeholders as $placeholder) {
            $position = (int) preg_replace('/\D+/', '', (string) $placeholder);

            if ($position >= 1) {
                $fromList[] = $position;
            }
        }

        $fromList = array_values(array_unique($fromList));
        $preview = $body;

        if (self::positions($body) === [] && $fromList !== []) {
            $replace = $fromList;
            rsort($replace);

            foreach ($replace as $position) {
                $preview = preg_replace(
                    '/(?<!\{)(?<!\d)'.$position.'(?!\d)(?!\})/',
                    '{{'.$position.'}}',
                    (string) $preview,
                ) ?? (string) $preview;
            }
        }

        $positions = self::positions($preview);

        if ($positions === []) {
            sort($fromList);
            $positions = $fromList;
        }

        return [
            'preview' => $preview,
            'positions' => $positions,
        ];
    }

    public static function toRemoteBody(string $preview): string
    {
        return (string) preg_replace('/\{\{(\d+)\}\}/', '$1', $preview);
    }
}
